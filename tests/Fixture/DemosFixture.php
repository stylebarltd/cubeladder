<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * DemosFixture
 */
class DemosFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'game_id' => 1,
                'filename' => 'Lorem ipsum dolor sit amet',
                'sequence' => 1,
                'created_at' => '2025-12-06 14:52:38',
            ],
        ];
        parent::init();
    }
}
