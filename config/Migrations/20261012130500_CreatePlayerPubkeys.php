<?php
use Migrations\BaseMigration;

class CreatePlayerPubkeys extends BaseMigration
{
    /**
     * Every pubkey a player has logged in with (players.pubkey is only the
     * latest one): a player with two PCs, or one whose profiles were merged,
     * stays one profile whichever key logs in (AcLogParser).
     */
    public function up(): void
    {
        $this->table('player_pubkeys', ['id' => false, 'primary_key' => ['pubkey']])
            ->addColumn('pubkey', 'string', ['limit' => 128])
            ->addColumn('player_id', 'char', ['limit' => 36])
            ->addColumn('first_seen', 'datetime', ['null' => true])
            ->addColumn('last_seen', 'datetime', ['null' => true])
            ->addIndex(['player_id'])
            ->create();

        $this->execute("INSERT IGNORE INTO player_pubkeys (pubkey, player_id, first_seen, last_seen)
            SELECT pubkey, id, first_seen, last_seen FROM players WHERE pubkey IS NOT NULL AND pubkey <> ''");
    }

    public function down(): void
    {
        $this->table('player_pubkeys')->drop()->save();
    }
}
