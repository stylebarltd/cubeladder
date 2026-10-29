<?php
use Migrations\BaseMigration;

class AimRoundsSetup extends BaseMigration
{
    /**
     * Aim trainer fair play: how a round was played (AimTrainer::finish) -
     * input (only "mouse" is accepted), the game's size in CSS pixels (fixed
     * WIDTH x HEIGHT), the screen's pixel ratio and the browser zoom as the
     * page saw it. Null for the rounds before.
     */
    public function change(): void
    {
        $this->table('aim_rounds')
            ->addColumn('input', 'string', ['limit' => 8, 'null' => true, 'default' => null, 'after' => 'avg_reaction_ms'])
            ->addColumn('width', 'smallinteger', ['null' => true, 'default' => null, 'after' => 'input'])
            ->addColumn('height', 'smallinteger', ['null' => true, 'default' => null, 'after' => 'width'])
            ->addColumn('dpr', 'float', ['null' => true, 'default' => null, 'after' => 'height'])
            ->addColumn('zoom', 'float', ['null' => true, 'default' => null, 'after' => 'dpr'])
            ->update();
    }
}
