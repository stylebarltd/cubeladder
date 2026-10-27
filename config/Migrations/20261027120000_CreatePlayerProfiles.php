<?php
use Migrations\BaseMigration;

class CreatePlayerProfiles extends BaseMigration
{
    /**
     * What a player sets up with their QR hunt points (QrHunt::UNLOCKS): the
     * share card's colour and tagline (1 point), a short text about themselves
     * (2 points). Its own table: players is written by the log parser.
     */
    public function change(): void
    {
        $this->table('player_profiles', ['id' => false, 'primary_key' => ['player_id'], 'collation' => 'utf8mb4_general_ci'])
            ->addColumn('player_id', 'uuid')
            ->addColumn('card_color', 'string', ['limit' => 16, 'null' => true, 'default' => null])   // a key of QrHunt::CARD_COLORS
            ->addColumn('card_tagline', 'string', ['limit' => 40, 'null' => true, 'default' => null])
            ->addColumn('bio', 'string', ['limit' => 160, 'null' => true, 'default' => null])
            ->addColumn('modified', 'datetime')
            ->create();
    }
}
