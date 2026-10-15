<?php
use Migrations\BaseMigration;

class CompetitiveSourcesAndAliases extends BaseMigration
{
    /**
     * - comp_games.source: "live" (CompetitiveTracker) or "actionfps"
     *   (bin/cake import_actionfps), external_id = ActionFPS game id
     * - comp_aliases: who a name is (CompetitiveIdentity) - name + /24
     *   network (null for ActionFPS names) -> player_key, so players who
     *   keep changing their name stay one player
     */
    public function up(): void
    {
        $this->table('comp_games')
            ->addColumn('source', 'string', ['limit' => 16, 'default' => 'live', 'after' => 'server_name'])
            ->addColumn('external_id', 'string', ['limit' => 64, 'null' => true, 'after' => 'source'])
            ->addIndex(['external_id'], ['unique' => true])
            ->update();

        $this->table('comp_aliases', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('name', 'string', ['limit' => 64])
            ->addColumn('ip3', 'string', ['limit' => 16, 'default' => ''])
            ->addColumn('player_key', 'string', ['limit' => 80])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('how', 'string', ['limit' => 128])
            ->addColumn('last_seen', 'datetime')
            ->addIndex(['name', 'ip3'], ['unique' => true])
            ->addIndex(['ip3'])
            ->addIndex(['player_key'])
            ->create();
    }

    public function down(): void
    {
        $this->table('comp_aliases')->drop()->save();
        $this->table('comp_games')->removeIndex(['external_id'])->removeColumn('source')->removeColumn('external_id')->update();
    }
}
