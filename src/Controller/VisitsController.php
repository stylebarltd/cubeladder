<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\SiteVisits;

/**
 * The page-view ping of the layout JS (SiteVisits): /visits/hit?p=<path>&r=<referrer>
 */
class VisitsController extends AppController
{
    public function hit()
    {
        $this->request->allowMethod(['get']);
        $page = SiteVisits::page((string)$this->request->getQuery('p', ''));
        if ($page !== null && !$this->isAdmin()) {
            (new SiteVisits())->hit(
                $page,
                substr((string)$this->request->getQuery('r', ''), 0, 500),
                (string)$this->request->host(),
                $this->request->getAttribute('identity')?->id,
                (string)$this->request->clientIp(),
                substr((string)$this->request->getHeaderLine('User-Agent'), 0, 500)
            );
        }

        return $this->response->withStatus(204)->withHeader('Cache-Control', 'no-store');
    }
}
