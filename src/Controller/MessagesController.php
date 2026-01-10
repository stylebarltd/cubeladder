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
//    public function view($id)
//    {
//        $player = $this->authPlayer();
//
//        $message = $this->Messages->find()
//            ->where(['Messages.id' => $id, 'receiver_id' => $player->id])
//            ->contain(['Senders'])
//            ->firstOrFail();
//
//        // mark as read
//        $message->is_read = 1;
//        $this->Messages->save($message);
//
//        // mark notification as seen
//        $notification = $this->Messages->Notifications->find()
//            ->where(['message_id' => $id])
//            ->first();
//
//        if ($notification) {
//            $notification->is_seen = 1;
//            $this->Messages->Notifications->save($notification);
//        }
//
//        $this->set(compact('message'));
//    }
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

    public function send($receiverId = null)
    {
        $player = $this->authPlayer();

        if (!$sender) {
            throw new ForbiddenException("You must be a registered player (IP match) to send messages.");
        }

        if (!$receiverId) {
            throw new BadRequestException("Receiver not specified.");
        }

        if ($sender->id == $receiverId) {
            throw new ForbiddenException("You cannot send a message to yourself.");
        }

        $receiver = $this->Messages->Receivers->get($receiverId);

        $message = $this->Messages->newEmptyEntity();

        if ($this->request->is('post')) {

            $message = $this->Messages->patchEntity($message, $this->request->getData());

            $message->sender_id = $sender->id;
            $message->receiver_id = $receiverId;
            $message->is_read = 0;

            if ($this->Messages->save($message)) {

                // OPTIONAL: Create a notification entry for receiver
                $notificationTable = $this->fetchTable('Notifications');
                $notificationTable->save(
                    $notificationTable->newEntity([
                        'player_id' => $receiverId,
                        'message_id' => $message->id,
                        'is_seen' => 0
                    ])
                );

                $this->Flash->success("Message sent to {$receiver->name}!");
                return $this->redirect(['controller' => 'Players', 'action' => 'view', $receiverId]);
            }

            $this->Flash->error("Could not send message.");
        }

        $this->set(compact('receiver', 'message'));
    }


}
