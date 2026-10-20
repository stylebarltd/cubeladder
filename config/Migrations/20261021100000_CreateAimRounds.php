<?php
use Migrations\BaseMigration;

class CreateAimRounds extends BaseMigration
{
    /**
     * Aim trainer (/play, AimTrainer service): one row per round. A round is
     * started with a one-time token and seed, and finished once; guests play
     * too (player_id null) but only recognised players are on the board.
     */
    public function change(): void
    {
        $this->table('aim_rounds', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('token', 'char', ['limit' => 32])
            ->addColumn('seed', 'integer', ['signed' => false])
            ->addColumn('player_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('started_at', 'datetime')
            ->addColumn('finished_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('score', 'integer', ['null' => true, 'default' => null])
            ->addColumn('shots', 'integer', ['null' => true, 'default' => null])
            ->addColumn('hits', 'integer', ['null' => true, 'default' => null])
            ->addColumn('headshots', 'integer', ['null' => true, 'default' => null])
            ->addColumn('escapes', 'integer', ['null' => true, 'default' => null])
            ->addColumn('avg_reaction_ms', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['finished_at', 'score'])
            ->addIndex(['player_id'])
            ->create();
    }
}
