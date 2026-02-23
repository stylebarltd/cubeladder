<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * WebVisit Entity
 *
 * @property string $id
 * @property string $ip_address
 * @property string|null $country_iso
 * @property string|null $user_agent
 * @property bool $is_bot
 * @property string $path
 * @property string $method
 * @property string|null $referer
 * @property string|null $player_id
 * @property \Cake\I18n\DateTime $created
 *
 * @property \App\Model\Entity\Player $player
 */
class WebVisit extends Entity
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
        'ip_address' => true,
        'country_iso' => true,
        'user_agent' => true,
        'is_bot' => true,
        'path' => true,
        'method' => true,
        'referer' => true,
        'player_id' => true,
        'created' => true,
        'player' => true,
    ];
}
