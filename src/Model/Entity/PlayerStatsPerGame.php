<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PlayerStatsPerGame Entity
 *
 * @property int $id
 * @property string $game_id
 * @property string $player_id
 * @property int|null $kills
 * @property int|null $teamkills
 * @property int|null $deaths
 * @property int|null $headshot
 * @property int|null $busted
 * @property int|null $shredded
 * @property int|null $peppered
 * @property int|null $sprayed
 * @property int|null $punctured
 * @property int|null $splattered
 * @property int|null $slashed
 * @property int|null $gibbed
 * @property int|null $suicided
 * @property int|null $picked_off
 * @property int|null $stole_the_flag
 * @property int|null $lost_the_flag
 * @property int|null $returned_the_flag
 * @property int|null $scored_with_the_flag
 * @property float|null $kd_ratio
 * @property float|null $total_score
 *
 * @property \App\Model\Entity\Game $game
 * @property \App\Model\Entity\Player $player
 */
class PlayerStatsPerGame extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'game_id' => true,
        'player_id' => true,
        'kills' => true,
        'teamkills' => true,
        'deaths' => true,
        'headshot' => true,
        'busted' => true,
        'shredded' => true,
        'peppered' => true,
        'sprayed' => true,
        'punctured' => true,
        'splattered' => true,
        'slashed' => true,
        'gibbed' => true,
        'suicided' => true,
        'picked_off' => true,
        'stole_the_flag' => true,
        'lost_the_flag' => true,
        'returned_the_flag' => true,
        'scored_with_the_flag' => true,
        'kd_ratio' => true,
        'total_score' => true,
        'game' => true,
        'player' => true,
    ];
}
