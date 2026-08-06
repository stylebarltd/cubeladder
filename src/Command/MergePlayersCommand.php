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
 *   bin/cake merge_players --by-subnet            Merge rotating-pubkey dupes:
 *                                                 same name on the same /24
 *                                                 subnet (one human, new key
 *                                                 every session).
 *   bin/cake merge_players "A" "Nameseeker"       Force-merge the given names
 *                                                 (use for real players who own
 *                                                 several pubkeys).
 *   bin/cake merge_players --from <id> --to <id>  Merge one specific profile
 *                                                 into another; the --to profile
 *                                                 survives regardless of game
 *                                                 counts. Ids may be unique
 *                                                 prefixes.
 *   bin/cake merge_players --by-subnet --dry-run  Preview without writing.
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
            ->addOption('by-subnet', [
                'boolean' => true,
                'help' => 'Merge same-name profiles that share a /24 subnet (rotating-pubkey dupes).',
            ])
            ->addOption('from', [
                'help' => 'Id of the profile to merge away (accepts a unique id prefix). Requires --to.',
            ])
            ->addOption('to', [
                'help' => 'Id of the surviving profile (accepts a unique id prefix). Requires --from.',
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

        $groups = $this->resolveGroups($args, $conn, $io);
        if (empty($groups)) {
            $io->error('Nothing to merge. Pass names as arguments or use --auto / --by-subnet.');
            return self::CODE_ERROR;
        }

        $io->out(sprintf('Merging %d group(s)%s', count($groups), $dryRun ? ' [DRY RUN]' : ''));

        $totalMerged = 0;
        $totalGroups = 0;

        foreach ($groups as $group) {
            $profiles = $group['profiles'];
            if (count($profiles) < 2) {
                continue;
            }

            $survivor = null;
            if (!empty($group['survivor_id'])) {
                foreach ($profiles as $p) {
                    if ($p['id'] === $group['survivor_id']) {
                        $survivor = $p;
                        break;
                    }
                }
            }
            $survivor = $survivor ?? $this->pickSurvivor($profiles);
            $dupIds = [];
            foreach ($profiles as $p) {
                if ($p['id'] !== $survivor['id']) {
                    $dupIds[] = $p['id'];
                }
            }

            $io->out(sprintf(
                '- %-24s %d profiles -> survivor %s (%d games), merging %d',
                $group['label'],
                count($profiles),
                substr($survivor['id'], 0, 8),
                $survivor['games'],
                count($dupIds)
            ));

            if ($dryRun) {
                $totalGroups++;
                $totalMerged += count($dupIds);
                continue;
            }

            $conn->transactional(function ($c) use ($survivor, $dupIds, $profiles) {
                $this->mergeInto($c, $survivor, $dupIds, $profiles);
            });

            $totalGroups++;
            $totalMerged += count($dupIds);
        }

        $io->success(sprintf(
            '%s %d duplicate profile(s) across %d group(s).',
            $dryRun ? 'Would merge' : 'Merged',
            $totalMerged,
            $totalGroups
        ));

        return self::CODE_SUCCESS;
    }

    /**
     * Shared default / non-identity names that must never be merged together.
     * "unarmed" is the AssaultCube client default and is used by countless
     * distinct people, so its rows are not a single player.
     */
    private const IGNORED_NAMES = ['unarmed'];

    /**
     * Build the list of profile groups to merge. Each group is a set of
     * duplicate profiles that should collapse into one survivor.
     *
     * @return array<int, array{label:string, profiles:array}>
     */
    private function resolveGroups(Arguments $args, $conn, ConsoleIo $io): array
    {
        $fromOpt = $args->getOption('from');
        $toOpt = $args->getOption('to');
        if ($fromOpt !== null || $toOpt !== null) {
            if ($fromOpt === null || $toOpt === null) {
                $io->error('--from and --to must be used together.');

                return [];
            }

            $from = $this->profileById($conn, (string)$fromOpt, $io);
            $to = $this->profileById($conn, (string)$toOpt, $io);
            if ($from === null || $to === null) {
                return [];
            }
            if ($from['id'] === $to['id']) {
                $io->error('--from and --to resolve to the same profile.');

                return [];
            }

            return [[
                'label' => sprintf('%s -> %s', substr($from['id'], 0, 8), substr($to['id'], 0, 8)),
                'profiles' => [$from, $to],
                'survivor_id' => $to['id'],
            ]];
        }

        if ($args->getOption('by-subnet')) {
            return $this->subnetGroups($conn);
        }

        if ($args->getOption('auto')) {
            $rows = $conn->execute(
                "SELECT name FROM players
                 GROUP BY name
                 HAVING COUNT(*) > 1
                    AND COUNT(DISTINCT CASE WHEN pubkey IS NOT NULL AND pubkey <> '' THEN pubkey END) = 1"
            )->fetchAll('assoc');

            $groups = [];
            foreach ($rows as $r) {
                if (in_array(strtolower($r['name']), self::IGNORED_NAMES, true)) {
                    continue;
                }
                $groups[] = [
                    'label' => $r['name'],
                    'profiles' => $this->profilesForName($conn, $r['name']),
                ];
            }

            return $groups;
        }

        $arg = $args->getArgument('names');
        if ($arg === null || trim($arg) === '') {
            return [];
        }

        $names = array_map('trim', explode(',', $arg));
        $names = array_filter($names, fn($n) => $n !== '');
        $names = array_values(array_unique($names));

        $groups = [];
        foreach ($names as $name) {
            $groups[] = [
                'label' => $name,
                'profiles' => $this->profilesForName($conn, $name),
            ];
        }

        return $groups;
    }

    /**
     * Rotating-pubkey duplicates: the same name appearing more than once on the
     * same /24 subnet. That is one human whose client mints a fresh pubkey each
     * session on a dynamic IP. Different networks stay separate so distinct
     * people who share a common name are never merged.
     *
     * @return array<int, array{label:string, profiles:array}>
     */
    private function subnetGroups($conn): array
    {
        $placeholders = implode(',', array_fill(0, count(self::IGNORED_NAMES), '?'));
        $rows = $conn->execute(
            "SELECT name, SUBSTRING_INDEX(ip, '.', 3) AS net
             FROM players
             WHERE ip IS NOT NULL AND ip <> ''
               AND LOWER(name) NOT IN ($placeholders)
             GROUP BY name, net
             HAVING COUNT(*) > 1",
            self::IGNORED_NAMES
        )->fetchAll('assoc');

        $groups = [];
        foreach ($rows as $r) {
            $groups[] = [
                'label' => $r['name'] . ' @' . $r['net'] . '.0/24',
                'profiles' => $this->profilesForNameNet($conn, $r['name'], $r['net']),
            ];
        }

        return $groups;
    }

    /**
     * Look up one profile by exact id, falling back to a unique id prefix
     * (the command prints 8-char prefixes, so those are accepted back).
     *
     * @return array{id:string,pubkey:?string,games:int,first_seen:?string,last_seen:?string,picture:?string,ip:?string,country:?string}|null
     */
    private function profileById($conn, string $id, ConsoleIo $io): ?array
    {
        $select = "SELECT p.id, p.pubkey, p.first_seen, p.last_seen, p.picture, p.ip, p.country,
                          (SELECT COUNT(*) FROM player_stats_per_game s WHERE s.player_id = p.id) AS games
                   FROM players p";

        $rows = $conn->execute("$select WHERE p.id = :id", ['id' => $id])->fetchAll('assoc');
        if (count($rows) === 1) {
            return $rows[0];
        }

        $escaped = addcslashes($id, '%_\\');
        $rows = $conn->execute("$select WHERE p.id LIKE :prefix", ['prefix' => $escaped . '%'])->fetchAll('assoc');
        if (count($rows) === 1) {
            return $rows[0];
        }

        if (count($rows) > 1) {
            $io->error(sprintf('Id prefix "%s" matches %d profiles; use a longer id.', $id, count($rows)));
        } else {
            $io->error(sprintf('No player found with id "%s".', $id));
        }

        return null;
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
     * Profiles for one name restricted to a single /24 subnet.
     *
     * @return array<int, array{id:string,pubkey:?string,games:int,first_seen:?string,last_seen:?string,picture:?string,ip:?string,country:?string}>
     */
    private function profilesForNameNet($conn, string $name, string $net): array
    {
        return $conn->execute(
            "SELECT p.id, p.pubkey, p.first_seen, p.last_seen, p.picture, p.ip, p.country,
                    (SELECT COUNT(*) FROM player_stats_per_game s WHERE s.player_id = p.id) AS games
             FROM players p
             WHERE p.name = :name AND SUBSTRING_INDEX(p.ip, '.', 3) = :net",
            ['name' => $name, 'net' => $net]
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
