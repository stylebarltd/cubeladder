<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * MatchesFixture
 */
class MatchesFixture extends TestFixture
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
                'mode' => 'Lorem ipsum dolor sit amet',
                'map' => 'Lorem ipsum dolor sit amet',
                'started_at' => '2025-12-06 14:45:07',
                'ended_at' => '2025-12-06 14:45:07',
                'duration_minutes' => 1,
                'mastermode' => 'Lorem ipsum dolor sit amet',
                'map_rev' => 'Lorem ipsum dolor sit amet',
                'demo_file' => 'Lorem ipsum dolor sit amet',
                'created' => '2025-12-06 14:45:07',
                'modified' => '2025-12-06 14:45:07',
            ],
        ];
        parent::init();
    }
}
