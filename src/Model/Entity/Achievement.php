<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Achievement Entity
 *
 * @property int $id
 * @property \Cake\I18n\Date $week_start
 * @property \Cake\I18n\Date $week_end
 * @property string $player_id
 * @property string $map_id
 * @property string $event_type
 * @property string $count
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Player $player
 * @property \App\Model\Entity\Map $map
 */
class Achievement extends Entity
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
        'week_start' => true,
        'week_end' => true,
        'player_id' => true,
        'map_id' => true,
        'event_type' => true,
        'count' => true,
        'created' => true,
        'player' => true,
        'map' => true,
    ];
}
