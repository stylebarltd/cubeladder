<?php
use Migrations\BaseMigration;

class Aim3dRunAndGun extends BaseMigration
{
    /**
     * Run & Gun (/run): the 3D rounds get the course - pickups collected in
     * order (counted by the server from the path) and the moment the course
     * was done (end_ms, null when the time ran out) - and a mode, so the
     * boards stay apart if more 3D modes come.
     */
    public function change(): void
    {
        $this->table('aim3d_rounds')
            ->addColumn('mode', 'string', ['limit' => 16, 'default' => 'rungun', 'after' => 'map'])
            ->addColumn('pickups', 'integer', ['null' => true, 'default' => null, 'after' => 'headshots'])
            ->addColumn('end_ms', 'integer', ['null' => true, 'default' => null, 'after' => 'pickups'])
            ->addIndex(['map', 'mode', 'score'])
            ->update();
    }
}
