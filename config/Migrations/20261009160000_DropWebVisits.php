<?php
use Migrations\BaseMigration;

class DropWebVisits extends BaseMigration
{
    /**
     * The visitor statistics (with visitor IPs) were removed - drop their data.
     */
    public function up(): void
    {
        if ($this->hasTable('web_visits')) {
            $this->table('web_visits')->drop()->save();
        }
    }

    public function down(): void
    {
        // not restored - see 20260213000000_CreateWebVisits for the old schema
    }
}
