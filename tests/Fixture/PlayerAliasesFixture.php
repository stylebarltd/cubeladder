<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PlayerAliasesFixture
 */
class PlayerAliasesFixture extends TestFixture
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
                'player_id' => '72537e7b-68f7-4ce1-9baa-41fff0161427',
                'alias' => 'Lorem ipsum dolor sit amet',
                'first_seen' => '2026-01-03 07:35:28',
                'last_seen' => '2026-01-03 07:35:28',
            ],
        ];
        parent::init();
    }
}
