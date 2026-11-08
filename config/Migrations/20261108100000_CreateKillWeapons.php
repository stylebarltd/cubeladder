<?php
use Migrations\BaseMigration;

class CreateKillWeapons extends BaseMigration
{
    /**
     * Who killed whom with which weapon, per game (Nemesis advanced stats, a QR
     * hunt unlock). Filled by the log parser for new games only (no backfill:
     * games keep no raw log). Teamkills are left out.
     */
    public function change(): void
    {
        $this->table('kill_weapons', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('game_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('killer_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('victim_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('weapon', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('kills', 'integer', ['default' => 0, 'null' => false])
            ->addIndex(['game_id', 'killer_id', 'victim_id', 'weapon'], ['unique' => true, 'name' => 'kill_weapons_unique'])
            ->addIndex(['killer_id'])
            ->addIndex(['victim_id'])
            ->create();
    }
}
