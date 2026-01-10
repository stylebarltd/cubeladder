<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PlayersFixture
 */
class PlayersFixture extends TestFixture
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
                'id' => '9207f049-6c1f-4a30-acd4-257313d05af5',
                'name' => 'Lorem ipsum dolor sit amet',
                'picture' => 'Lorem ipsum dolor sit amet',
                'pubkey' => 'Lorem ipsum dolor sit amet',
                'first_seen' => '2026-01-04 07:15:32',
                'last_seen' => '2026-01-04 07:15:32',
                'ip' => 'Lorem ipsum dolor sit amet',
                'country' => 'Lo',
                'track' => 1,
                'locked' => 1,
                'likes' => 1,
                'views' => 1,
                'created' => '2026-01-04 07:15:32',
                'modified' => '2026-01-04 07:15:32',
                'latitude' => 1.5,
                'longitude' => 1.5,
                'geo_accuracy' => 1,
            ],
        ];
        parent::init();
    }
}
