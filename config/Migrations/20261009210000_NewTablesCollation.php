<?php
use Migrations\BaseMigration;

class NewTablesCollation extends BaseMigration
{
    /**
     * The rating and milestone tables were created with the connection's
     * collation (utf8mb4_unicode_ci on production), the rest of the database
     * uses utf8mb4_general_ci - joining them failed with "Illegal mix of
     * collations". Bring them in line with the other tables.
     */
    public function up(): void
    {
        foreach (['player_ratings', 'player_game_ratings', 'player_milestones', 'player_milestone_progress'] as $table) {
            $this->execute("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        }
    }

    public function down(): void
    {
    }
}
