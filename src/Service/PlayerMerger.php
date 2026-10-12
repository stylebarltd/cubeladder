<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Database\Connection;
use Cake\Datasource\ConnectionManager;

/**
 * Merges duplicate player profiles into one: games, kills, achievements,
 * milestones, messages, events and names move to the profile that is left,
 * the others are deleted. Each merge is logged in player_merges, so links to
 * a merged profile still lead somewhere (PlayersController::view).
 *
 * Ratings are recalculated by the next import (CalculateRatings rebuilds them).
 * Used by the admin area and bin/cake merge_players.
 */
class PlayerMerger
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? ConnectionManager::get('default');
    }

    /**
     * One profile with what the merge needs (null when it doesn't exist).
     *
     * @return array<string, mixed>|null
     */
    public function profile(string $id): ?array
    {
        $row = $this->conn->execute(
            'SELECT p.*, (SELECT COUNT(*) FROM player_stats_per_game s WHERE s.player_id = p.id) AS games,
                    (SELECT COALESCE(SUM(s.total_score), 0) FROM player_stats_per_game s WHERE s.player_id = p.id) AS points
             FROM players p WHERE p.id = ?', [$id])->fetch('assoc');

        return $row ?: null;
    }

    /**
     * Merges $fromIds into $toId (which stays) in one transaction.
     *
     * @param list<string> $fromIds
     * @param string $source who merged: admin, player request, cli
     * @return int games moved
     */
    public function merge(string $toId, array $fromIds, string $source = 'admin'): int
    {
        $fromIds = array_values(array_unique(array_filter($fromIds, fn($id) => $id !== $toId)));
        $survivor = $this->profile($toId);
        if ($survivor === null) {
            throw new \InvalidArgumentException("No player $toId");
        }
        $dups = [];
        foreach ($fromIds as $id) {
            $dup = $this->profile($id);
            if ($dup === null) {
                throw new \InvalidArgumentException("No player $id");
            }
            $dups[] = $dup;
        }
        if (!$dups) {
            return 0;
        }

        $moved = 0;
        $this->conn->transactional(function (Connection $c) use ($survivor, $dups, $source, &$moved) {
            $sid = $survivor['id'];
            foreach ($dups as $dup) {
                $moved += $this->moveRows($c, $sid, $dup, $source);
            }
            $this->consolidate($c, $survivor, $dups);
        });
        Cache::clear('rankings');

        return $moved;
    }

    /** Everything of one duplicate onto the survivor; returns the games moved. */
    private function moveRows(Connection $c, string $sid, array $dup, string $source): int
    {
        $p = ['sid' => $sid, 'dup' => $dup['id']];

        // games: a game both profiles played (a reconnect) keeps the survivor's row
        $moved = $c->execute('UPDATE IGNORE player_stats_per_game SET player_id = :sid WHERE player_id = :dup', $p)->rowCount();
        $c->execute('DELETE FROM player_stats_per_game WHERE player_id = :dup', ['dup' => $dup['id']]);

        foreach ([['kill_pairs', 'killer_id'], ['kill_pairs', 'victim_id'], ['player_aliases', 'player_id'], ['player_milestones', 'player_id']] as [$table, $col]) {
            $c->execute("UPDATE IGNORE $table SET $col = :sid WHERE $col = :dup", $p);
            $c->execute("DELETE FROM $table WHERE $col = :dup", ['dup' => $dup['id']]);
        }
        // every key of the duplicate now logs in to the survivor (a second PC)
        if (!empty($dup['pubkey'])) {
            $c->execute('INSERT IGNORE INTO player_pubkeys (pubkey, player_id, first_seen, last_seen) VALUES (?, ?, ?, ?)',
                [$dup['pubkey'], $sid, $dup['first_seen'], $dup['last_seen']]);
        }
        foreach ([['player_pubkeys', 'player_id'], ['achievements', 'player_id'], ['events', 'actor_id'], ['events', 'target_id'],
                  ['messages', 'sender_id'], ['messages', 'receiver_id'], ['merge_requests', 'to_id'],
                  ['player_merges', 'to_id']] as [$table, $col]) {
            $c->execute("UPDATE $table SET $col = :sid WHERE $col = :dup", $p);
        }
        // rebuilt by the next CalculateRatings / CalculateMilestones
        foreach (['player_ratings', 'player_game_ratings', 'player_milestone_progress'] as $table) {
            $c->execute("DELETE FROM $table WHERE player_id = :dup", ['dup' => $dup['id']]);
        }

        // the old name stays in the survivor's name history
        $survivorName = $c->execute('SELECT name FROM players WHERE id = ?', [$sid])->fetchColumn(0);
        if (!empty($dup['name']) && $dup['name'] !== $survivorName) {
            $c->execute('INSERT INTO player_aliases (player_id, alias, first_seen, last_seen)
                SELECT :sid, :alias, :fs, :ls FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM player_aliases WHERE player_id = :sid2 AND alias = :alias2)', [
                'sid' => $sid, 'sid2' => $sid, 'alias' => $dup['name'], 'alias2' => $dup['name'],
                'fs' => $dup['first_seen'] ?? date('Y-m-d H:i:s'), 'ls' => $dup['last_seen'] ?? date('Y-m-d H:i:s'),
            ]);
        }

        $c->execute("UPDATE merge_requests SET status = 'merged', resolved_at = NOW()
            WHERE from_id = :dup AND status IN ('pending', 'asked')", ['dup' => $dup['id']]);
        $c->execute('DELETE FROM player_merges WHERE from_id = :dup', ['dup' => $dup['id']]);
        $c->execute('INSERT INTO player_merges (from_id, from_name, to_id, games, source, merged_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$dup['id'], $dup['name'], $sid, $moved, $source]);

        return $moved;
    }

    /** The survivor keeps its own identity and fills the gaps from the duplicates. */
    private function consolidate(Connection $c, array $survivor, array $dups): void
    {
        $best = fn(string $col) => !empty($survivor[$col]) ? $survivor[$col]
            : (array_values(array_filter(array_column($dups, $col)))[0] ?? null);
        $all = array_merge([$survivor], $dups);
        $firsts = array_filter(array_column($all, 'first_seen'));
        $lasts = array_filter(array_column($all, 'last_seen'));
        $values = [
            'pubkey' => $best('pubkey'),
            'picture' => $best('picture'),
            'ip' => $best('ip'),
            'country' => $best('country'),
            'first_seen' => $firsts ? min($firsts) : null,
            'last_seen' => $lasts ? max($lasts) : null,
            'likes' => array_sum(array_map('intval', array_column($all, 'likes'))),
            'views' => array_sum(array_map('intval', array_column($all, 'views'))),
        ];

        // the duplicates go first: their pubkey is unique and may move to the survivor
        $ids = array_column($dups, 'id');
        $c->execute('DELETE FROM players WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);

        $set = implode(', ', array_map(fn($col) => "$col = :$col", array_keys($values)));
        $c->execute("UPDATE players SET $set WHERE id = :sid", $values + ['sid' => $survivor['id']]);
    }
}
