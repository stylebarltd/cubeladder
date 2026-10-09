<?php
use Migrations\BaseMigration;

class CreatePlayerGameRatings extends BaseMigration
{
    /**
     * CTF rating of every player in every rated CTF game (0.1 - 9.9, 5.0 =
     * an average game), rebuilt by bin/cake CalculateRatings.
     */
    public function change(): void
    {
        $this->table('player_game_ratings', ['id' => false, 'primary_key' => ['game_id', 'player_id']])
            ->addColumn('game_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('player_id', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('rating', 'decimal', ['precision' => 3, 'scale' => 1, 'null' => false])
            ->create();
    }
}
