<?php

namespace App\Service;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;

trait LastGamesTrait
{
    /**
     * Ids of the last Ladder.maxGamesToRank (100) counted games - "the last 100".
     */
    protected function getLastGameIds(?int $limit = null): array
    {
        $limit ??= (int)(Configure::read('Ladder.maxGamesToRank') ?: 100);
        $Games = TableRegistry::getTableLocator()->get('Games');

        $gameIds = $Games
            ->find()
            ->select(['id'])
            ->where(['inaccurate' => false])
            ->orderByDesc('ended_at')
            ->limit($limit)
            ->enableHydration(false)
            ->all()
            ->extract('id')
            ->toList();

        return $gameIds;
    }
    protected function getHallOfFameGameIds(): array
    {
        $Games = TableRegistry::getTableLocator()->get('Games');

        $gameIds = $Games
            ->find()
            ->select(['id'])
            ->where(['inaccurate' => false])
            ->orderByDesc('ended_at')
            ->where(['started_at >=' => \App\Controller\PlayersController::HOF_SINCE])
            ->enableHydration(false)
            ->all()
            ->extract('id')
            ->toList();

        return $gameIds;
    }
    protected function getThisYearGameIds(): array
    {
        $Games = TableRegistry::getTableLocator()->get('Games');

        $gameIds = $Games
            ->find()
            ->select(['id'])
            ->where(['inaccurate' => false])
            ->orderByDesc('ended_at')
            ->where(['started_at >=' => date('Y') . '-01-01'])
            ->enableHydration(false)
            ->all()
            ->extract('id')
            ->toList();

        return $gameIds;
    }

    protected function getGameDateRange(?int $limit = null): array
    {
        $limit ??= (int)(Configure::read('Ladder.maxGamesToRank') ?: 100);
        $Games = TableRegistry::getTableLocator()->get('Games');

        $games = $Games->find()
            ->select(['started_at', 'ended_at'])
            ->where(['inaccurate' => false])
            ->orderByDesc('ended_at')
            ->limit($limit)
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

