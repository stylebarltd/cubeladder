<?php
use Migrations\BaseMigration;

class CreateAim3dRounds extends BaseMigration
{
    /**
     * 3D aim trainer (/run, mode "Shooting", Aim3D service): one row per
     * round. The score is computed on the server from the shots the page
     * sends; guests play too (player_id null), only recognised players are on
     * the board.
     */
    public function change(): void
    {
        $this->table('aim3d_rounds', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('token', 'char', ['limit' => 32])
            ->addColumn('map', 'string', ['limit' => 64])
            ->addColumn('player_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('started_ms', 'biginteger')
            ->addColumn('finished_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('score', 'integer', ['null' => true, 'default' => null])
            ->addColumn('shots', 'integer', ['null' => true, 'default' => null])
            ->addColumn('hits', 'integer', ['null' => true, 'default' => null])
            ->addColumn('headshots', 'integer', ['null' => true, 'default' => null])
            ->addColumn('avg_reaction_ms', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['map', 'score'])
            ->addIndex(['player_id'])
            ->addIndex(['finished_at'])
            ->create();
    }
}
