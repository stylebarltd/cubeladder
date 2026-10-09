<?php
use Migrations\BaseMigration;

class AddTeams extends BaseMigration
{
    /**
     * Team per player and game (CLA/RVSF, last team seen in the status
     * blocks) and the final team totals of the game as the in-game
     * scoreboard shows them: {"CLA":{"players":3,"frags":69,"flags":10},...}
     */
    public function change(): void
    {
        $this->table('player_stats_per_game')
            ->addColumn('team', 'string', ['limit' => 4, 'default' => null, 'null' => true, 'after' => 'player_id'])
            ->update();
        $this->table('games')
            ->addColumn('team_scores', 'text', ['default' => null, 'null' => true, 'after' => 'inaccurate_reason'])
            ->update();
    }
}
