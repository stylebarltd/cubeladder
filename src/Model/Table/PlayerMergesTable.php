<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * Merged profiles: from_id is gone, to_id is what it became (App\Service\PlayerMerger).
 */
class PlayerMergesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('player_merges');
        $this->setPrimaryKey('id');
    }
}
