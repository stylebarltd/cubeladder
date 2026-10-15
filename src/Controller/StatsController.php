<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\CommunityStatsService;

/**
 * /stats - the community in numbers: where the front page's player count comes
 * from and how many of them play regularly.
 */
class StatsController extends AppController
{
    public function index()
    {
        $this->set('stats', (new CommunityStatsService())->get());
    }
}
