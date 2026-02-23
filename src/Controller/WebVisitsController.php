<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\ForbiddenException;

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
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
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
     *
     * @param string|null $id Web Visit id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $webVisit = $this->WebVisits->get($id, contain: ['Players']);
        $this->set(compact('webVisit'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
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
     *
     * @param string|null $id Web Visit id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
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
     *
     * @param string|null $id Web Visit id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
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
