<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * WebVisitsFixture
 */
class WebVisitsFixture extends TestFixture
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
                'id' => '0f7f9e0c-b575-4d43-9202-35470b3ba67a',
                'ip_address' => 'Lorem ipsum dolor sit amet',
                'country_iso' => 'Lo',
                'user_agent' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'is_bot' => 1,
                'path' => 'Lorem ipsum dolor sit amet',
                'method' => 'Lorem ip',
                'referer' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
                'player_id' => 'fd227f95-eebb-48dd-b110-10015e1242bc',
                'created' => '2026-02-13 07:25:58',
            ],
        ];
        parent::init();
    }
}
