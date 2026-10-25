<?php
use Migrations\BaseMigration;

class CreateLinkCodes extends BaseMigration
{
    /**
     * "Say this code in the game" (PlayerLink service, /play): a browser that the
     * site cannot recognise by IP gets a one-time code; the player says it in the
     * chat of a game on our servers, the next log import stores that chat line in
     * events, and the browser is linked to the player who said it. Codes are
     * never reused (primary key), so an old chat line can never match a new code.
     */
    public function change(): void
    {
        $this->table('link_codes', ['id' => false, 'primary_key' => ['code'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('code', 'string', ['limit' => 12])
            ->addColumn('from_event_id', 'integer', ['default' => 0])   // only chat lines newer than this count
            ->addColumn('created', 'datetime')
            ->addColumn('player_id', 'uuid', ['null' => true, 'default' => null])
            ->addColumn('linked', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['created'])
            ->create();
    }
}
