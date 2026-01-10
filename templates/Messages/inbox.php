<main class="flex-1 mx-auto max-w-5xl px-6 py-14 text-white">

    <!-- Hero / Title -->
    <h1 class="text-4xl font-bold mb-10 text-center text-green-400 animate-pulse">Inbox</h1>

    <?php if (empty($messages)): ?>
        <p>No messages.</p>
    <?php else: ?>
        <table class="border-separate border-spacing-2 border-spacing-x-4 border border-gray-400 dark:border-gray-500">
            <thead>
            <tr>
                <th class="border border-gray-300 dark:border-gray-600 p-5">From</th>
                <th class="border border-gray-300 dark:border-gray-600 p-5">Message</th>
                <th class="border border-gray-300 dark:border-gray-600 p-5">Date</th>
                <th class="border border-gray-300 dark:border-gray-600 p-5">Delete</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $msg): ?>
                <tr class="<?= $msg->is_read ? '' : 'fw-bold' ?>">
                    <td class="border border-gray-300 dark:border-gray-700 p-5">
                        <?= $this->Html->link(
                            h($msg->sender->name),
                            ['controller' => 'Players', 'action' => 'view', $msg->sender->id]
                        ) ?> <?= $this->Layout->flag($msg->sender->country) ?>
                    </td>

                    <td class="border border-gray-300 dark:border-gray-700 p-5">
                        <?= $this->Html->link(
                            h($msg->subject),
                            ['action' => 'view', $msg->id]
                        ) ?>
                        <p><?= nl2br(h($msg->body)) ?></p>
                    </td>

                    <td class="border border-gray-300 dark:border-gray-700 p-5"><?= $msg->created->format('Y-m-d H:i') ?></td>

                    <td class="border border-gray-300 dark:border-gray-700 p-5">
                        <?= $this->Form->postLink(
                            'Delete',
                            ['action' => 'delete', $msg->id],
                            ['class' => 'btn btn-sm btn-danger', 'confirm' => 'Delete this message?']
                        ) ?>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</main>
