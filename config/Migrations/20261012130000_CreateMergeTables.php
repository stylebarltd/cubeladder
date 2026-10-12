<?php
use Migrations\BaseMigration;

class CreateMergeTables extends BaseMigration
{
    /**
     * Profile merges (App\Service\PlayerMerger):
     * - merge_requests: "this was my old profile" from a player, or an admin's
     *   question to a player about a likely duplicate (DuplicateFinder);
     *   dismissed pairs stay here so they are not suggested again.
     * - player_merges: what was merged into what, so old profile links still
     *   lead to the profile that is left.
     */
    public function change(): void
    {
        $this->table('merge_requests')
            ->addColumn('from_id', 'char', ['limit' => 36])
            ->addColumn('to_id', 'char', ['limit' => 36])
            ->addColumn('requested_by', 'char', ['limit' => 36, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 16, 'default' => 'pending'])
            ->addColumn('note', 'text', ['null' => true])
            ->addColumn('created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('resolved_at', 'datetime', ['null' => true])
            ->addIndex(['from_id'])
            ->addIndex(['to_id'])
            ->addIndex(['status'])
            ->create();

        $this->table('player_merges')
            ->addColumn('from_id', 'char', ['limit' => 36])
            ->addColumn('from_name', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('to_id', 'char', ['limit' => 36])
            ->addColumn('games', 'integer', ['default' => 0])
            ->addColumn('source', 'string', ['limit' => 16])
            ->addColumn('merged_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['from_id'], ['unique' => true])
            ->addIndex(['to_id'])
            ->create();
    }
}
