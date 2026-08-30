<?php
use Migrations\BaseMigration;

class AddLongestStreakToStats extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('player_stats_per_game');

        // Longest run of consecutive kills without dying within a single game
        $table->addColumn('longest_streak', 'integer', [
            'default' => 0,
            'null' => false,
            'after' => 'scored_with_the_flag',
        ]);

        $table->update();
    }
}
