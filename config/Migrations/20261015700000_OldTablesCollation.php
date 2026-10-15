<?php
use Migrations\BaseMigration;

class OldTablesCollation extends BaseMigration
{
    /**
     * Like NewTablesCollation: on production player_pubkeys, merge_requests
     * and player_merges are utf8mb4_unicode_ci and player_aliases is even
     * latin1 - joins with players failed ("Illegal mix of collations", which
     * stopped CleanUp --deep) and aliases with other letters could not be
     * stored. All utf8mb4_general_ci like the rest.
     */
    public function up(): void
    {
        foreach (['player_aliases', 'player_pubkeys', 'merge_requests', 'player_merges'] as $table) {
            $this->execute("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        }
    }

    public function down(): void
    {
    }
}
