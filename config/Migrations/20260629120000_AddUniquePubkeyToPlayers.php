<?php
use Migrations\BaseMigration;

class AddUniquePubkeyToPlayers extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('players');

        // Guarantee one player row per pubkey. MariaDB allows multiple NULL
        // values in a unique index, so name-only (pubkey IS NULL) rows are
        // unaffected; only real, pubkey-bearing identities are deduplicated.
        $table->addIndex(
            ['pubkey'],
            [
                'name' => 'unique_pubkey',
                'unique' => true,
            ]
        );

        $table->update();
    }
}
