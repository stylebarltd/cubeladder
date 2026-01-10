<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * GamesFixture
 */
class GamesFixture extends TestFixture
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
                'id' => '477e585c-c922-44a1-a258-2601f251a510',
                'unique_key' => 'Lorem ipsum dolor sit amet',
                'mode' => 'Lorem ipsum dolor sit amet',
                'map_id' => '58cb068e-86b7-4a99-87f2-f756a735e5e9',
                'started_at' => '2025-12-18 08:14:38',
                'ended_at' => '2025-12-18 08:14:38',
                'duration_minutes' => 1,
                'map_rev' => 'Lorem ipsum dolor sit amet',
                'raw' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'created' => '2025-12-18 08:14:38',
                'modified' => '2025-12-18 08:14:38',
            ],
        ];
        parent::init();
    }
}
