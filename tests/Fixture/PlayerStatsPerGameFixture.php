<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * PlayerStatsPerGameFixture
 */
class PlayerStatsPerGameFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'player_stats_per_game';
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
                'game_id' => 'a2d95cb1-b1a2-4fff-9116-9489e87a027b',
                'player_id' => '3bc3d1eb-abcb-4088-bcfd-93cda0934487',
                'kills' => 1,
                'teamkills' => 1,
                'deaths' => 1,
                'headshot' => 1,
                'busted' => 1,
                'shredded' => 1,
                'peppered' => 1,
                'sprayed' => 1,
                'punctured' => 1,
                'splattered' => 1,
                'slashed' => 1,
                'gibbed' => 1,
                'suicided' => 1,
                'picked_off' => 1,
                'stole_the_flag' => 1,
                'lost_the_flag' => 1,
                'returned_the_flag' => 1,
                'scored_with_the_flag' => 1,
                'kd_ratio' => 1,
                'total_score' => 1,
            ],
        ];
        parent::init();
    }
}
