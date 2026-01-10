<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * LogOffset Entity
 *
 * @property int $id
 * @property string $server_name
 * @property string $log_path
 * @property int $last_offset
 * @property int $inode
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 */
class LogOffset extends Entity
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
        'server_name' => true,
        'log_path' => true,
        'last_offset' => true,
        'inode' => true,
        'created' => true,
        'modified' => true,
    ];
}
