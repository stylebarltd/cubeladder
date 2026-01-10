<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * NotificationsFixture
 */
class NotificationsFixture extends TestFixture
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
                'player_id' => 1,
                'message_id' => 1,
                'is_seen' => 1,
                'created' => '2025-12-12 04:12:55',
            ],
        ];
        parent::init();
    }
}
