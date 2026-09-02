<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\DiscordLiveService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Throwable;

/**
 * Update the Discord "live servers" message(s).
 *
 * Ladder.discord describes the default feed; Ladder.discord.feeds adds
 * more channels (each inherits the default settings and overrides its
 * own webhook / servers / …). Every run updates all configured feeds.
 *
 *   bin/cake discord_live                      one update
 *   bin/cake discord_live --every=15           keep running, update every 15 s (screen/systemd)
 *   bin/cake discord_live --every=15 --for=55  refresh every 15 s but exit after ~55 s; meant
 *                                              to be started by cron every minute, so it works
 *                                              on shared hosting that kills long processes
 *   bin/cake discord_live --feed=mys           only this feed (default feed is called "default")
 *   bin/cake discord_live --new                post fresh messages instead of editing the old ones
 */
class DiscordLiveCommand extends Command
{
    public static function defaultName(): string
    {
        return 'discord_live';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Post / refresh the live server status message(s) in Discord')
            ->addOption('every', [
                'help' => 'Keep running and refresh every N seconds (default: run once)',
                'default' => null,
            ])
            ->addOption('for', [
                'help' => 'With --every: exit after N seconds, for cron-restarted runs',
                'default' => null,
            ])
            ->addOption('feed', [
                'help' => 'Only update this feed ("default" or a key of Ladder.discord.feeds)',
                'default' => null,
            ])
            ->addOption('new', [
                'help' => 'Post new messages instead of editing the remembered ones',
                'boolean' => true,
            ]);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $every = (int)($args->getOption('every') ?? 0);
        $for = (int)($args->getOption('for') ?? 0);
        $forceNew = (bool)$args->getOption('new');
        $only = $args->getOption('feed');

        // Looping runs hold a lock so overlapping cron starts (or a leftover
        // screen session) never edit the same webhook messages concurrently.
        if ($every > 0) {
            $lock = fopen(TMP . 'discord_live_' . ($only ?? 'all') . '.lock', 'c');
            if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                $io->out('another discord_live run is active - exiting');

                return self::CODE_SUCCESS;
            }
        }

        $services = [];
        foreach ($this->feedConfigs() as $name => $cfg) {
            if ($only !== null && $only !== $name) {
                continue;
            }
            if (empty($cfg['webhook'])) {
                if ($only === $name) {
                    $io->err("Feed '{$name}' has no webhook configured");
                    return self::CODE_ERROR;
                }
                continue;
            }
            $services[$name] = new DiscordLiveService($cfg);
        }
        if (!$services) {
            $io->err($only !== null ? "Unknown feed '{$only}'" : 'No feed has a webhook configured');
            return self::CODE_ERROR;
        }

        $deadline = ($every > 0 && $for > 0) ? time() + $for : null;
        do {
            $failed = false;
            foreach ($services as $name => $service) {
                try {
                    $r = $service->update($forceNew);
                    $io->out(sprintf(
                        '[%s] %s: %s message %s – %d online, %d players',
                        date('H:i:s'), $name, $r['action'], $r['message_id'], $r['online'], $r['players']
                    ));
                } catch (Throwable $e) {
                    $io->err('[' . date('H:i:s') . "] {$name}: " . $e->getMessage());
                    $failed = true;
                }
            }
            $forceNew = false;
            if ($every > 0) {
                if ($deadline !== null && time() + $every >= $deadline) {
                    break;
                }
                sleep($every);
            }
        } while ($every > 0);

        return $failed ? self::CODE_ERROR : self::CODE_SUCCESS;
    }

    /**
     * Default feed + Ladder.discord.feeds, each merged over the defaults.
     *
     * @return array<string, array>
     */
    private function feedConfigs(): array
    {
        $base = Configure::read('Ladder.discord') ?: [];
        $feeds = (array)($base['feeds'] ?? []);
        unset($base['feeds']);

        $configs = ['default' => $base];
        foreach ($feeds as $name => $feed) {
            $cfg = (array)$feed + $base;
            // never share the default feed's state file
            if (empty($feed['state'])) {
                $cfg['state'] = TMP . 'discord_live_' . $name . '.json';
            }
            $configs[$name] = $cfg;
        }

        return $configs;
    }
}
