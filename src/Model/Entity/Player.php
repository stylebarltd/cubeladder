<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Player Entity
 *
 * @property string $id
 * @property string|null $name
 * @property string|null $picture
 * @property string|null $pubkey
 * @property \Cake\I18n\DateTime|null $first_seen
 * @property \Cake\I18n\DateTime|null $last_seen
 * @property string|null $ip
 * @property string|null $country
 * @property bool|null $track
 * @property bool|null $locked
 * @property int|null $likes
 * @property int|null $views
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $geo_accuracy
 *
 * @property \App\Model\Entity\Achievement[] $achievements
 * @property \App\Model\Entity\PlayerAlias[] $player_aliases
 * @property \App\Model\Entity\PlayerStatsPerGame[] $player_stats_per_game
 */
class Player extends Entity
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
        'picture' => true,
        'pubkey' => true,
        'first_seen' => true,
        'last_seen' => true,
        'ip' => true,
        'country' => true,
        'track' => true,
        'locked' => true,
        'likes' => true,
        'views' => true,
        'created' => true,
        'modified' => true,
        'latitude' => true,
        'longitude' => true,
        'geo_accuracy' => true,
        'achievements' => true,
        'player_aliases' => true,
        'player_stats_per_game' => true,
    ];
}
