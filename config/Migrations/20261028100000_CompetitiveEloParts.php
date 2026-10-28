<?php
use Migrations\BaseMigration;

class CompetitiveEloParts extends BaseMigration
{
    /**
     * The parts of each Elo change, so the pages can show how a rating came
     * about (CompetitiveRatings): the player's team strength and the other
     * team's (average Elo of the rated players), the expected result (win
     * chance 0..1) and the K factor used. Rebuilt with the ratings.
     */
    public function change(): void
    {
        $this->table('comp_player_stats')
            ->addColumn('team_rating', 'float', ['null' => true, 'default' => null, 'after' => 'rating_after'])
            ->addColumn('opp_rating', 'float', ['null' => true, 'default' => null, 'after' => 'team_rating'])
            ->addColumn('expected', 'float', ['null' => true, 'default' => null, 'after' => 'opp_rating'])
            ->addColumn('k_factor', 'float', ['null' => true, 'default' => null, 'after' => 'expected'])
            ->update();
    }
}
