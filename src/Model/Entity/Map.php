<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Map Entity
 *
 * @property string $id
 * @property string $name
 * @property int|null $times_played
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Achievement[] $achievements
 * @property \App\Model\Entity\Game[] $games
 */
class Map extends Entity
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
        'name' => true,
        'times_played' => true,
        'created' => true,
        'modified' => true,
        'achievements' => true,
        'games' => true,
    ];
}
