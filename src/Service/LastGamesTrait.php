<?php

namespace App\Service;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

trait LastGamesTrait
{
    protected function getLastGameIds(int $limit = 100): array
    {

        $Games = TableRegistry::getTableLocator()->get('Games');

        $maxGamesToRank = Configure::read('Ladder.maxGamesToRank');

        $lastGameIds = $Games
            ->find()
            ->select(['id'])
            ->orderByDesc('ended_at')
            ->limit($maxGamesToRank)
            ->enableHydration(false)
            ->all()
            ->extract('id')
            ->toList();

        return $lastGameIds;
    }
    protected function getGameDateRange(): array
    {
        $Games = TableRegistry::getTableLocator()->get('Games');

        $maxGamesToRank = Configure::read('Ladder.maxGamesToRank');

        $games = $Games->find()
            ->select(['started_at', 'ended_at'])
            ->orderDesc('ended_at')
            ->limit($maxGamesToRank)
            ->enableHydration(false)
            ->toArray();

        if (empty($games)) {
            return ['start' => null, 'end' => null];
        }

        // newest game (first row)
        $endDate = $games[0]['ended_at'];

        // oldest game within the last 100 (last row)
        $startDate = $games[count($games) - 1]['started_at'];

        return [
            'start' => date("D, dS M H:i",strtotime($startDate)),
            'end'   => date("D, dS M H:i",strtotime($endDate)),
        ];

    }

}

