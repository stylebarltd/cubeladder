<?php
use Migrations\BaseMigration;

class AddTimelinePingFlagEvents extends BaseMigration
{
    /**
     * - game_timelines: the scoreboard of every minute of a game (the server's
     *   "Game status" blocks), JSON - see AcLogParser::timelineSnapshot()
     * - avg_ping: the player's mean ping over the game's minute scoreboards
     * - flag events of hunt / keep the flag: picked up, dropped, failed to score
     *   (only counted, no points)
     */
    public function up(): void
    {
        $this->table('game_timelines', ['id' => false, 'primary_key' => ['game_id'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('game_id', 'char', ['limit' => 36])
            ->addColumn('timeline', 'text', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::TEXT_LONG])
            ->create();

        $this->table('player_stats_per_game')
            ->addColumn('avg_ping', 'smallinteger', ['null' => true, 'default' => null, 'after' => 'minutes_played'])
            ->addColumn('picked_up_the_flag', 'integer', ['null' => false, 'default' => 0, 'after' => 'returned_the_flag'])
            ->addColumn('dropped_the_flag', 'integer', ['null' => false, 'default' => 0, 'after' => 'picked_up_the_flag'])
            ->addColumn('failed_to_score', 'integer', ['null' => false, 'default' => 0, 'after' => 'dropped_the_flag'])
            ->update();
    }

    public function down(): void
    {
        $this->table('game_timelines')->drop()->save();
        $this->table('player_stats_per_game')
            ->removeColumn('avg_ping')
            ->removeColumn('picked_up_the_flag')
            ->removeColumn('dropped_the_flag')
            ->removeColumn('failed_to_score')
            ->update();
    }
}
