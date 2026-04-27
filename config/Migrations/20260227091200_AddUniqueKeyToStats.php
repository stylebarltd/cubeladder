<?php
use Migrations\BaseMigration;

class AddUniqueKeyToStats extends BaseMigration
{
    public function change(): void
    {
        $table = $this->table('player_stats_per_game');

        // Add unique index on game_id + player_id
        $table->addIndex(
            ['game_id', 'player_id'],
            [
                'name' => 'unique_game_player',
                'unique' => true,
            ]
        );

        $table->update();
    }
}
