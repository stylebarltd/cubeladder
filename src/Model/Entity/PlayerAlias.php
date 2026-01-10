<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PlayerAlias Entity
 *
 * @property int $id
 * @property string $player_id
 * @property string $alias
 * @property \Cake\I18n\DateTime $first_seen
 * @property \Cake\I18n\DateTime $last_seen
 *
 * @property \App\Model\Entity\Player $player
 */
class PlayerAlias extends Entity
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
        'player_id' => true,
        'alias' => true,
        'first_seen' => true,
        'last_seen' => true,
        'player' => true,
    ];
}
