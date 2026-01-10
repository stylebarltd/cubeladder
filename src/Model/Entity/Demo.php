<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Demo Entity
 *
 * @property int $id
 * @property int|null $game_id
 * @property string $filename
 * @property int|null $sequence
 * @property \Cake\I18n\DateTime|null $created_at
 *
 * @property \App\Model\Entity\Game $game
 */
class Demo extends Entity
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
        'filename' => true,
        'sequence' => true,
        'created_at' => true,
        'game' => true,
    ];
}
