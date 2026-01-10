<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * LogOffsetsFixture
 */
class LogOffsetsFixture extends TestFixture
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
                'server_name' => 'Lorem ipsum dolor sit amet',
                'log_path' => 'Lorem ipsum dolor sit amet',
                'last_offset' => 1,
                'inode' => 1,
                'created' => '2025-12-18 08:16:12',
                'modified' => '2025-12-18 08:16:12',
            ],
        ];
        parent::init();
    }
}
