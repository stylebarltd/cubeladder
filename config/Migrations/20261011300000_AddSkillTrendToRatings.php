<?php
use Migrations\BaseMigration;

class AddSkillTrendToRatings extends BaseMigration
{
    /**
     * Form of the skill rating (bin/cake CalculateRatings): the last 10
     * games against the last 100, on the per-game 0-10 scale, as the CTF
     * rating's trend - for the arrows.
     */
    public function change(): void
    {
        $this->table('player_ratings')
            ->addColumn('skill_trend', 'decimal', ['precision' => 4, 'scale' => 2, 'null' => true, 'default' => null, 'after' => 'skill_rank'])
            ->update();
    }
}
