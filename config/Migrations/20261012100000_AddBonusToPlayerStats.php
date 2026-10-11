<?php
use Migrations\BaseMigration;

class AddBonusToPlayerStats extends BaseMigration
{
    /**
     * Placement bonus (bin/cake PlacementBonus): 1st / 2nd / 3rd of a
     * free-for-all game get 20 / 15 / 10 extra points. Kept apart and
     * included in total_score, so it can be recalculated any time.
     */
    public function change(): void
    {
        $this->table('player_stats_per_game')
            ->addColumn('bonus', 'integer', ['null' => false, 'default' => 0, 'after' => 'total_score'])
            ->update();
    }
}
