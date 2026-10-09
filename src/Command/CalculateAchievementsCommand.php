<?php
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use DateTimeImmutable;
use App\Service\LastGamesTrait;

class CalculateAchievementsCommand extends Command
{

    use LastGamesTrait;

    public function execute(Arguments $args, ConsoleIo $io): int
    {

        $Messages = $this->fetchTable('Messages');


        $Games        = $this->fetchTable('Games');
        $PlayerStats  = $this->fetchTable('PlayerStatsPerGame');
        $Achievements = $this->fetchTable('Achievements');
        $Maps         = $this->fetchTable('Maps');

        /**
         * Week range (last completed Sunday)
         */
        $today = new DateTimeImmutable('today');

        $weekEnd = ($today->format('w') === '0')
            ? $today
            : $today->modify('last sunday');

        $weekStart = $weekEnd->modify('-6 days');

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

            $this->sendAchievementMessage(
                $Messages,
                $row['player_id'],
                'Weekly Achievement Unlocked!',
                sprintf(
                    'You ranked #1 for **%s** this week with a value of **%s**.\n\nGreat job! 🏆',
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

            $this->sendAchievementMessage(
                $Messages,
                $row['player_id'],
                'Map Champion!',
                sprintf(
                    'You were the **top player on %s** this week with a score of **%s**.\n\nDominating! 💥',
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

        return 1;
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
