<?php
use Migrations\BaseMigration;

class BlacklistCollation extends BaseMigration
{
    /**
     * The blacklist table takes the collation of players (CreateBlacklist got
     * the server default, and comparing the two failed: "Illegal mix of
     * collations" on production).
     */
    public function up(): void
    {
        $row = $this->fetchRow("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'players'");
        $coll = is_array($row) ? (string)($row['TABLE_COLLATION'] ?? '') : '';
        if (preg_match('/^([a-z0-9]+)_[a-z0-9_]+$/', $coll, $m)) {
            $this->execute("ALTER TABLE blacklist CONVERT TO CHARACTER SET {$m[1]} COLLATE {$coll}");
        }
    }

    public function down(): void
    {
    }
}
