<?php
declare(strict_types=1);

namespace App\Controller;

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

        $player = $this->authPlayer();

        if ($message->receiver_id !== $player->id) {
            throw new ForbiddenException("Not your message.");
        }

        $this->Messages->delete($message);

        $this->Flash->success('Message deleted.');
        return $this->redirect(['action' => 'inbox']);
    }

    public function inbox()
    {
        $player = $this->authPlayer();

        if (!$player) {
            throw new \Cake\Http\Exception\ForbiddenException("Login required (IP mismatch).");
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
    public function send(?string $receiverId = null)
    {
        $sender = $this->authPlayer();

        if (!$sender) {
            $this->Flash->error('You must be a registered player to send messages.');
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
        }

        if (!$receiverId) {
            $this->Flash->error('Receiver not specified.');
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
            return $this->redirect(['controller' => 'Players', 'action' => 'view', $receiverId]);
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

//    public function send($receiverId = null)
//    {
//        $player = $this->authPlayer();
//
//        if (!$player) {
//            $this->Flash->error("You must be a registered player (IP match) to send messages.");
//            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
//        }
//
//        if (!$receiverId) {
//            $this->Flash->error("Receiver not specified.");
//            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
//        }
//
//        if ($player->id == $receiverId) {
//            $this->Flash->error("You cannot send a message to yourself.");
//            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
//        }
//
//        $receiver = $this->Messages->Receivers->get($receiverId);
//
//        $message = $this->Messages->newEmptyEntity();
//
//        if ($this->request->is('post')) {
//
//            $message = $this->Messages->patchEntity($message, $this->request->getData());
//
//            $message->sender_id = $player->id;
//            $message->receiver_id = $receiverId;
//            $message->is_read = 0;
//
//            if ($this->Messages->save($message)) {
//                $this->Flash->success("Message sent to {$receiver->name}!");
//                return $this->redirect(['controller' => 'Players', 'action' => 'view', $receiverId]);
//            }
//
//            $this->Flash->error("Could not send message.");
//        }
//
//        $this->set(compact('receiver', 'message'));
//    }


}
