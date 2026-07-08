<?php
use Migrations\BaseMigration;

class AddPerformanceIndexes extends BaseMigration
{
    /**
     * Indexes to fix slow ranking/maps pages.
     *
     * Before these, /players and /maps were doing full table scans + filesorts:
     *  - games had no index on started_at / ended_at / map_id, so every
     *    "recent games" ordering and every per-map lookup scanned all games.
     *  - player_stats_per_game had only a composite index starting with
     *    game_id, so filtering/joining by player_id alone (the last_game_id
     *    subquery on /players) scanned the whole stats table per player.
     *
     * Measured effect on /players index query: ~29.8s -> ~0.7s.
     */
    public function change(): void
    {
        $games = $this->table('games');
        if (!$games->hasIndex(['started_at'])) {
            $games->addIndex(['started_at'], ['name' => 'idx_games_started_at']);
        }
        if (!$games->hasIndex(['ended_at'])) {
            $games->addIndex(['ended_at'], ['name' => 'idx_games_ended_at']);
        }
        if (!$games->hasIndex(['map_id'])) {
            $games->addIndex(['map_id'], ['name' => 'idx_games_map_id']);
        }
        $games->update();

        $stats = $this->table('player_stats_per_game');
        if (!$stats->hasIndex(['player_id'])) {
            $stats->addIndex(['player_id'], ['name' => 'idx_pspg_player_id']);
        }
        $stats->update();
    }
}
