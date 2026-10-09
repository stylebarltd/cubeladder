<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * PlayerRatings Model: CTF rating (0.1 - 9.9) and player type per rated
 * player, written by bin/cake CalculateRatings (the table is rebuilt on
 * every run, so it is read-only for the app).
 */
class PlayerRatingsTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('player_ratings');
        $this->setPrimaryKey('player_id');

        $this->belongsTo('Players', [
            'foreignKey' => 'player_id',
            'joinType' => 'INNER',
        ]);
    }
}
