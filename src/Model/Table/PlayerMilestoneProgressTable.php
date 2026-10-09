<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Current milestone totals per player (bin/cake CalculateMilestones), JSON in progress.
 * Rebuilt after every import, so it is read-only for the app.
 */
class PlayerMilestoneProgressTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('player_milestone_progress');
        $this->setPrimaryKey('player_id');
    }
}
