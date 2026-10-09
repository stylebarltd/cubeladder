<main class="flex-1 mx-auto max-w-6xl px-6 py-14 text-white">

    <!-- Title -->
    <h1 class="text-4xl font-extrabold mb-12 text-center text-blue-500 tracking-wide">
        Inbox
    </h1>

    <?php if (empty($messages)): ?>
        <div class="text-center text-gray-400 italic">
            No messages yet.
        </div>
    <?php else: ?>

        <div class="overflow-hidden rounded-2xl shadow-xl border border-gray-700 bg-gray-900/60 backdrop-blur">
            <table class="min-w-full border-collapse">

                <!-- Header -->
                <thead class="bg-gray-800 text-gray-300 text-sm uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">From</th>
                    <th class="px-6 py-4 text-left">Message</th>
                    <th class="px-6 py-4 text-left">Date</th>
                    <th class="px-6 py-4 text-center">Action</th>
                </tr>
                </thead>

                <!-- Body -->
                <tbody class="divide-y divide-gray-700">
                <?php foreach ($messages as $msg): ?>
                    <tr class="
                        transition
                        hover:bg-gray-800/70
                        <?= $msg->is_read ? 'text-gray-300' : 'bg-gray-800/40 font-semibold' ?>
                    ">

                        <!-- Sender -->
                        <td class="px-6 py-5 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <?php if ($msg->sender): ?>
                                    <?= $this->Html->link(
                                        h($msg->sender->name),
                                        ['controller' => 'Players', 'action' => 'view', $msg->sender->id],
                                        ['class' => 'hover:text-blue-500 hover:underline']
                                    ) ?>
                                    <?= $this->Layout->flag($msg->sender->country) ?>
                                <?php else: ?>
                                    <span class="italic text-gray-400">anonymous visitor</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Message -->
                        <td class="px-6 py-5 max-w-xl">
                            <p class="line-clamp-4 leading-relaxed text-gray-200">
                                <?= nl2br(h($msg->body)) ?>
                            </p>
                        </td>

                        <!-- Date -->
                        <td class="px-6 py-5 text-sm text-gray-400 whitespace-nowrap">
                            <?= $msg->created->format('Y-m-d H:i') ?>
                        </td>

                        <!-- Delete -->
                        <td class="px-6 py-5 text-center p-4">
                            <?= $this->Form->postLink(
                                'Delete',
                                ['action' => 'delete', $msg->id],
                                [
                                    'confirm' => 'Delete this message?',
                                    'class' =>
                                        'inline-flex items-center px-3 py-1.5
                                         rounded-lg text-sm font-medium
                                         text-red-400 border border-red-500/40
                                         hover:bg-red-500/10 hover:text-red-300
                                         transition'
                                ]
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>

            </table>
        </div>

    <?php endif ?>
</main>
