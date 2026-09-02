<?php
use Migrations\BaseMigration;

class AlterKillPairsCollation extends BaseMigration
{
    /**
     * kill_pairs was created with the server's default collation, which on
     * the webhost is utf8mb4_unicode_ci while every other table (players,
     * games, ...) is utf8mb4_general_ci - joining on the id columns then
     * fails with "Illegal mix of collations". Convert to match; a no-op
     * where the table already has the right collation.
     */
    public function up(): void
    {
        $this->execute('ALTER TABLE kill_pairs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    }

    public function down(): void
    {
        // nothing to restore - the old collation was an accident
    }
}
