<?php
use Migrations\BaseMigration;

class CreateCompetitive extends BaseMigration
{
    /**
     * The competitive ladder, apart from the normal one: games on the clan
     * match servers (Ladder.competitive.servers), seen only by the live view
     * (no logs) - CompetitiveTracker.
     *
     * - comp_games: one per game that started with even teams (2v2+); counted
     *   when it was a full game seen from start to end (counted, reason)
     * - comp_player_stats: the players' final numbers and their Elo change
     * - comp_ratings: each player's Elo (player_key = ladder player id, or
     *   "name:<name>" when no profile matches)
     * - comp_running: the game each server is in right now
     */
    public function up(): void
    {
        $this->table('comp_games', ['id' => false, 'primary_key' => ['id'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('id', 'char', ['limit' => 36])
            ->addColumn('server_name', 'string', ['limit' => 64])
            ->addColumn('map', 'string', ['limit' => 128])
            ->addColumn('mode', 'string', ['limit' => 16])
            ->addColumn('started_at', 'datetime') // UTC
            ->addColumn('ended_at', 'datetime')
            ->addColumn('duration_minutes', 'integer')
            ->addColumn('team_size', 'string', ['limit' => 8]) // "3v3" at the start
            ->addColumn('team_scores', 'text') // JSON CLA / RVSF: flags, frags
            ->addColumn('winner', 'string', ['limit' => 8, 'null' => true]) // CLA / RVSF / null = draw
            ->addColumn('counted', 'boolean', ['default' => false])
            ->addColumn('reason', 'string', ['limit' => 255, 'null' => true]) // why not counted
            ->addIndex(['ended_at'])
            ->create();

        $this->table('comp_player_stats', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('comp_game_id', 'char', ['limit' => 36])
            ->addColumn('player_key', 'string', ['limit' => 80])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 64])
            ->addColumn('team', 'string', ['limit' => 8])
            ->addColumn('flags', 'integer', ['default' => 0])
            ->addColumn('frags', 'integer', ['default' => 0])
            ->addColumn('deaths', 'integer', ['default' => 0])
            ->addColumn('teamkills', 'integer', ['default' => 0])
            ->addColumn('share', 'float', ['default' => 1]) // part of the game played
            ->addColumn('at_end', 'boolean', ['default' => true])
            ->addColumn('rating_before', 'float', ['null' => true])
            ->addColumn('rating_after', 'float', ['null' => true])
            ->addIndex(['comp_game_id'])
            ->addIndex(['player_key'])
            ->addIndex(['player_id'])
            ->create();

        $this->table('comp_ratings', ['id' => false, 'primary_key' => ['player_key'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('player_key', 'string', ['limit' => 80])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 64])
            ->addColumn('rating', 'float')
            ->addColumn('peak', 'float')
            ->addColumn('games', 'integer')
            ->addColumn('wins', 'integer')
            ->addColumn('losses', 'integer')
            ->addColumn('draws', 'integer')
            ->addColumn('last_game_at', 'datetime')
            ->addIndex(['player_id'])
            ->create();

        $this->table('comp_running', ['id' => false, 'primary_key' => ['server_name'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('server_name', 'string', ['limit' => 64])
            ->addColumn('state', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_MEDIUM])
            ->addColumn('updated_at', 'datetime')
            ->create();
    }

    public function down(): void
    {
        foreach (['comp_games', 'comp_player_stats', 'comp_ratings', 'comp_running'] as $t) {
            $this->table($t)->drop()->save();
        }
    }
}
