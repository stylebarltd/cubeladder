<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/**
 * Merge duplicate player profiles into a single survivor.
 *
 * For each target name, the survivor is the profile with the MOST games
 * (player_stats_per_game rows). All other profiles for that name are merged
 * into it: their stats, web_visits and achievements are repointed to the
 * survivor and the duplicate rows are deleted.
 *
 * Usage:
 *   bin/cake merge_players --auto                 Merge every name that maps to
 *                                                 exactly one pubkey identity.
 *   bin/cake merge_players "A" "Nameseeker"       Force-merge the given names
 *                                                 (use for real players who own
 *                                                 several pubkeys).
 *   bin/cake merge_players --auto --dry-run       Preview without writing.
 */
class MergePlayersCommand extends Command
{
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Merge duplicate player profiles into the profile with the most games.')
            ->addArgument('names', [
                'help' => 'Player name(s) to force-merge. Separate multiple names with commas, e.g. "A,Nameseeker".',
            ])
            ->addOption('auto', [
                'boolean' => true,
                'help' => 'Merge all names that map to exactly one pubkey identity.',
            ])
            ->addOption('dry-run', [
                'boolean' => true,
                'help' => 'Show what would happen without modifying the database.',
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $conn = ConnectionManager::get('default');
        $dryRun = (bool)$args->getOption('dry-run');

        $names = $this->resolveNames($args, $conn);
        if (empty($names)) {
            $io->error('No names to merge. Pass names as arguments or use --auto.');
            return self::CODE_ERROR;
        }

        $io->out(sprintf('Merging %d name(s)%s', count($names), $dryRun ? ' [DRY RUN]' : ''));

        $totalMerged = 0;
        $totalNames = 0;

        foreach ($names as $name) {
            $profiles = $this->profilesForName($conn, $name);
            if (count($profiles) < 2) {
                continue;
            }

            $survivor = $this->pickSurvivor($profiles);
            $dupIds = [];
            foreach ($profiles as $p) {
                if ($p['id'] !== $survivor['id']) {
                    $dupIds[] = $p['id'];
                }
            }

            $io->out(sprintf(
                '- %-20s %d profiles -> survivor %s (%d games), merging %d',
                $name,
                count($profiles),
                substr($survivor['id'], 0, 8),
                $survivor['games'],
                count($dupIds)
            ));

            if ($dryRun) {
                $totalNames++;
                $totalMerged += count($dupIds);
                continue;
            }

            $conn->transactional(function ($c) use ($survivor, $dupIds, $profiles) {
                $this->mergeInto($c, $survivor, $dupIds, $profiles);
            });

            $totalNames++;
            $totalMerged += count($dupIds);
        }

        $io->success(sprintf(
            '%s %d duplicate profile(s) across %d name(s).',
            $dryRun ? 'Would merge' : 'Merged',
            $totalMerged,
            $totalNames
        ));

        return self::CODE_SUCCESS;
    }

    /**
     * Decide which names to merge.
     *
     * @return string[]
     */
    private function resolveNames(Arguments $args, $conn): array
    {
        if ($args->getOption('auto')) {
            $rows = $conn->execute(
                "SELECT name FROM players
                 GROUP BY name
                 HAVING COUNT(*) > 1
                    AND COUNT(DISTINCT CASE WHEN pubkey IS NOT NULL AND pubkey <> '' THEN pubkey END) = 1"
            )->fetchAll('assoc');

            return array_map(fn($r) => $r['name'], $rows);
        }

        $arg = $args->getArgument('names');
        if ($arg === null || trim($arg) === '') {
            return [];
        }

        $names = array_map('trim', explode(',', $arg));
        $names = array_filter($names, fn($n) => $n !== '');

        return array_values(array_unique($names));
    }

    /**
     * @return array<int, array{id:string,pubkey:?string,games:int,first_seen:?string,last_seen:?string,picture:?string,ip:?string,country:?string}>
     */
    private function profilesForName($conn, string $name): array
    {
        return $conn->execute(
            "SELECT p.id, p.pubkey, p.first_seen, p.last_seen, p.picture, p.ip, p.country,
                    (SELECT COUNT(*) FROM player_stats_per_game s WHERE s.player_id = p.id) AS games
             FROM players p
             WHERE p.name = :name",
            ['name' => $name]
        )->fetchAll('assoc');
    }

    /**
     * Survivor = most games; tie-break: has a pubkey, then smallest id.
     */
    private function pickSurvivor(array $profiles): array
    {
        usort($profiles, function ($a, $b) {
            if ((int)$a['games'] !== (int)$b['games']) {
                return (int)$b['games'] <=> (int)$a['games'];
            }
            $aPk = !empty($a['pubkey']) ? 1 : 0;
            $bPk = !empty($b['pubkey']) ? 1 : 0;
            if ($aPk !== $bPk) {
                return $bPk <=> $aPk;
            }
            return strcmp($a['id'], $b['id']);
        });

        return $profiles[0];
    }

    private function mergeInto($conn, array $survivor, array $dupIds, array $profiles): void
    {
        $sid = $survivor['id'];

        foreach ($dupIds as $dupId) {
            // Repoint stats; UPDATE IGNORE skips rows that collide on the
            // (game_id, player_id) unique key (same player already has that game).
            $conn->execute(
                'UPDATE IGNORE player_stats_per_game SET player_id = :sid WHERE player_id = :dup',
                ['sid' => $sid, 'dup' => $dupId]
            );
            // Drop any colliding leftovers that UPDATE IGNORE refused to move.
            $conn->execute(
                'DELETE FROM player_stats_per_game WHERE player_id = :dup',
                ['dup' => $dupId]
            );

            $conn->execute(
                'UPDATE web_visits SET player_id = :sid WHERE player_id = :dup',
                ['sid' => $sid, 'dup' => $dupId]
            );
            $conn->execute(
                'UPDATE achievements SET player_id = :sid WHERE player_id = :dup',
                ['sid' => $sid, 'dup' => $dupId]
            );
        }

        // Work out the identity to keep on the survivor.
        $bestPubkey = !empty($survivor['pubkey']) ? $survivor['pubkey'] : null;
        $bestPicture = !empty($survivor['picture']) ? $survivor['picture'] : null;
        $bestIp = !empty($survivor['ip']) ? $survivor['ip'] : null;
        $bestCountry = !empty($survivor['country']) ? $survivor['country'] : null;
        $minFirst = $survivor['first_seen'];
        $maxLast = $survivor['last_seen'];

        foreach ($profiles as $p) {
            if ($bestPubkey === null && !empty($p['pubkey'])) {
                $bestPubkey = $p['pubkey'];
            }
            if ($bestPicture === null && !empty($p['picture'])) {
                $bestPicture = $p['picture'];
            }
            if ($bestIp === null && !empty($p['ip'])) {
                $bestIp = $p['ip'];
            }
            if ($bestCountry === null && !empty($p['country'])) {
                $bestCountry = $p['country'];
            }
            if (!empty($p['first_seen']) && ($minFirst === null || $p['first_seen'] < $minFirst)) {
                $minFirst = $p['first_seen'];
            }
            if (!empty($p['last_seen']) && ($maxLast === null || $p['last_seen'] > $maxLast)) {
                $maxLast = $p['last_seen'];
            }
        }

        // Remove the duplicate player rows FIRST. The survivor may be a
        // pubkey-less profile (most games but never logged in with a pubkey);
        // assigning a duplicate's pubkey to it while that duplicate still
        // exists would violate the unique_pubkey index. Deleting the dups now
        // frees their pubkeys so the survivor can claim one.
        foreach (array_chunk($dupIds, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $conn->execute("DELETE FROM players WHERE id IN ($in)", $chunk);
        }

        // Now consolidate the chosen identity onto the survivor.
        $conn->execute(
            'UPDATE players SET pubkey = :pk, picture = :pic, ip = :ip, country = :co,
                    first_seen = :fs, last_seen = :ls WHERE id = :sid',
            [
                'pk' => $bestPubkey,
                'pic' => $bestPicture,
                'ip' => $bestIp,
                'co' => $bestCountry,
                'fs' => $minFirst,
                'ls' => $maxLast,
                'sid' => $sid,
            ]
        );
    }
}
