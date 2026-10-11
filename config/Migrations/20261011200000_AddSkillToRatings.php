<?php
use Migrations\BaseMigration;

class AddSkillToRatings extends BaseMigration
{
    /**
     * Skill rating of a rated player (bin/cake CalculateRatings): the
     * individual rating over their last 100 games of any mode - kills,
     * headshot / knife / grenade kills, K/D, flags, streaks, team wins,
     * lobby strength - with its own place and the team win rate in those games.
     */
    public function change(): void
    {
        $this->table('player_ratings')
            ->addColumn('skill', 'decimal', ['precision' => 3, 'scale' => 1, 'null' => true, 'default' => null, 'after' => 'rank'])
            ->addColumn('skill_rank', 'integer', ['null' => true, 'default' => null, 'after' => 'skill'])
            ->addColumn('skill_win_rate', 'decimal', ['precision' => 4, 'scale' => 3, 'null' => true, 'default' => null, 'after' => 'skill_rank'])
            ->update();
    }
}
