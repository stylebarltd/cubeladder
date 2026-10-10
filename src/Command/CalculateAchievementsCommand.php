<?php
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Http\Client;
use DateTimeImmutable;
use App\Service\LastGamesTrait;
use Throwable;

/**
 * Weekly achievements (cron, Monday 00:01, for the week that just ended):
 * the #1 of each category over the last 100 games and the best player on
 * every map, stored in achievements, sent to the winners' inbox and posted
 * to the Discord achievements channel (Ladder.discord.achievements.webhook).
 *
 *   bin/cake CalculateAchievements              calculate, store, post
 *   bin/cake CalculateAchievements --post-only  post the stored week again
 */
class CalculateAchievementsCommand extends Command
{

    use LastGamesTrait;

    /** Discord labels of the weekly categories */
    private const LABELS = [
        'total_score' => ['🏆', 'Most points'],
        'kd_ratio' => ['🎯', 'Best K/D'],
        'scored_with_the_flag' => ['🚩', 'Most flags scored'],
        'headshot' => ['💀', 'Most headshots'],
        'slashed' => ['🔪', 'Most slashes'],
        'gibbed' => ['💣', 'Most gibs'],
        'teamkills' => ['🤦', 'Most teamkills'],
        'suicided' => ['☠️', 'Most suicides'],
    ];

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Weekly achievements: calculate, store, notify, post to Discord')
            ->addOption('post-only', ['boolean' => true, 'help' => 'Only post the stored achievements of the last week to Discord']);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if ($args->getOption('post-only')) {
            [$weekStart, $weekEnd] = $this->week();
            $this->postToDiscord($weekStart, $weekEnd, $io);

            return Command::CODE_SUCCESS;
        }
        $saved = 0;

        $Messages = $this->fetchTable('Messages');


        $Games        = $this->fetchTable('Games');
        $PlayerStats  = $this->fetchTable('PlayerStatsPerGame');
        $Achievements = $this->fetchTable('Achievements');
        $Maps         = $this->fetchTable('Maps');

        [$weekStart, $weekEnd] = $this->week();

        /**
         * Last 100 games
         */

        $theLast100GameIds = $this->getLastGameIds();

        if (!$theLast100GameIds) {
            $io->err('No games found.');
            return Command::SUCCESS;
        }

        /**
         * Metrics definition (logic only)
         */
        $metrics = [
            'kd_ratio' => 'kd_ratio',
            'headshot' => 'headshot',
            'scored_with_the_flag' => 'scored_with_the_flag',
            'slashed' => 'slashed',
            'gibbed' => 'gibbed',
            'suicided' => 'suicided',
            'teamkills' => 'teamkills',
            'total_score' => 'total_score',
        ];

        foreach ($metrics as $eventType => $field) {

            $value = $PlayerStats->find()->func()->sum($field);

            if($eventType=='kd_ratio'){
                $value = $PlayerStats->find()->func()->avg($field);
            }

            $where = [
                'game_id IN' => $theLast100GameIds,
                'Players.name IS NOT' => "unarmed",
            ];
            if ($eventType == 'kd_ratio') {
                // average K/D: games under 3 minutes would skew it
                $where[] = ['OR' => [
                    'PlayerStatsPerGame.minutes_played IS' => null,
                    'PlayerStatsPerGame.minutes_played >=' => \App\Model\Table\PlayerStatsPerGameTable::MIN_MINUTES,
                ]];
            }

            $row = $PlayerStats->find()
                ->select([
                    'player_id',
                    'value' => $value,
//                    'total_deaths',
//                    'total_kills',
                    'Players.name'
                ])
                ->where($where)
                ->group('player_id')
                ->contain(['Players'])
                ->orderDesc('value')
                ->limit(1)
                ->enableHydration(false)
                ->first();



            if (!$row) {
                //dd($row);
                continue;
            }


           // dd($row);
            /**
             * Prevent duplicate weekly entries
             */
            if ($Achievements->exists([
                'week_start' => $weekStart->format('Y-m-d'),
                'event_type' => $eventType
            ])) {
                continue;
            }

            $entity = $Achievements->newEntity([
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end'   => $weekEnd->format('Y-m-d'),
                'player_id'  => $row['player_id'],
                'event_type' => $eventType,
                'count'      => $row['value'],
                'created'    => (new DateTimeImmutable())->format('Y-m-d H:i:s')
            ]);

            $Achievements->saveOrFail($entity);
            $saved++;

            $this->sendAchievementMessage(
                $Messages,
                $row['player_id'],
                'Weekly Achievement Unlocked!',
                sprintf(
                    "You ranked #1 for **%s** this week with a value of **%s**.\n\nGreat job! 🏆",
                    str_replace('_', ' ', $eventType),
                    $row['value']
                )
            );


            $io->out(sprintf(
                '✔ %s → player %d (%s)',
                $eventType,
                $row['player_id'],
                $row['value']
            ));
        }




        /**
         * Best player per map (last 100 games)
         */
        $maps = $Maps->find()
            ->matching('Games', function ($q) use ($theLast100GameIds) {
                return $q->where([
                    'Games.id IN' => $theLast100GameIds
                ]);
            })
            ->distinct(['Maps.id'])
            ->select([
                'Maps.id',
                'Maps.name'
            ])
            ->enableHydration(false)
            ->toArray();

        foreach ($maps as $map) {

            $row = $PlayerStats->find()
                ->select([
                    'player_id',
                    'value' => $PlayerStats->find()->func()->max('total_score'),
                    'Players.name'
                ])
                ->matching('Games', function ($q) use ($theLast100GameIds, $map) {
                    return $q->where([
                        'Games.id IN' => $theLast100GameIds,
                        'Games.map_id' => $map['id']
                    ]);
                })
                ->where([
                    'Players.name IS NOT' => 'unarmed'
                ])
                ->group('player_id')
                ->contain(['Players'])
                ->orderDesc('value')
                ->limit(1)
                ->enableHydration(false)
                ->first();

            if (!$row) {
                continue;
            }

            /**
             * Prevent duplicate weekly entries per map
             */
            if ($Achievements->exists([
                'week_start' => $weekStart->format('Y-m-d'),
                'event_type' => 'best_on_map',
                'map_id'     => $map['id']
            ])) {
                continue;
            }

            $entity = $Achievements->newEntity([
                'week_start' => $weekStart->format('Y-m-d'),
                'week_end'   => $weekEnd->format('Y-m-d'),
                'player_id'  => $row['player_id'],
                'map_id'     => $map['id'],
                'event_type' => 'best_on_map',
                'count'      => $row['value'],
                'created'    => (new DateTimeImmutable())->format('Y-m-d H:i:s')
            ]);

            $Achievements->saveOrFail($entity);
            $saved++;

            $this->sendAchievementMessage(
                $Messages,
                $row['player_id'],
                'Map Champion!',
                sprintf(
                    "You were the **top player on %s** this week with a score of **%s**.\n\nDominating! 💥",
                    $map['name'],
                    $row['value']
                )
            );


            $io->out(sprintf(
                '✔ best_on_map → %s → player %d (%s)',
                $map['name'],
                $row['player_id'],
                $row['value']
            ));
        }

        // the week's achievements in the Discord channel (once: only when
        // this run stored new ones)
        if ($saved > 0) {
            $this->postToDiscord($weekStart, $weekEnd, $io);
        }

        return Command::CODE_SUCCESS;
    }

    /**
     * The week ending last Sunday (today on a Sunday).
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function week(): array
    {
        $today = new DateTimeImmutable('today');
        $weekEnd = ($today->format('w') === '0') ? $today : $today->modify('last sunday');

        return [$weekEnd->modify('-6 days'), $weekEnd];
    }

    /**
     * One Discord message with the stored achievements of the week: the
     * category winners, then the map champions. Players who opted out of
     * tracking are shown as Anonymous, without a link.
     */
    private function postToDiscord(DateTimeImmutable $weekStart, DateTimeImmutable $weekEnd, ConsoleIo $io): void
    {
        $webhook = Configure::read('Ladder.discord.achievements.webhook');
        if (empty($webhook)) {
            $io->out('No achievements webhook configured (Ladder.discord.achievements.webhook) - not posted');

            return;
        }
        $site = rtrim((string)(Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        $rows = $this->fetchTable('Achievements')->find()
            ->contain(['Players', 'Maps'])
            ->where(['Achievements.week_start' => $weekStart->format('Y-m-d')])
            ->all();
        if ($rows->isEmpty()) {
            $io->out('No achievements stored for the week of ' . $weekStart->format('Y-m-d'));

            return;
        }

        $who = function ($a) use ($site): string {
            if (!$a->player || (int)$a->player->track !== 1) {
                return '_Anonymous_';
            }
            $name = strtr((string)$a->player->name, ['[' => '(', ']' => ')', '*' => "\u{2217}", '`' => "\u{2CB}"]);

            return sprintf('[%s](%s/players/view/%s)', $name, $site, $a->player->id);
        };
        $value = fn($a) => $a->event_type === 'kd_ratio'
            ? number_format((float)$a->count, 2)
            : number_format((float)$a->count);

        $fields = [];
        foreach (self::LABELS as $type => [$emoji, $label]) {
            foreach ($rows as $a) {
                if ($a->event_type === $type) {
                    $fields[] = ['name' => $emoji . ' ' . $label, 'value' => $who($a) . ' · **' . $value($a) . '**', 'inline' => true];
                }
            }
        }

        $maps = array_values(array_filter($rows->toList(), fn($a) => $a->event_type === 'best_on_map' && $a->map));
        usort($maps, fn($x, $y) => (float)$y->count <=> (float)$x->count);
        $lines = [];
        $length = 0;
        foreach ($maps as $i => $a) {
            $line = sprintf('**%s** · %s · %s pts', $a->map->name, $who($a), $value($a));
            if ($length + strlen($line) > 3800) {
                $lines[] = sprintf('… and %d more maps', count($maps) - $i);
                break;
            }
            $lines[] = $line;
            $length += strlen($line) + 1;
        }

        $period = $weekStart->format('j M') . ' – ' . $weekEnd->format('j M Y');
        $embeds = [[
            'title' => '🥇 Weekly achievements · ' . $period,
            'url' => $site . '/players',
            'description' => 'The best of the week over the last 100 games.',
            'color' => 0xEAB308,
            'fields' => $fields,
        ]];
        if ($lines) {
            $embeds[] = [
                'title' => '🗺️ Best on map · ' . count($maps) . ' maps',
                'url' => $site . '/maps',
                'description' => implode("\n", $lines),
                'color' => 0x3B82F6,
            ];
        }

        try {
            $res = (new Client(['timeout' => 15]))->post($webhook . '?wait=true', (string)json_encode([
                'username' => 'cubeLadder Achievements',
                'avatar_url' => $site . '/img/brand/cubeladder-discord-icon-512.png',
                'embeds' => $embeds,
                'allowed_mentions' => ['parse' => []],
                'flags' => 4096, // SUPPRESS_NOTIFICATIONS: no sound in the achievements channel
            ]), ['type' => 'json']);
            $io->out($res->isOk() ? 'Posted the week to Discord' : 'Discord: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());
        } catch (Throwable $e) {
            $io->err('Discord: ' . $e->getMessage());
        }
    }


    private function sendAchievementMessage(
        \Cake\ORM\Table $Messages,
        string $receiverId,
        string $title,
        string $body
    ): void {
        $message = $Messages->newEntity([
            'sender_id'   => ((array)\Cake\Core\Configure::read('Ladder.admins'))[0] ?? null,
            'receiver_id' => $receiverId,
            'body'        => "**{$title}**\n\n{$body}",
            'is_read'     => 0,
            'created'     => new DateTimeImmutable()
        ]);

        $Messages->save($message);
    }


}
