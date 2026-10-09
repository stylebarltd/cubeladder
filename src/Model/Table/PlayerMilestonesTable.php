<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Player milestones (bin/cake CalculateMilestones): one row per reached tier.
 * Rebuilt after every import, so it is read-only for the app.
 */
class PlayerMilestonesTable extends Table
{
    /**
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('player_milestones');
        $this->setPrimaryKey(['player_id', 'milestone', 'tier']);
    }
}
