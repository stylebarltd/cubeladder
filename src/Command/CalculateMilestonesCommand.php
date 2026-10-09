<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\MilestoneService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\I18n\DateTime;
use Throwable;

/**
 * Rebuilds player_milestones and player_milestone_progress (MilestoneService)
 * after every import, and announces new Gold-and-up milestones of tracked
 * players in the Discord results channel (Ladder.discord.results.webhook).
 *
 * "New" = not in the table before this run; the very first run (empty
 * table) announces nothing, and only milestones reached in the last
 * ANNOUNCE_DAYS are announced, so a rebuild cannot flood the channel.
 */
class CalculateMilestonesCommand extends Command
{
    private const ANNOUNCE_DAYS = 3;
    private const MAX_LINES = 15;

    public static function defaultName(): string
    {
        return 'CalculateMilestones';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Calculate player milestones and announce new big ones in Discord')
            ->addOption('no-announce', ['help' => 'Store only, post nothing to Discord', 'boolean' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $started = microtime(true);
        $result = (new MilestoneService())->compute();
        $connection = $this->fetchTable('PlayerStatsPerGame')->getConnection();

        // what was there before, to find the new ones
        $before = [];
        foreach ($connection->execute('SELECT player_id, milestone, tier FROM player_milestones')->fetchAll('assoc') as $r) {
            $before[$r['player_id'] . '|' . $r['milestone'] . '|' . $r['tier']] = true;
        }
        $new = $before ? array_values(array_filter(
            $result['milestones'],
            fn($m) => !isset($before[$m['player_id'] . '|' . $m['milestone'] . '|' . $m['tier']])
        )) : [];

        $now = DateTime::now();
        $connection->transactional(function ($connection) use ($result, $now) {
            $connection->execute('DELETE FROM player_milestones');
            foreach (array_chunk($result['milestones'], 500) as $chunk) {
                $query = $connection->insertQuery('player_milestones')
                    ->insert(['player_id', 'milestone', 'tier', 'threshold', 'reached_at', 'game_id', 'detail']);
                foreach ($chunk as $row) {
                    $query->values($row);
                }
                $query->execute();
            }
            $connection->execute('DELETE FROM player_milestone_progress');
            foreach (array_chunk($result['progress'], 500, true) as $chunk) {
                $query = $connection->insertQuery('player_milestone_progress')
                    ->insert(['player_id', 'progress', 'calculated_at'], ['calculated_at' => 'datetime']);
                foreach ($chunk as $playerId => $progress) {
                    $query->values(['player_id' => $playerId, 'progress' => json_encode($progress), 'calculated_at' => $now]);
                }
                $query->execute();
            }
        });

        $io->out(sprintf('%d milestones of %d players (%d new) in %.1f s',
            count($result['milestones']), count($result['progress']), count($new), microtime(true) - $started));

        if (!$args->getOption('no-announce')) {
            $this->announce($new, $io);
        }

        return self::CODE_SUCCESS;
    }

    /**
     * One Discord message listing the new Gold+ tiers of tracked players.
     */
    private function announce(array $new, ConsoleIo $io): void
    {
        $webhook = Configure::read('Ladder.discord.results.webhook');
        $since = date('Y-m-d H:i:s', strtotime('-' . self::ANNOUNCE_DAYS . ' days'));
        $big = array_values(array_filter($new, fn($m) => isset(MilestoneService::MILESTONES[$m['milestone']])
            && $m['tier'] >= MilestoneService::ANNOUNCE_FROM_TIER && $m['reached_at'] >= $since));
        if (!$webhook || !$big) {
            return;
        }

        $players = $this->fetchTable('Players')->find()
            ->select(['id', 'name'])
            ->where(['id IN' => array_unique(array_column($big, 'player_id')), 'track' => 1])
            ->all()->combine('id', 'name')->toArray();
        // highest tiers first; a player who jumped two tiers at once only gets the top one
        usort($big, fn($a, $b) => $b['tier'] <=> $a['tier']);
        $seen = [];
        $lines = [];
        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        foreach ($big as $m) {
            if (!isset($players[$m['player_id']]) || isset($seen[$m['player_id'] . $m['milestone']])) {
                continue;
            }
            $seen[$m['player_id'] . $m['milestone']] = true;
            $def = MilestoneService::MILESTONES[$m['milestone']];
            $lines[] = sprintf(
                '%s **[%s](%s/players/view/%s)** reached **%s %s** · %s',
                $m['tier'] >= 5 ? '💎' : '🏆',
                strtr($players[$m['player_id']], ['[' => '(', ']' => ')', '*' => "\u{2217}", '`' => "\u{2CB}"]),
                $site,
                $m['player_id'],
                number_format((int)$m['threshold']),
                $def['unit'],
                MilestoneService::TIER_NAMES[$m['tier']] ?? ''
            );
        }
        if (!$lines) {
            return;
        }
        $more = count($lines) - self::MAX_LINES;
        $lines = array_slice($lines, 0, self::MAX_LINES);
        if ($more > 0) {
            $lines[] = "… and {$more} more";
        }

        try {
            $res = (new Client(['timeout' => 15]))->post($webhook . '?wait=true', (string)json_encode([
                'username' => 'cubeLadder Milestones',
                'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
                'embeds' => [[
                    'title' => count($lines) === 1 ? 'New milestone' : 'New milestones',
                    'description' => implode("\n", $lines),
                    'color' => 0xEAB308,
                ]],
                'allowed_mentions' => ['parse' => []],
            ]), ['type' => 'json']);
            $io->out($res->isOk() ? 'Announced ' . count($lines) . ' milestone(s)' : 'Discord: HTTP ' . $res->getStatusCode());
        } catch (Throwable $e) {
            $io->err('Discord: ' . $e->getMessage());
        }
    }
}
