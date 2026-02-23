<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Exception\ForbiddenException;

/**
 * Messages Controller
 *
 * @property \App\Model\Table\MessagesTable $Messages
 */
class MessagesController extends AppController
{

    public function delete($id)
    {
        $this->request->allowMethod(['post']);

        $message = $this->Messages->get($id);

        $player = $this->request->getAttribute('identity');

        if ($message->receiver_id !== $player->id) {
            throw new ForbiddenException("Not your message.");
        }

        $this->Messages->delete($message);

        $this->Flash->success('Message deleted.');
        return $this->redirect(['action' => 'inbox']);
    }

    public function inbox()
    {
        $player = $this->request->getAttribute('identity');

        if (!$player) {
            throw new ForbiddenException("Login required (IP mismatch).");
        }

        $messages = $this->Messages
            ->find()
            ->where(['receiver_id' => $player->id])
            ->contain(['Senders'])
            ->order(['Messages.created' => 'DESC']);
        //dd($messages->toArray());

        foreach ($messages as $message) {

            $message->is_read = 1;
            $this->Messages->save($message);

        }

        $this->set(compact('messages'));
    }
    public function sendToAdmin()
    {
$receiverId='ed947213-05f5-4030-a7bc-f1f67e5c5de8';


        $sender = $this->request->getAttribute('identity');
        $senderId = $receiverId;
        if ($sender) {
            $senderId = $sender->id;
        }

        $receiver = $this->Messages->Receivers->get($receiverId);
        //debug($receiver);
        $message  = $this->Messages->newEmptyEntity();

        if (!$this->request->is('post')) {
            $this->set(compact('receiver', 'message'));
            return;
        }

        // Inbox limit
        $inboxCount = $this->Messages->find()
            ->where(['receiver_id' => $receiverId])
            ->count();

        if ($inboxCount >= 200) {
            $this->Flash->error("Inbox full. {$receiver->name} cannot receive more messages.");
            return $this->redirect(['controller' => 'pages', 'action' => 'about']);
        }

        $message = $this->Messages->patchEntity($message, $this->request->getData());
        $message->sender_id   = $senderId;
        $message->receiver_id = $receiverId;
        $message->is_read     = 0;
        //dd($message);
        if ($this->Messages->save($message)) {
            $this->Flash->success("Message sent to admin.");
            return $this->redirect(['controller' => 'pages', 'action' => 'about']);
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

        if (!$sender) {
            $this->Flash->error('You must be a registered player to send messages.');
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

        $message = $this->Messages->patchEntity($message, $this->request->getData());
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
