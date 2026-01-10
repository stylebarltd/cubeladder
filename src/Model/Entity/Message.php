<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Message Entity
 *
 * @property int $id
 * @property string $sender_id
 * @property string $receiver_id
 * @property string|null $body
 * @property bool|null $is_read
 * @property \Cake\I18n\DateTime|null $created
 *
 * @property \App\Model\Entity\Player $sender
 * @property \App\Model\Entity\Player $receiver
 */
class Message extends Entity
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
        'sender_id' => true,
        'receiver_id' => true,
        'body' => true,
        'is_read' => true,
        'created' => true,
        'sender' => true,
        'receiver' => true,
    ];
}
