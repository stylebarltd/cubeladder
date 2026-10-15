<?php
use Migrations\BaseMigration;

class CreateLivePlayerRuns extends BaseMigration
{
    /**
     * live_player_runs: one row per player and game as the live loop sees it
     * (extinfo every 15 s, LiveStatsRecorder) - the latest damage and shot
     * damage (damage the shots could have done: accuracy = damage / that).
     * bin/cake link_live_stats copies them to the log's game
     * (player_stats_per_game.damage / shot_damage) after each import.
     */
    public function up(): void
    {
        $this->table('live_player_runs', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('server_name', 'string', ['limit' => 64])
            ->addColumn('map', 'string', ['limit' => 128])
            ->addColumn('mode', 'string', ['limit' => 16])
            ->addColumn('name', 'string', ['limit' => 64])
            ->addColumn('first_seen', 'datetime') // UTC
            ->addColumn('last_seen', 'datetime')
            ->addColumn('damage', 'integer', ['default' => 0])
            ->addColumn('shot_damage', 'integer', ['default' => 0])
            ->addColumn('frags', 'integer', ['default' => 0])
            ->addColumn('guns', 'string', ['limit' => 255, 'null' => true]) // JSON gun => polls holding it
            ->addIndex(['server_name', 'last_seen'])
            ->addIndex(['server_name', 'map', 'first_seen'])
            ->create();

        $this->table('player_stats_per_game')
            ->addColumn('damage', 'integer', ['null' => true, 'default' => null, 'after' => 'avg_ping'])
            ->addColumn('shot_damage', 'integer', ['null' => true, 'default' => null, 'after' => 'damage'])
            ->update();
    }

    public function down(): void
    {
        $this->table('live_player_runs')->drop()->save();
        $this->table('player_stats_per_game')->removeColumn('damage')->removeColumn('shot_damage')->update();
    }
}
