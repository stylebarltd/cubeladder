<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * AchievementsFixture
 */
class AchievementsFixture extends TestFixture
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
                'week_start' => '2025-12-18',
                'week_end' => '2025-12-18',
                'player_id' => 'd608c557-2040-4176-857a-a45884fe26a6',
                'map_id' => 'a883bedc-f12e-4187-9e32-2fb2528cb270',
                'event_type' => 'Lorem ipsum dolor sit amet',
                'count' => 1.5,
                'created' => '2025-12-18 12:36:39',
            ],
        ];
        parent::init();
    }
}
