<?php
use Migrations\BaseMigration;

class AddMinutesPlayed extends BaseMigration
{
    /**
     * Minutes a player was actually playing in a game (status blocks on a
     * team, spectating excluded). Below PlayerStatsPerGameTable::MIN_MINUTES
     * the row still counts for every total, just not as a "game played".
     * NULL = unknown (counts as a game).
     */
    public function change(): void
    {
        $this->table('player_stats_per_game')
            ->addColumn('minutes_played', 'integer', ['default' => null, 'null' => true, 'after' => 'team'])
            ->update();
    }
}
