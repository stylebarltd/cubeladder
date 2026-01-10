<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Event Entity
 *
 * @property int $id
 * @property string|null $game_id
 * @property \Cake\I18n\DateTime|null $event_time
 * @property string $event_hash
 * @property string|null $type
 * @property string|null $raw
 * @property string|null $actor_id
 * @property string|null $target_id
 * @property string|null $details
 *
 * @property \App\Model\Entity\Game $game
 * @property \App\Model\Entity\Player $actor
 * @property \App\Model\Entity\Player $target
 */
class Event extends Entity
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
        'event_time' => true,
        'event_hash' => true,
        'type' => true,
        'raw' => true,
        'actor_id' => true,
        'target_id' => true,
        'details' => true,
        'game' => true,
        'actor' => true,
        'target' => true,
    ];
}
