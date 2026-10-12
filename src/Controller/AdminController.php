<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\DuplicateFinder;
use App\Service\PlayerMerger;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Routing\Router;

/**
 * Admin area: merge requests of players, likely duplicate profiles, the
 * messages to the admin and merging two profiles by hand.
 *
 * Login with the admin password (bin/cake admin_password sets it), or as a
 * site admin's player on that player's IP (AppController::isAdmin).
 */
class AdminController extends AppController
{
    /** failed logins per IP and 15 minutes */
    private const MAX_LOGIN_TRIES = 5;

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        if ($this->request->getParam('action') !== 'login' && !$this->isAdmin()) {
            return $this->redirect(['action' => 'login']);
        }
        // the duplicates form has a checkbox and buttons per row (CSRF still applies)
        $this->FormProtection->setConfig('unlockedActions', ['pair']);
    }

    /** bcrypt hash of the admin password: config/admin_password.php (bin/cake admin_password) */
    public static function passwordHash(): ?string
    {
        $file = CONFIG . 'admin_password.php';
        $hash = is_file($file) ? include $file : Configure::read('Ladder.adminPasswordHash');

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function login()
    {
        if ($this->isAdmin()) {
            return $this->redirect(['action' => 'index']);
        }
        $hash = self::passwordHash();
        $this->set('noPassword', $hash === null);
        if (!$this->request->is('post') || $hash === null) {
            return;
        }

        $key = 'admin_login_' . md5((string)$this->request->clientIp() . floor(time() / 900));
        $tries = (int)Cache::read($key);
        if ($tries >= self::MAX_LOGIN_TRIES) {
            $this->Flash->error('Too many tries - wait 15 minutes.');

            return;
        }
        if (!password_verify((string)$this->request->getData('password'), $hash)) {
            Cache::write($key, $tries + 1);
            sleep(1);
            $this->Flash->error('Wrong password.');

            return;
        }

        $session = $this->request->getSession();
        $session->renew();
        $session->write('Admin.ok', true);

        return $this->redirect(['action' => 'index']);
    }

    public function logout()
    {
        $this->request->allowMethod(['post']);
        $this->request->getSession()->delete('Admin');

        return $this->redirect('/');
    }

    /** Open merge requests, and the last merges */
    public function index()
    {
        $requests = $this->fetchTable('MergeRequests')->find()
            ->where(['status IN' => ['pending', 'asked']])
            ->orderBy(['status' => 'DESC', 'created' => 'DESC'])
            ->all()->toList();
        $merges = $this->fetchTable('PlayerMerges')->find()->orderBy(['merged_at' => 'DESC'])->limit(15)->all()->toList();

        $merger = new PlayerMerger();
        $profiles = [];
        foreach ($requests as $r) {
            $profiles[$r->from_id] ??= $merger->profile($r->from_id);
            $profiles[$r->to_id] ??= $merger->profile($r->to_id);
        }
        foreach ($merges as $m) {
            $profiles[$m->to_id] ??= $merger->profile($m->to_id);
        }

        $this->set(compact('requests', 'merges', 'profiles'));
        $this->set('tab', 'requests');
    }

    /** Likely duplicate profiles (DuplicateFinder) */
    public function duplicates()
    {
        $this->set('candidates', (new DuplicateFinder())->candidates(150));
        $this->set('tab', 'duplicates');
    }

    /** Messages to the admin (send-to-admin form and replies), with reply */
    public function messages()
    {
        $adminId = $this->adminPlayerId();
        $messages = $adminId ? $this->fetchTable('Messages')->find()
            ->where(['receiver_id' => $adminId])
            ->contain(['Senders'])
            ->orderBy(['Messages.created' => 'DESC'])
            ->limit(100)->all()->toList() : [];
        if ($adminId) {
            $this->fetchTable('Messages')->updateAll(['is_read' => 1], ['receiver_id' => $adminId, 'is_read' => 0]);
        }
        $this->set(compact('messages'));
        $this->set('tab', 'messages');
    }

    public function reply(int $id)
    {
        $this->request->allowMethod(['post']);
        $Messages = $this->fetchTable('Messages');
        $msg = $Messages->get($id);
        $body = trim((string)$this->request->getData('body'));
        if ($msg->sender_id === null || $body === '') {
            $this->Flash->error($msg->sender_id === null ? 'Anonymous message - no one to reply to.' : 'Empty reply.');
        } else {
            $this->sendMessage($msg->sender_id, $body);
            $this->Flash->success('Reply sent.');
        }

        return $this->redirect(['action' => 'messages']);
    }

    /**
     * Merge two profiles by hand: ?from= (goes away) &to= (stays), each an
     * id, a profile link or an exact name. GET previews, POST merges.
     */
    public function merge()
    {
        $merger = new PlayerMerger();
        $fromInput = trim((string)($this->request->getData('from') ?? $this->request->getQuery('from', '')));
        $toInput = trim((string)($this->request->getData('to') ?? $this->request->getQuery('to', '')));
        $from = $fromInput !== '' ? $this->resolve($fromInput) : null;
        $to = $toInput !== '' ? $this->resolve($toInput) : null;

        $error = null;
        if ($fromInput !== '' && $from === null) {
            $error = "No single profile found for \"$fromInput\" - use the id or the profile link.";
        } elseif ($toInput !== '' && $to === null) {
            $error = "No single profile found for \"$toInput\" - use the id or the profile link.";
        } elseif ($from && $to && $from === $to) {
            $error = 'That is the same profile twice.';
        }

        if ($this->request->is('post') && $from && $to && !$error) {
            $games = $merger->merge($to, [$from], 'admin');
            $this->Flash->success(sprintf('Merged: %d games moved. Ratings update with the next import (10 min).', $games));

            return $this->redirect(['controller' => 'Players', 'action' => 'view', $to]);
        }

        $this->set([
            'fromInput' => $fromInput, 'toInput' => $toInput, 'error' => $error,
            'from' => $from ? $merger->profile($from) : null,
            'to' => $to ? $merger->profile($to) : null,
            'tab' => 'merge',
        ]);
    }

    /** Merge a request (from -> to) and tell the player */
    public function approve(int $id)
    {
        $this->request->allowMethod(['post']);
        $r = $this->fetchTable('MergeRequests')->get($id);
        $merger = new PlayerMerger();
        if (!$merger->profile($r->from_id) || !$merger->profile($r->to_id)) {
            $this->closeRequest($r, 'rejected');
            $this->Flash->error('One of the profiles no longer exists - request closed.');

            return $this->redirect(['action' => 'index']);
        }
        $old = $merger->profile($r->from_id);
        $games = $merger->merge($r->to_id, [$r->from_id], $r->requested_by ? 'request' : 'admin');
        $this->sendMessage($r->to_id, sprintf("**Profiles merged**\nYour old profile %s is now part of this one: %d games, with all points and awards. Thanks for telling us!",
            $old['name'], $games));
        $this->Flash->success(sprintf('Merged %s into the newer profile (%d games).', $old['name'], $games));

        return $this->redirect(['action' => 'index']);
    }

    public function reject(int $id)
    {
        $this->request->allowMethod(['post']);
        $this->closeRequest($this->fetchTable('MergeRequests')->get($id), 'rejected');
        $this->Flash->success('Request closed - the pair is not suggested again.');

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Duplicates page actions on one pair: merge it now, ask the player
     * (a message with a confirm link) or dismiss it (not suggested again).
     * Also for several pairs at once: pairs[] = "old|new".
     */
    public function pair()
    {
        $this->request->allowMethod(['post']);
        $do = (string)$this->request->getData('do');
        $pairs = (array)($this->request->getData('pairs') ?? []);
        // a button of one row: "action|old|new"
        if ($this->request->getData('single')) {
            [$do, $pair] = array_pad(explode('|', (string)$this->request->getData('single'), 2), 2, '');
            $pairs = [$pair];
        }
        $merger = new PlayerMerger();
        $Requests = $this->fetchTable('MergeRequests');
        $count = 0;
        foreach ($pairs as $pair) {
            [$oldId, $newId] = array_pad(explode('|', (string)$pair, 2), 2, '');
            $old = $merger->profile($oldId);
            $new = $merger->profile($newId);
            if (!$old || !$new || $oldId === $newId) {
                continue;
            }
            if ($do === 'merge') {
                $merger->merge($newId, [$oldId], 'admin');
            } elseif ($do === 'ask') {
                $this->sendMessage($newId, $this->askText($old));
                $Requests->save($Requests->newEntity(['from_id' => $oldId, 'to_id' => $newId, 'status' => 'asked'], ['accessibleFields' => ['*' => true]]));
            } elseif ($do === 'dismiss') {
                $Requests->save($Requests->newEntity(['from_id' => $oldId, 'to_id' => $newId, 'status' => 'dismissed',
                    'resolved_at' => date('Y-m-d H:i:s')], ['accessibleFields' => ['*' => true]]));
            } else {
                continue;
            }
            $count++;
        }
        $label = ['merge' => 'merged', 'ask' => 'asked by message', 'dismiss' => 'dismissed'][$do] ?? 'done';
        $this->Flash->success(sprintf('%d pair%s %s.', $count, $count === 1 ? '' : 's', $label));

        return $this->redirect(['action' => 'duplicates']);
    }

    /** The question to the player behind the newer profile */
    private function askText(array $old): string
    {
        $url = Router::url(['controller' => 'Players', 'action' => 'claim', $old['id']], true);
        $seen = $old['last_seen'] ? date('j M Y', strtotime((string)$old['last_seen'])) : 'a while ago';

        return sprintf("**Is this your old profile?**\nIt looks like you also played as %s (%d games, last seen %s).\n"
            . "If that was you, open this link and confirm - we then merge it into this profile, with all games, points and awards:\n%s\n"
            . "If it wasn't you, just ignore this message.", $old['name'], $old['games'], $seen, $url);
    }

    private function closeRequest($request, string $status): void
    {
        $request->status = $status;
        $request->resolved_at = date('Y-m-d H:i:s');
        $this->fetchTable('MergeRequests')->save($request);
    }

    private function adminPlayerId(): ?string
    {
        return ((array)Configure::read('Ladder.admins'))[0] ?? null;
    }

    /** A message from the admin (the first Ladder.admins player) */
    private function sendMessage(string $to, string $body): void
    {
        $Messages = $this->fetchTable('Messages');
        $msg = $Messages->newEmptyEntity();
        $msg->sender_id = $this->adminPlayerId();
        $msg->receiver_id = $to;
        $msg->body = $body;
        $msg->is_read = 0;
        $Messages->saveOrFail($msg);
    }

    /** A player id from an id, an id prefix, a profile link or an exact (unique) name */
    private function resolve(string $input): ?string
    {
        if (preg_match('#/players/view/([0-9a-f-]{8,36})#i', $input, $m)) {
            $input = $m[1];
        }
        $Players = $this->fetchTable('Players');
        $ids = $Players->find()->select(['id'])->where(['id' => $input])->all()->extract('id')->toList();
        if (!$ids && preg_match('/^[0-9a-f-]{6,36}$/i', $input)) {
            $ids = $Players->find()->select(['id'])->where(['id LIKE' => $input . '%'])->limit(2)->all()->extract('id')->toList();
        }
        if (!$ids) {
            $ids = $Players->find()->select(['id'])->where(['name' => $input])->limit(2)->all()->extract('id')->toList();
        }

        return count($ids) === 1 ? $ids[0] : null;
    }
}
