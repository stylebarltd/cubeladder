<?php
use Migrations\BaseMigration;

class FlagRunsMode extends BaseMigration
{
    /**
     * Flag runs get a mode: `flag` (flag to flag and back) or `parkour` (the
     * map's fixed course of pickups, in order, and home). Boards are per map
     * and mode.
     */
    public function change(): void
    {
        $this->table('flag_runs')
            ->addColumn('mode', 'string', ['limit' => 16, 'default' => 'flag', 'after' => 'map'])
            ->addIndex(['map', 'mode', 'time_ms'])
            ->update();
    }
}
