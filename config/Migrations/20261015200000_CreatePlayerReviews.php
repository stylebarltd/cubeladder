<?php
use Migrations\BaseMigration;

class CreatePlayerReviews extends BaseMigration
{
    /**
     * The admin's verdict on a player of the suspicious list (Admin
     * suspicious): ok (cleared, off the list), watch or cheater, with a note.
     */
    public function up(): void
    {
        $this->table('player_reviews', ['id' => false, 'primary_key' => ['player_id'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('player_id', 'char', ['limit' => 36])
            ->addColumn('status', 'string', ['limit' => 16])
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('reviewed_at', 'datetime')
            ->create();
    }

    public function down(): void
    {
        $this->table('player_reviews')->drop()->save();
    }
}
