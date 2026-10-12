<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Merge requests: a player's "this was my profile", our question to a player, dismissed pairs (see AdminController).
 */
class MergeRequestsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('merge_requests');
        $this->setPrimaryKey('id');
    }
}
