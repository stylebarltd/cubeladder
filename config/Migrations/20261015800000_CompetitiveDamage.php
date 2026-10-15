<?php
use Migrations\BaseMigration;

class CompetitiveDamage extends BaseMigration
{
    /**
     * Damage and "shot damage" (accuracy = damage / shot damage) of a
     * competitive player as the live view saw it at the end - null when the
     * server hid them (match mode).
     */
    public function change(): void
    {
        $this->table('comp_player_stats')
            ->addColumn('damage', 'integer', ['null' => true, 'default' => null, 'after' => 'teamkills'])
            ->addColumn('shot_damage', 'integer', ['null' => true, 'default' => null, 'after' => 'damage'])
            ->update();
    }
}
