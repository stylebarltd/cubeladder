<?php
declare(strict_types=1);

namespace App\Utility;

use DateTimeInterface;

/**
 * Active players: those with a game in the last ACTIVE_DAYS. Inactive ones
 * drop out of the CTF rating (no rank, not counted among the rated players),
 * the All Time Ranking and the map rating lists, and are marked "inactive"
 * where their old records stay (Hall of Fame, player page). Nothing is
 * stored: the next game makes a player active again.
 */
class Activity
{
    public const ACTIVE_DAYS = 90;

    /** Earliest last game that still counts as active, 'Y-m-d H:i:s' */
    public static function since(): string
    {
        return date('Y-m-d H:i:s', strtotime('-' . self::ACTIVE_DAYS . ' days'));
    }

    public static function isActive(DateTimeInterface|string|null $lastSeen): bool
    {
        if ($lastSeen === null || $lastSeen === '') {
            return false;
        }
        $ts = $lastSeen instanceof DateTimeInterface ? $lastSeen->getTimestamp() : strtotime((string)$lastSeen);

        return $ts >= strtotime(self::since());
    }
}
