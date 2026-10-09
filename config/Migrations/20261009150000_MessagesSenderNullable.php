<?php
use Migrations\BaseMigration;

class MessagesSenderNullable extends BaseMigration
{
    /**
     * Messages from anonymous visitors (contact form to the admin) have no
     * sender - before they were stored as sent by the admin himself.
     */
    public function up(): void
    {
        $this->table('messages')
            ->changeColumn('sender_id', 'char', ['limit' => 36, 'null' => true, 'default' => null])
            ->update();
    }

    public function down(): void
    {
        $this->table('messages')
            ->changeColumn('sender_id', 'char', ['limit' => 36, 'null' => false])
            ->update();
    }
}
