<?php
use Migrations\BaseMigration;

class CreateFlagRuns extends BaseMigration
{
    /**
     * Flag run (/run, FlagRun service): one row per run. A run starts with a
     * one-time token at the start of the countdown (started_ms, server clock)
     * and ends when the page sends its time and the path it ran; time_ms is the
     * run's time from GO to bringing the enemy flag home. Guests run too
     * (player_id null); only recognised players are on the boards.
     */
    public function change(): void
    {
        $this->table('flag_runs', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('token', 'char', ['limit' => 32])
            ->addColumn('map', 'string', ['limit' => 64])
            ->addColumn('player_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('started_ms', 'biginteger')
            ->addColumn('finished_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('time_ms', 'integer', ['null' => true, 'default' => null])
            ->addColumn('samples', 'integer', ['null' => true, 'default' => null])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['map', 'time_ms'])
            ->addIndex(['player_id'])
            ->addIndex(['finished_at'])
            ->create();
    }
}
