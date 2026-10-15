<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\CompetitiveIdentity;
use App\Service\CompetitiveRatings;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Client;
use Cake\Utility\Text;

/**
 * Competitive games from actionfps.com, the clan scene's old ladder (it gets
 * the servers' logs): history for the competitive Elo and the games our live
 * tracker missed. There is no API - its game search lists games by time
 * ("2026-10-14" = that day, max 20; "2026-10-14T22" = that hour) with map,
 * mode, both teams and every player's flags, frags and ActionFPS id.
 * Slowly: one request every --delay seconds.
 *
 * NOT RUN ANY MORE (2026-10-15): the competitive ladder starts over with the
 * live tracker only, which also has deaths and accuracy. Kept in case the
 * history is wanted again (backup: resources/cubeladder-20261015-competitive-before-reset.sql.gz).
 *
 *   bin/cake import_actionfps --games=1000      the latest 1000 games (backfill, once)
 *   bin/cake import_actionfps --recent          today and yesterday (process_logs.sh)
 *   bin/cake import_actionfps --dry-run ...
 *
 * A game we saw live ourselves (same map, at the same time) is left out.
 * Kept: team games with 2+ players a side; no deaths there (ActionFPS shows none).
 */
class ImportActionFpsCommand extends Command
{
    private const BASE = 'https://actionfps.com';
    /** The search lists at most this many games */
    private const PAGE = 20;
    private const MODES = ['ctf', 'htf', 'tktf', 'tdm', 'tosok', 'tlss', 'tsurv', 'tpf'];

    private ?Client $http = null;
    private int $delay = 3;
    private int $requests = 0;

    public static function defaultName(): string
    {
        return 'import_actionfps';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Competitive games from actionfps.com')
            ->addOption('games', ['help' => 'Backfill: the latest N games', 'default' => '0'])
            ->addOption('recent', ['help' => 'Only today and yesterday (after each import)', 'boolean' => true])
            ->addOption('before', ['help' => 'Backfill: start with the day before this one (YYYY-MM-DD) - to go on after a break'])
            ->addOption('delay', ['help' => 'Seconds between two requests', 'default' => '3'])
            ->addOption('dry-run', ['help' => 'Fetch and show, write nothing', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $this->delay = max(1, (int)$args->getOption('delay'));
        $this->http = new Client(['timeout' => 30, 'headers' => ['User-Agent' => 'cubeLadder competitive import (https://cubeladder.ovh)']]);
        $dry = (bool)$args->getOption('dry-run');
        $want = (int)$args->getOption('games');
        $recent = (bool)$args->getOption('recent');
        if (!$recent && $want <= 0) {
            $io->err('Use --games=N or --recent');

            return self::CODE_ERROR;
        }

        $games = [];
        if ($recent) {
            foreach ([gmdate('Y-m-d'), gmdate('Y-m-d', time() - 86400)] as $day) {
                $games += $this->day($day);
            }
        } else {
            $empty = 0;
            $before = $args->getOption('before');
            $start = $before ? strtotime($before . ' 12:00 UTC') - 86400 : time();
            for ($t = $start; count($games) < $want && $empty < 60; $t -= 86400) {
                $found = $this->day(gmdate('Y-m-d', $t));
                $empty = $found ? 0 : $empty + 1;
                $games += $found;
                $io->verbose(sprintf('%s: %d games (%d so far, %d requests)', gmdate('Y-m-d', $t), count($found), count($games), $this->requests));
            }
            krsort($games);
            $games = array_slice($games, 0, $want, true);
        }

        $added = $skipped = 0;
        $conn = ConnectionManager::get('default');
        $identity = new CompetitiveIdentity();
        foreach ($games as $g) {
            if ($dry) {
                $io->out(sprintf('%s %s %s %s:%s', $g['id'], $g['mode'], $g['map'], $g['teams']['CLA']['flags'], $g['teams']['RVSF']['flags']));
                continue;
            }
            if ($this->known($g)) {
                $skipped++;
                continue;
            }
            $ended = strtotime($g['id']);
            $flagMode = in_array($g['mode'], ['ctf', 'htf', 'tktf'], true);
            $points = fn($t) => [$flagMode ? $g['teams'][$t]['flags'] : 0, $g['teams'][$t]['frags']];
            $cmp = $points('CLA') <=> $points('RVSF');
            $id = Text::uuid();
            $conn->execute(
                'INSERT INTO comp_games (id, server_name, source, external_id, map, mode, started_at, ended_at, duration_minutes, team_size, team_scores, winner, counted, reason)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NULL)',
                [$id, 'actionfps', 'actionfps', $g['id'], $g['map'], $g['mode'], gmdate('Y-m-d H:i:s', $ended - 15 * 60),
                 gmdate('Y-m-d H:i:s', $ended), 15, count($g['teams']['CLA']['players']) . 'v' . count($g['teams']['RVSF']['players']),
                 json_encode(['CLA' => ['flags' => $g['teams']['CLA']['flags'], 'frags' => $g['teams']['CLA']['frags']],
                              'RVSF' => ['flags' => $g['teams']['RVSF']['flags'], 'frags' => $g['teams']['RVSF']['frags']]]),
                 $cmp > 0 ? 'CLA' : ($cmp < 0 ? 'RVSF' : null)]);
            foreach (['CLA', 'RVSF'] as $team) {
                foreach ($g['teams'][$team]['players'] as $p) {
                    $who = $identity->resolveActionFps($p['name'], $p['afps_id']);
                    $conn->execute(
                        'INSERT INTO comp_player_stats (comp_game_id, player_key, player_id, name, team, flags, frags, deaths, teamkills, share, at_end)
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0, 0, 1, 1)',
                        [$id, $who['key'], $who['player_id'], $p['name'], $team, $p['flags'], $p['frags']]);
                }
            }
            $added++;
        }
        if ($added) {
            (new CompetitiveRatings())->recalculate();
        }
        $io->out(sprintf('%sActionFPS: %d games found, %d added, %d already known (%d requests)',
            $dry ? '[dry run] ' : '', count($games), $added, $skipped, $this->requests));

        return self::CODE_SUCCESS;
    }

    /**
     * All games of a UTC day, keyed by id: the day; when it fills a page, its
     * hours 0x / 1x / 2x; a part that fills a page again, hour by hour.
     */
    private function day(string $day): array
    {
        $games = $this->search($day);
        if (count($games) < self::PAGE) {
            return $games;
        }
        $games = [];
        foreach (['0' => range(0, 9), '1' => range(10, 19), '2' => range(20, 23)] as $tens => $hours) {
            $part = $this->search($day . 'T' . $tens);
            if (count($part) >= self::PAGE) {
                $part = [];
                foreach ($hours as $h) {
                    $part += $this->search(sprintf('%sT%02d', $day, $h));
                }
            }
            $games += $part;
        }

        return $games;
    }

    /** One search page, parsed: team games with 2+ players a side whose id starts with $q */
    private function search(string $q): array
    {
        if ($this->requests++ > 0) {
            sleep($this->delay);
        }
        $res = $this->http->get(self::BASE . '/games-search', ['qq' => $q]);
        if (!$res->isOk()) {
            throw new \RuntimeException('actionfps.com: HTTP ' . $res->getStatusCode() . ' for ' . $q);
        }

        return self::parse((string)$res->getStringBody(), $q);
    }

    /** Game cards of a search page (public for tests) */
    public static function parse(string $html, string $prefix = ''): array
    {
        $games = [];
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        $xp = new \DOMXPath($doc);
        foreach ($xp->query('//article[contains(concat(" ", @class, " "), " GameCard ")]') as $card) {
            $link = $xp->query('.//header//a', $card)->item(0);
            if (!$link || !preg_match('~id=([^&"]+)~', $link->getAttribute('href'), $m)) {
                continue;
            }
            $id = urldecode($m[1]);
            // the search also matches names etc.: keep the games of this time only
            if ($prefix !== '' && !str_starts_with($id, $prefix)) {
                continue;
            }
            $heading = trim((string)$xp->query('.//span[@class="heading"]', $card)->item(0)?->textContent);
            if (!preg_match('~^(\w+) @ (\S+)$~', $heading, $hm) || !in_array($hm[1], self::MODES, true)) {
                continue;
            }
            $teams = [];
            foreach (['cla' => 'CLA', 'rvsf' => 'RVSF'] as $class => $team) {
                $node = $xp->query('.//div[contains(concat(" ", @class, " "), " team ") and contains(concat(" ", @class, " "), " ' . $class . ' ")]', $card)->item(0);
                if (!$node) {
                    continue 2;
                }
                $players = [];
                foreach ($xp->query('.//li[contains(@class, "player")]', $node) as $li) {
                    $a = $xp->query('.//span[contains(@class, "name")]//a', $li)->item(0);
                    $players[] = [
                        'name' => trim((string)$xp->query('.//span[contains(@class, "name")]', $li)->item(0)?->textContent),
                        'afps_id' => $a && preg_match('~id=([^&]+)~', $a->getAttribute('href'), $pm) ? urldecode($pm[1]) : null,
                        'flags' => (int)$xp->query('.//span[contains(@class, "flags")]', $li)->item(0)?->textContent,
                        'frags' => (int)$xp->query('.//span[contains(@class, "frags")]', $li)->item(0)?->textContent,
                    ];
                }
                $result = $xp->query('.//div[@class="result"]', $node)->item(0);
                $teams[$team] = [
                    'flags' => (int)$xp->query('.//span[@class="score"]', $result)->item(0)?->textContent,
                    'frags' => (int)$xp->query('.//span[@class="subscore"]', $result)->item(0)?->textContent,
                    'players' => $players,
                ];
            }
            if (count($teams['CLA']['players']) < 2 || count($teams['RVSF']['players']) < 2) {
                continue;
            }
            $games[$id] = ['id' => $id, 'mode' => $hm[1], 'map' => $hm[2], 'teams' => $teams];
        }

        return $games;
    }

    /** Imported before, or seen live by us (same map, the time falls in our game) */
    private function known(array $g): bool
    {
        $conn = ConnectionManager::get('default');
        if ($conn->execute('SELECT 1 FROM comp_games WHERE external_id = ?', [$g['id']])->fetchColumn(0)) {
            return true;
        }
        $t = gmdate('Y-m-d H:i:s', strtotime($g['id']));

        return (bool)$conn->execute(
            "SELECT 1 FROM comp_games WHERE source = 'live' AND map = ?
               AND ? BETWEEN started_at - INTERVAL 5 MINUTE AND ended_at + INTERVAL 5 MINUTE",
            [$g['map'], $t])->fetchColumn(0);
    }
}
