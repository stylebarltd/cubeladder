<?php
use Migrations\BaseMigration;

class CreateBlacklist extends BaseMigration
{
    /**
     * Admin decisions on a player (App\Service\Blacklist):
     * - players.admin_status: 'untracked' (track = 0, locked: the player cannot switch it back)
     *   or 'blacklisted' (cheater = 1 and track = 0, locked: out of every list on the ladder);
     *   players.admin_note: why (the admin's note);
     * - blacklist: the AssaultCube pubkeys (and, if the admin ticks it, the last IP) of a
     *   blacklisted player. A new profile with a listed pubkey is blacklisted by the import
     *   (bin/cake blacklist); one on a listed IP only shows up in Admin -> Blacklist.
     */
    public function change(): void
    {
        $this->table('players')
            ->addColumn('admin_status', 'string', ['limit' => 16, 'null' => true, 'default' => null, 'after' => 'cheater'])
            ->addColumn('admin_note', 'string', ['limit' => 500, 'null' => true, 'default' => null, 'after' => 'admin_status'])
            ->update();

        $this->table('blacklist')
            ->addColumn('kind', 'string', ['limit' => 8, 'null' => false])           // pubkey | ip
            ->addColumn('value', 'string', ['limit' => 128, 'null' => false])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => true, 'default' => null])
            ->addColumn('note', 'string', ['limit' => 500, 'null' => true, 'default' => null])
            ->addColumn('created', 'datetime', ['null' => false])
            ->addIndex(['kind', 'value'], ['unique' => true])
            ->addIndex(['player_id'])
            ->create();
    }
}
