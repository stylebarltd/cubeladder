<?php
use Migrations\BaseMigration;

class AddCountryIsoToWebVisits extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('web_visits');

        $table->addColumn('country_iso', 'string', [
            'limit' => 2,
            'null' => true,
            'default' => null,
            'after' => 'ip_address',
        ]);

        $table->update();
    }
}
