<?php
use Migrations\BaseMigration;

class CompetitiveIndividual extends BaseMigration
{
    /**
     * The individual rating next to the team Elo (CompetitiveRatings::individual):
     * 0.1 - 9.9 per rated player per game (5.0 = an average game), and the
     * player's average over the last games. Rebuilt with the ratings.
     */
    public function change(): void
    {
        $this->table('comp_player_stats')
            ->addColumn('ind_rating', 'float', ['null' => true, 'default' => null, 'after' => 'k_factor'])
            ->update();
        $this->table('comp_ratings')
            ->addColumn('ind_rating', 'float', ['null' => true, 'default' => null, 'after' => 'peak'])
            ->addColumn('ind_games', 'integer', ['null' => false, 'default' => 0, 'after' => 'ind_rating'])
            ->update();
    }
}
