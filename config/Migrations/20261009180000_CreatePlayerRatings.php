<?php
use Migrations\BaseMigration;

class CreatePlayerRatings extends BaseMigration
{
    /**
     * CTF player rating and player type (bin/cake CalculateRatings): one row
     * per rated player, the table is rebuilt on every run.
     */
    public function change(): void
    {
        $this->table('player_ratings', ['id' => false, 'primary_key' => ['player_id']])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('rating', 'decimal', ['precision' => 3, 'scale' => 1, 'null' => false])
            ->addColumn('rank', 'integer', ['null' => false])
            ->addColumn('type', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('weapon', 'string', ['limit' => 16, 'null' => false])
            ->addColumn('games', 'integer', ['null' => false])
            ->addColumn('attack_pct', 'integer', ['null' => false])
            ->addColumn('defense_pct', 'integer', ['null' => false])
            ->addColumn('combat_pct', 'integer', ['null' => false])
            ->addColumn('win_rate', 'decimal', ['precision' => 4, 'scale' => 3, 'null' => true])
            ->addColumn('calculated_at', 'datetime', ['null' => false])
            ->addIndex(['rank'])
            ->create();
    }
}
