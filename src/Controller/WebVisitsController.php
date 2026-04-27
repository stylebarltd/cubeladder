<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;

/**
 * WebVisits Controller
 *
 * @property \App\Model\Table\WebVisitsTable $WebVisits
 */
class WebVisitsController extends AppController
{

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $allowedPlayerIds = [
            'ed947213-05f5-4030-a7bc-f1f67e5c5de8', //pola
            'c889c0bd-68fc-49ad-9a7d-44704b0a2267', //ketar
        ];

        $player = $this->request->getAttribute('identity');

        if (!$player || !in_array($player->id, $allowedPlayerIds)) {
            throw new ForbiddenException('Not allowed.');
        }
    }

    /**
     * Stats / dashboard action
     */
    public function stats()
    {
        $db = $this->WebVisits->getConnection();

        // --- Summary cards ---
        $total      = $this->WebVisits->find()->count();
        $bots       = $this->WebVisits->find()->where(['is_bot' => true])->count();
        $humans     = $total - $bots;
        $uniqueIPs  = $this->WebVisits->find()
            ->select(['cnt' => 'COUNT(DISTINCT ip_address)'])
            ->first()->cnt ?? 0;

        // --- Visits per day (last 30 days) ---
        $visitsPerDay = $db->execute(
            "SELECT DATE(created) AS day,
                    COUNT(*) AS total,
                    SUM(is_bot) AS bots
             FROM web_visits
             WHERE created >= NOW() - INTERVAL 30 DAY
             GROUP BY day
             ORDER BY day ASC"
        )->fetchAll('assoc');

        // --- Top paths ---
        $topPaths = $this->WebVisits->find()
            ->select([
                'path',
                'total'   => 'COUNT(*)',
                'bot_hits' => 'SUM(is_bot)',
            ])
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit(10)
            ->disableHydration()
            ->all()
            ->toList();

        // --- HTTP methods breakdown ---
        $methods = $this->WebVisits->find()
            ->select(['method', 'cnt' => 'COUNT(*)'])
            ->groupBy('method')
            ->orderByDesc('cnt')
            ->disableHydration()
            ->all()
            ->toList();

        // --- Top IPs (with player name + country from players table) ---
        $topIPs = $db->execute(
            "SELECT
                wv.ip_address,
                COUNT(*)                                    AS cnt,
                MAX(wv.is_bot)                              AS is_bot,
                MAX(wv.country_iso)                         AS country_iso,
                p.name                                      AS player_name,
                p.country                               AS player_country
             FROM web_visits wv
             LEFT JOIN players p ON p.id = (
                 SELECT player_id FROM web_visits
                 WHERE ip_address = wv.ip_address
                   AND player_id IS NOT NULL
                 LIMIT 1
             )
             GROUP BY wv.ip_address, p.name, p.country
             ORDER BY cnt DESC
             LIMIT 15"
        )->fetchAll('assoc');

        // --- Visits by country (top 15, human only) ---
        $byCountry = $db->execute(
            "SELECT
                COALESCE(country_iso, '??') AS country_iso,
                COUNT(*)                    AS cnt
             FROM web_visits
             WHERE is_bot = 0
               AND country_iso IS NOT NULL
               AND country_iso != ''
             GROUP BY country_iso
             ORDER BY cnt DESC
             LIMIT 15"
        )->fetchAll('assoc');

        // --- Hourly heatmap (hour of day × day of week) ---
        $heatmap = $db->execute(
            "SELECT HOUR(created) AS hour,
                    DAYOFWEEK(created) AS dow,
                    COUNT(*) AS cnt
             FROM web_visits
             GROUP BY hour, dow"
        )->fetchAll('assoc');

        $this->set(compact(
            'total', 'bots', 'humans', 'uniqueIPs',
            'visitsPerDay', 'topPaths', 'methods', 'topIPs', 'byCountry', 'heatmap'
        ));
    }

    /**
     * Index method
     */
    public function index()
    {
        $query = $this->WebVisits->find()
            ->contain(['Players'])->orderByDesc('WebVisits.created');
        $webVisits = $this->paginate($query);

        $this->set(compact('webVisits'));
    }

    /**
     * View method
     */
    public function view($id = null)
    {
        $webVisit = $this->WebVisits->get($id, contain: ['Players']);
        $this->set(compact('webVisit'));
    }

    /**
     * Add method
     */
    public function add()
    {
        $webVisit = $this->WebVisits->newEmptyEntity();
        if ($this->request->is('post')) {
            $webVisit = $this->WebVisits->patchEntity($webVisit, $this->request->getData());
            if ($this->WebVisits->save($webVisit)) {
                $this->Flash->success(__('The web visit has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The web visit could not be saved. Please, try again.'));
        }
        $players = $this->WebVisits->Players->find('list', limit: 200)->all();
        $this->set(compact('webVisit', 'players'));
    }

    /**
     * Edit method
     */
    public function edit($id = null)
    {
        $webVisit = $this->WebVisits->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $webVisit = $this->WebVisits->patchEntity($webVisit, $this->request->getData());
            if ($this->WebVisits->save($webVisit)) {
                $this->Flash->success(__('The web visit has been saved.'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The web visit could not be saved. Please, try again.'));
        }
        $players = $this->WebVisits->Players->find('list', limit: 200)->all();
        $this->set(compact('webVisit', 'players'));
    }

    /**
     * Delete method
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $webVisit = $this->WebVisits->get($id);
        if ($this->WebVisits->delete($webVisit)) {
            $this->Flash->success(__('The web visit has been deleted.'));
        } else {
            $this->Flash->error(__('The web visit could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
