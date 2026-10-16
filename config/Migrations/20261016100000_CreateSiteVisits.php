<?php
use Migrations\BaseMigration;

class CreateSiteVisits extends BaseMigration
{
    /**
     * Visitor stats without IPs (SiteVisits service): one row per visitor and
     * day - players by their profile, guests by a hash that changes every day -
     * and page views per page and day.
     */
    public function change(): void
    {
        $this->table('site_visitors', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('day', 'date')
            ->addColumn('visitor', 'char', ['limit' => 16])
            ->addColumn('player_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('phone', 'boolean', ['default' => false])
            ->addColumn('source', 'string', ['limit' => 60, 'default' => 'direct'])
            ->addColumn('views', 'integer', ['default' => 0])
            ->addColumn('first_seen', 'datetime')
            ->addColumn('last_seen', 'datetime')
            ->addIndex(['day', 'visitor'], ['unique' => true])
            ->addIndex(['player_id'])
            ->create();

        $this->table('site_page_views', ['id' => false, 'primary_key' => ['day', 'page'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('day', 'date')
            ->addColumn('page', 'string', ['limit' => 60])
            ->addColumn('views', 'integer', ['default' => 0])
            ->create();
    }
}
