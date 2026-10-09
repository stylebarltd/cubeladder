<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;

/**
 * Messages Controller
 *
 * @property \App\Model\Table\MessagesTable $Messages
 */
class MessagesController extends AppController
{
    /** Contact-form messages to the admin per IP and hour */
    private const ADMIN_MESSAGES_PER_HOUR = 3;

    /**
     * Logged-in player who may read / send private messages: identity and
     * the player's own IP (ownsPlayer), else a 403.
     */
    private function messagingPlayer(): \App\Model\Entity\Player
    {
        $player = $this->request->getAttribute('identity');
        if (!$this->ownsPlayer($player)) {
            throw new ForbiddenException('Messages are only available from the IP you play from.');
        }

        return $player;
    }

    public function delete($id)
    {
        $this->request->allowMethod(['post']);

        $player = $this->messagingPlayer();
        $message = $this->Messages->get($id);

        if ($message->receiver_id !== $player->id) {
            throw new ForbiddenException("Not your message.");
        }

        $this->Messages->delete($message);

        $this->Flash->success('Message deleted.');
        return $this->redirect(['action' => 'inbox']);
    }

    public function inbox()
    {
        $player = $this->messagingPlayer();

        $messages = $this->Messages
            ->find()
            ->where(['receiver_id' => $player->id])
            ->contain(['Senders'])
            ->order(['Messages.created' => 'DESC'])
            ->all()
            ->toList();

        // shown as "new" this time, read from now on
        $this->Messages->updateAll(['is_read' => 1], ['receiver_id' => $player->id, 'is_read' => 0]);

        $this->set(compact('messages'));
    }
    public function sendToAdmin()
    {
        $receiverId = ((array)Configure::read('Ladder.admins'))[0] ?? null;
        if (!$receiverId) {
            $this->Flash->error('No admin configured.');
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'about']);
        }

        // known player (own IP) or an anonymous visitor (no sender)
        $sender = $this->request->getAttribute('identity');
        $senderId = $this->ownsPlayer($sender) ? $sender->id : null;

        $receiver = $this->Messages->Receivers->get($receiverId);
        $message  = $this->Messages->newEmptyEntity();

        if (!$this->request->is('post')) {
            $this->set(compact('receiver', 'message'));
            return;
        }

        // rate limit per IP (cache 'default')
        $rateKey = 'admin_msg_' . md5((string)$this->request->clientIp() . date('YmdH'));
        $sent = (int)Cache::read($rateKey);
        if ($sent >= self::ADMIN_MESSAGES_PER_HOUR) {
            $this->Flash->error('Too many messages - please try again in an hour.');
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'about']);
        }

        // Inbox limit
        $inboxCount = $this->Messages->find()
            ->where(['receiver_id' => $receiverId])
            ->count();

        if ($inboxCount >= 200) {
            $this->Flash->error("Inbox full. {$receiver->name} cannot receive more messages.");
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'about']);
        }

        $message = $this->Messages->patchEntity($message, $this->request->getData(), ['fields' => ['body']]);
        $message->sender_id   = $senderId;
        $message->receiver_id = $receiverId;
        $message->is_read     = 0;
        if ($this->Messages->save($message)) {
            Cache::write($rateKey, $sent + 1);
            $this->Flash->success("Message sent to admin.");
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'about']);
        }

        $this->Flash->error('Could not send message to admin.');
        $this->set(compact('receiver', 'message'));
    }

    public function send(?string $receiverId = null)
    {


        if (!$receiverId) {
            $this->Flash->error('Receiver not specified.');
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
        }

        $sender = $this->request->getAttribute('identity');

        if (!$this->ownsPlayer($sender)) {
            $this->Flash->error('You must be a registered player, on the IP you play from, to send messages.');
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
        }

        if ($sender->id === $receiverId) {
            $this->Flash->error('You cannot send a message to yourself.');
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
        }

        $receiver = $this->Messages->Receivers->get($receiverId);
        $message  = $this->Messages->newEmptyEntity();

        if (!$this->request->is('post')) {
            $this->set(compact('receiver', 'message'));
            return;
        }

        // Inbox limit
        $inboxCount = $this->Messages->find()
            ->where(['receiver_id' => $receiverId])
            ->count();

        if ($inboxCount >= 100) {
            $this->Flash->error("Inbox full. {$receiver->name} cannot receive more messages.");
            return $this->redirect(['controller' => 'Players', 'action' => 'index']);
        }

        $message = $this->Messages->patchEntity($message, $this->request->getData(), ['fields' => ['body']]);
        $message->sender_id   = $sender->id;
        $message->receiver_id = $receiverId;
        $message->is_read     = 0;

        if ($this->Messages->save($message)) {
            $this->Flash->success("Message sent to {$receiver->name}!");
            return $this->redirect(['controller' => 'Players', 'action' => 'view', $receiverId]);
        }

        $this->Flash->error('Could not send message.');
        $this->set(compact('receiver', 'message'));
    }
}
