<?php
use Migrations\BaseMigration;

class CreateKillPairs extends BaseMigration
{
    /**
     * Who killed whom, aggregated per game (nemesis / favorite victim /
     * head-to-head stats). Filled by the log parser for new games only -
     * no backfill, same rule as longest_streak.
     */
    public function change(): void
    {
        $table = $this->table('kill_pairs');

        $table
            ->addColumn('game_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('killer_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('victim_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('kills', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('teamkills', 'integer', ['default' => 0, 'null' => false])
            ->addIndex(['game_id', 'killer_id', 'victim_id'], ['unique' => true, 'name' => 'kill_pairs_unique'])
            ->addIndex(['killer_id'])
            ->addIndex(['victim_id']);

        $table->create();
    }
}
