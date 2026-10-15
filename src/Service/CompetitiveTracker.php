<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Utility\Text;

/**
 * The competitive ladder: games on the clan match servers
 * (Ladder.competitive.servers), which we have no logs of - only what the
 * live view sees. The live loop calls tick() every few seconds; this keeps
 * the game each server is in (comp_running) and saves it when it ends.
 *
 * A game is kept (comp_games) when it is a team mode that started with even
 * teams of 2 or more; it counts for the Elo when it was a game of
 * Ladder.competitive.minutes (15) seen from its first minute to its end - the
 * server shows the final scoreboard for 10 seconds after the game
 * (minremain 0), polling every 5 s always catches it. Match mode doesn't
 * matter (it only hides accuracy / damage, which we don't use here).
 *
 * Players are names + the first three parts of their IP (CompetitiveIdentity).
 *
 * While a game that started with even teams runs, a message in the cubeLadder
 * on-air channel (Ladder.competitive.onair_webhook, default the "cubeladder"
 * live feed's webhook) links to it on the live page; it is deleted when the
 * game ends.
 */
class CompetitiveTracker
{
    /** Seconds without seeing a server's game before it is closed (not counted) */
    public const LOST_AFTER = 300;
    /** The start's team sizes are taken from the first this-many seconds */
    public const START_WINDOW = 120;
    /** Part of the game a player must have played to win / lose rating */
    public const MIN_SHARE = 0.5;

    public static function servers(): array
    {
        return (array)(Configure::read('Ladder.competitive.servers') ?? ['mys-1111', 'mys-2222', 'mys-3333']);
    }

    /** Polls the competitive servers and moves their games on */
    public function tick(?int $now = null): void
    {
        $all = Configure::read('Ladder.servers') ?: [];
        $targets = array_intersect_key($all, array_flip(self::servers()));
        if (!$targets) {
            return;
        }
        $ext = new AcExtInfoService(1.5);
        $result = $ext->queryMany($targets);
        // UDP drops player packets now and then: ask again when some are missing
        $retry = array_filter($targets, fn($t, $k) => !empty($result[$k]['online'])
            && count($result[$k]['players']) < (int)$result[$k]['numplayers'], ARRAY_FILTER_USE_BOTH);
        if ($retry) {
            foreach ($ext->queryMany($retry) as $key => $info) {
                if (!empty($info['online']) && count($info['players']) > count($result[$key]['players'])) {
                    $result[$key] = $info;
                }
            }
        }
        $this->observe($result, $now ?? time());
    }

    /**
     * One poll of the servers (AcExtInfoService results by server key).
     * Public for tests.
     */
    public function observe(array $servers, int $now): void
    {
        $conn = ConnectionManager::get('default');
        $running = [];
        foreach ($conn->execute('SELECT server_name, state FROM comp_running')->fetchAll('assoc') as $r) {
            $running[$r['server_name']] = json_decode($r['state'], true) ?: null;
        }

        foreach (self::servers() as $key) {
            $s = $servers[$key] ?? null;
            $game = $running[$key] ?? null;
            $seen = $s && !empty($s['online']) && !empty($s['map']) && $s['mode'] !== null;

            if (!$seen) {
                if ($game && $now - $game['last_poll'] > self::LOST_AFTER) {
                    $this->finish($key, $game, 'lost');
                    $game = null;
                }
            } else {
                $min = (int)$s['minremain'];
                $new = !$game || $game['map'] !== $s['map'] || $game['mode'] !== (int)$s['mode']
                    || $min > $game['last_min'] || $now - $game['last_poll'] > self::LOST_AFTER;
                if ($new) {
                    if ($game) {
                        $this->finish($key, $game, 'next');
                    }
                    $game = ['map' => $s['map'], 'mode' => (int)$s['mode'], 'started' => $now, 'first_min' => $min,
                        'last_min' => $min, 'last_poll' => $now, 'polls' => 0, 'start' => ['CLA' => 0, 'RVSF' => 0],
                        'players' => [], 'final' => null];
                }
                $this->update($game, $s, $now);
                $this->announce($key, $game, $s);
            }

            if ($game) {
                $conn->execute('REPLACE INTO comp_running (server_name, state, updated_at) VALUES (?, ?, ?)',
                    [$key, json_encode($game, JSON_UNESCAPED_UNICODE), gmdate('Y-m-d H:i:s', $now)]);
            } else {
                $conn->execute('DELETE FROM comp_running WHERE server_name = ?', [$key]);
            }
        }
    }

    /**
     * A game that started with even teams of 2+ (and was seen from its
     * start): one message in the on-air channel, with a link to watch it.
     */
    private function announce(string $key, array &$game, array $s): void
    {
        $minutes = (int)(Configure::read('Ladder.competitive.minutes') ?? 15);
        if (!empty($game['onair_id']) || !empty($game['onair_tried']) || $game['start']['CLA'] < 2
            || $game['first_min'] !== $minutes || (int)$s['minremain'] === 0
            || !in_array($game['mode'], AcExtInfoService::TEAM_MODES, true)) {
            return;
        }
        $game['onair_tried'] = true; // once per game, also when Discord fails
        $webhook = $this->onAirWebhook();
        if (!$webhook) {
            return;
        }
        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        $names = ['CLA' => [], 'RVSF' => []];
        foreach ($s['players'] as $p) {
            $team = strtoupper((string)$p['team']);
            if (isset($names[$team])) {
                $names[$team][] = str_replace(['*', '_', '`', '|', '~'], ['\*', '\_', '\`', '\|', '\~'], (string)$p['name']);
            }
        }
        $serverName = Configure::read('Ladder.servers.' . $key . '.name') ?: $key;
        $text = sprintf("⚔️ **Competitive game** on **%s** — %dv%d · %s · %s
🔴 %s
🔵 %s
[▶ Watch now](%s/live#%s)",
            $serverName, $game['start']['CLA'], $game['start']['RVSF'], $s['map'], AcExtInfoService::MODES[$game['mode']] ?? '',
            implode(', ', $names['CLA']) ?: '–', implode(', ', $names['RVSF']) ?: '–', $site, rawurlencode($key));
        try {
            $res = (new \Cake\Http\Client(['timeout' => 5]))->post($webhook . '?wait=true', json_encode([
                'username' => 'cubeLadder Live',
                'content' => $text,
                'allowed_mentions' => ['parse' => []],
            ]), ['type' => 'json']);
            if ($res->isOk()) {
                $game['onair_id'] = (string)($res->getJson()['id'] ?? '');
            } else {
                \Cake\Log\Log::warning('competitive: on-air message failed: HTTP ' . $res->getStatusCode());
            }
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('competitive: on-air message failed: ' . $e->getMessage());
        }
    }

    private function deleteOnAir(string $id): void
    {
        $webhook = $this->onAirWebhook();
        if (!$webhook || $id === '') {
            return;
        }
        try {
            (new \Cake\Http\Client(['timeout' => 5]))->delete($webhook . '/messages/' . $id);
        } catch (\Throwable $e) {
            \Cake\Log\Log::warning('competitive: deleting the on-air message failed: ' . $e->getMessage());
        }
    }

    private function onAirWebhook(): ?string
    {
        return Configure::read('Ladder.competitive.onair_webhook') ?: (Configure::read('Ladder.discord.feeds.cubeladder.webhook') ?: null);
    }

    /** Adds one poll to the running game */
    private function update(array &$game, array $s, int $now): void
    {
        $min = (int)$s['minremain'];
        $game['last_min'] = min($game['last_min'], $min);
        $game['last_poll'] = $now;
        $game['polls']++;

        $teams = ['CLA' => 0, 'RVSF' => 0];
        $rows = [];
        foreach ($s['players'] as $p) {
            $team = strtoupper((string)$p['team']);
            if (!isset($teams[$team]) || !empty($p['is_spectator'])) {
                continue;
            }
            $teams[$team]++;
            $name = (string)$p['name'];
            $pl = $game['players'][$name] ?? ['teams' => [], 'seen' => 0, 'ip3' => null];
            $pl['teams'][$team] = ($pl['teams'][$team] ?? 0) + 1;
            $pl['seen']++;
            $pl['ip3'] = $p['ip3'] ?? $pl['ip3'];
            $pl['last'] = ['team' => $team, 'flags' => (int)$p['flags'], 'frags' => (int)$p['frags'],
                'deaths' => (int)$p['deaths'], 'tk' => (int)$p['teamkills']];
            $game['players'][$name] = $pl;
            $rows[$name] = $pl['last'];
        }
        // the team sizes it started with: the largest even line-up of the first seconds
        if ($now - $game['started'] <= self::START_WINDOW && $teams['CLA'] === $teams['RVSF'] && $teams['CLA'] > $game['start']['CLA']) {
            $game['start'] = $teams;
        }
        // game over: the server shows the final scoreboard (minremain 0)
        if ($min === 0 && $game['first_min'] > 0) {
            $game['final'] = $rows;
        }
    }

    /**
     * A server's game is over ($how: "next" - the next game began, "lost" -
     * not seen for LOST_AFTER): saved when it started with even teams of 2+.
     */
    private function finish(string $server, array $game, string $how): void
    {
        if (!empty($game['onair_id'])) {
            $this->deleteOnAir((string)$game['onair_id']);
        }
        $modeCode = AcExtInfoService::MODE_CODES[$game['mode']] ?? null;
        $size = (int)$game['start']['CLA'];
        if (!in_array($game['mode'], AcExtInfoService::TEAM_MODES, true) || $size < 2 || !$game['players']) {
            return;
        }
        $minutes = (int)(Configure::read('Ladder.competitive.minutes') ?? 15);
        $reason = match (true) {
            $game['first_min'] < $minutes => sprintf('seen from minute %d', max(1, $minutes - $game['first_min'] + 1)),
            $game['first_min'] > $minutes => sprintf('a %d-minute game', $game['first_min']),
            $game['final'] === null => $how === 'lost' ? 'server gone before the end' : 'ended early (map changed)',
            default => null,
        };

        // final numbers: the end scoreboard, else each player's last poll
        $final = $game['final'] ?? [];
        $players = [];
        foreach ($game['players'] as $name => $pl) {
            arsort($pl['teams']);
            $team = (string)array_key_first($pl['teams']);
            $row = ($final[$name] ?? null) ?: $pl['last'];
            $players[] = ['name' => (string)$name, 'ip3' => $pl['ip3'], 'team' => $team, 'row' => $row,
                'share' => min(1.0, $pl['seen'] / max(1, $game['polls'])), 'at_end' => isset($final[$name])];
        }
        // the team score as in the game: the players there at the end
        $scores = ['CLA' => ['flags' => 0, 'frags' => 0], 'RVSF' => ['flags' => 0, 'frags' => 0]];
        foreach ($players as $p) {
            if ($p['at_end'] || $final === []) {
                $scores[$p['row']['team']]['flags'] += $p['row']['flags'];
                $scores[$p['row']['team']]['frags'] += $p['row']['frags'];
            }
        }
        $flagMode = in_array($modeCode, ['ctf', 'htf', 'tktf'], true);
        $points = fn($t) => [$flagMode ? $scores[$t]['flags'] : 0, $scores[$t]['frags']];
        $cmp = $points('CLA') <=> $points('RVSF');
        $winner = $cmp > 0 ? 'CLA' : ($cmp < 0 ? 'RVSF' : null);

        $conn = ConnectionManager::get('default');
        $id = Text::uuid();
        $conn->execute(
            'INSERT INTO comp_games (id, server_name, source, map, mode, started_at, ended_at, duration_minutes, team_size, team_scores, winner, counted, reason)
             VALUES (?, ?, \'live\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $server, $game['map'], $modeCode ?? (string)$game['mode'], gmdate('Y-m-d H:i:s', $game['started']),
             gmdate('Y-m-d H:i:s', $game['last_poll']), $game['first_min'], $size . 'v' . $size, json_encode($scores),
             $winner, $reason === null ? 1 : 0, $reason]);
        $identity = new CompetitiveIdentity();
        foreach ($players as $p) {
            $who = $identity->resolve($p['name'], $p['ip3']);
            $conn->execute(
                'INSERT INTO comp_player_stats (comp_game_id, player_key, player_id, name, team, flags, frags, deaths, teamkills, share, at_end)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, $who['key'], $who['player_id'], $p['name'], $p['team'], $p['row']['flags'], $p['row']['frags'],
                 $p['row']['deaths'], $p['row']['tk'], round($p['share'], 3), (int)$p['at_end']]);
        }
        if ($reason === null) {
            (new CompetitiveRatings())->recalculate();
        }
    }

}
