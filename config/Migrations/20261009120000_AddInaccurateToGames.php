<?php
use Migrations\BaseMigration;

class AddInaccurateToGames extends BaseMigration
{
    /**
     * Games whose stats cannot be trusted (e.g. everyone else left and the
     * last player farmed flags against an empty team). Not cheating - just
     * inaccurate data: such games are dropped from every ranking/stat.
     */
    public function change(): void
    {
        $this->table('games')
            ->addColumn('inaccurate', 'boolean', ['default' => false, 'null' => false, 'after' => 'server_name'])
            ->addColumn('inaccurate_reason', 'string', ['limit' => 255, 'default' => null, 'null' => true, 'after' => 'inaccurate'])
            ->addIndex(['inaccurate'])
            ->update();
    }
}
