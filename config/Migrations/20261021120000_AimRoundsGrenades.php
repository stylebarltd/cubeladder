<?php
use Migrations\BaseMigration;

class AimRoundsGrenades extends BaseMigration
{
    /**
     * Aim trainer: rounds got shorter (60 -> 30 s) and grenades came in.
     * The board only compares rounds of the same length (the first rounds
     * were 60 s), gibs are kept apart from the shots' hits.
     */
    public function change(): void
    {
        $this->table('aim_rounds')
            ->addColumn('seconds', 'integer', ['default' => 60, 'after' => 'seed'])
            ->addColumn('gibs', 'integer', ['null' => true, 'default' => null, 'after' => 'headshots'])
            ->addIndex(['seconds', 'finished_at'])
            ->update();
    }
}
