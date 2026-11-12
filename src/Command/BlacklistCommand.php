<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\Blacklist;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

/**
 * After every import (process_logs.sh, right after ProcessLogs): blacklists
 * every profile that uses the AssaultCube key (pubkey) of a blacklisted
 * player, and reports how many profiles on a blacklisted IP wait for the
 * admin (Admin -> Blacklist). See App\Service\Blacklist.
 *
 *   bin/cake blacklist
 */
class BlacklistCommand extends Command
{
    public static function defaultName(): string
    {
        return 'blacklist';
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $bl = new Blacklist();
        foreach ($bl->apply() as $p) {
            $io->out(sprintf('Blacklisted %s (%s): same key as %s', $p['name'], $p['id'], $p['source'] ?? '?'));
        }
        $ip = count($bl->ipMatches());
        if ($ip) {
            $io->out(sprintf('%d profile(s) on a blacklisted IP wait in Admin -> Blacklist', $ip));
        }

        return static::CODE_SUCCESS;
    }
}
