<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Game Entity
 *
 * @property string $id
 * @property string|null $unique_key
 * @property string|null $mode
 * @property string $map_id
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $ended_at
 * @property int|null $duration_minutes
 * @property string|null $server_name
 * @property bool $inaccurate
 * @property string|null $inaccurate_reason
 * @property string|null $team_scores
 * @property string|null $raw
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \App\Model\Entity\Map $map
 * @property \App\Model\Entity\Event[] $events
 * @property \App\Model\Entity\PlayerStatsPerGame[] $player_stats_per_game
 */
class Game extends Entity
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
        'unique_key' => true,
        'mode' => true,
        'map_id' => true,
        'started_at' => true,
        'ended_at' => true,
        'duration_minutes' => true,
        'server_name' => true,
        'inaccurate' => true,
        'inaccurate_reason' => true,
        'team_scores' => true,
        'raw' => true,
        'created' => true,
        'modified' => true,
        'map' => true,
        'events' => true,
        'player_stats_per_game' => true,
    ];
}
