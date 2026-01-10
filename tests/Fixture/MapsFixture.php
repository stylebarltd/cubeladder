<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * MapsFixture
 */
class MapsFixture extends TestFixture
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
                'id' => '3a1cb338-2a07-44a0-a238-6fdc2bb97d0a',
                'name' => 'Lorem ipsum dolor sit amet',
                'times_played' => 1,
                'created' => '2025-12-18 13:00:04',
                'modified' => '2025-12-18 13:00:04',
            ],
        ];
        parent::init();
    }
}
