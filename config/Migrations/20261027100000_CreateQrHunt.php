<?php
use Migrations\BaseMigration;

class CreateQrHunt extends BaseMigration
{
    /**
     * QR hunt (QrHunt service, /q/<token>): QR codes hidden in our maps. Each
     * code has a random token (the address is never guessable); a player's level
     * is the number of different codes they found. The tokens live only here,
     * never in the public map repository.
     */
    public function change(): void
    {
        $this->table('qr_codes', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('token', 'string', ['limit' => 24])
            ->addColumn('map', 'string', ['limit' => 64])
            ->addColumn('label', 'string', ['limit' => 100])               // admin only: where it is hidden
            ->addColumn('hint', 'string', ['limit' => 255, 'null' => true, 'default' => null])   // shown after a find
            ->addColumn('active', 'boolean', ['default' => true])        // false: "this code has moved"
            ->addColumn('created', 'datetime')
            ->addIndex(['token'], ['unique' => true])
            ->create();

        $this->table('qr_finds', ['collation' => 'utf8mb4_general_ci'])
            ->addColumn('qr_code_id', 'integer')
            ->addColumn('player_id', 'uuid')
            ->addColumn('found', 'datetime')
            ->addIndex(['qr_code_id', 'player_id'], ['unique' => true])
            ->addIndex(['player_id'])
            ->create();
    }
}
