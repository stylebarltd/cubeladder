<main class="flex-1 mx-auto max-w-3xl px-6 py-14 text-white">

    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-700 shadow-xl p-8">

        <!-- Title -->
        <h2 class="text-3xl font-extrabold mb-6 text-blue-500 tracking-wide">
            Send message to
            <span class="text-white"><?= h($receiver->name) ?></span>
        </h2>

        <?= $this->Form->create($message) ?>

        <!-- Message field -->
        <div class="mb-6">
            <?= $this->Form->control('body', [
                'type' => 'textarea',
                'rows' => 6,
                'label' => 'Message',
                'class' => '
                    w-full rounded-xl bg-gray-800 border border-gray-600
                    text-white placeholder-gray-400
                    focus:border-green-400 focus:ring focus:ring-green-400/30
                    transition
                ',
                'labelClass' => 'block mb-2 text-sm font-medium text-gray-300'
            ]) ?>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between">

            <?= $this->Html->link(
                '← Back to Inbox',
                ['controller' => 'Messages', 'action' => 'inbox'],
                ['escape' => false, 'class' => 'text-sm text-gray-400 hover:text-gray-200 transition']
            ) ?>


            <?= $this->Form->submit('Send message', ['class' => 'inline-flex items-center px-3 py-1.5 cursor-pointer
                     rounded-lg text-sm font-medium
                     text-blue-400 border border-blue-500/40
                     hover:bg-blue-500/10 hover:text-blue-300
                     transition']) ?>


        </div>

        <?= $this->Form->end() ?>
    </div>

</main>
