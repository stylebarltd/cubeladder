<?php
/**
 * @var \App\View\AppView $this
 * @var iterable<\App\Model\Entity\WebVisit> $webVisits
 */
?>

<div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-10 px-6">
    <div class="max-w-7xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">
                Web Visits
            </h1>

        </div>

        <!-- Card -->
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-2xl overflow-hidden">

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?= $this->Paginator->sort('ip_address') ?>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?= $this->Paginator->sort('is_bot') ?>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?= $this->Paginator->sort('path') ?>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?= $this->Paginator->sort('method') ?>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?= $this->Paginator->sort('player_id') ?>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <?= $this->Paginator->sort('created') ?>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                    </thead>

                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($webVisits as $webVisit): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                <?= h($webVisit->ip_address) ?>
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($webVisit->is_bot): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700">
                                        Bot
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                        Human
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">
                                <?= h($webVisit->path) ?>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">
                                <span class="bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded text-xs font-mono">
                                    <?= h($webVisit->method) ?>
                                </span>
                            </td>

                            <td class="px-6 py-4 text-sm text-indigo-600 dark:text-indigo-400">
                                <?= $webVisit->hasValue('player')
                                    ? $this->Html->link(
                                        $webVisit->player->name,
                                        ['controller' => 'Players', 'action' => 'view', $webVisit->player->id],
                                        ['class' => 'hover:underline']
                                    )
                                    : '' ?>
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                <?= h($webVisit->created) ?>
                            </td>

                            <td class="px-6 py-4 text-right text-sm space-x-2">
                                <?= $this->Html->link('View', ['action' => 'view', $webVisit->id], [
                                    'class' => 'text-blue-600 hover:underline'
                                ]) ?>

                                <?= $this->Html->link('Edit', ['action' => 'edit', $webVisit->id], [
                                    'class' => 'text-yellow-600 hover:underline'
                                ]) ?>

                                <?= $this->Form->postLink(
                                    'Delete',
                                    ['action' => 'delete', $webVisit->id],
                                    [
                                        'confirm' => __('Are you sure you want to delete # {0}?', $webVisit->id),
                                        'class' => 'text-red-600 hover:underline'
                                    ]
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 flex items-center justify-between">
                <div class="flex space-x-2 text-sm">
                    <style>
                        li {list-style-type:none}
                    </style>
                        <?= $this->Paginator->first('<<', ['class' => 'px-3 py-1 bg-white dark:bg-gray-600 rounded shadow']) ?>
                        <?= $this->Paginator->prev('<', ['class' => 'px-3 py-1 bg-white dark:bg-gray-600 rounded shadow']) ?>
                        <?= $this->Paginator->numbers(['class' => 'px-3 py-1 bg-white dark:bg-gray-600 rounded shadow']) ?>
                        <?= $this->Paginator->next('>', ['class' => 'px-3 py-1 bg-white dark:bg-gray-600 rounded shadow']) ?>
                        <?= $this->Paginator->last('>>', ['class' => 'px-3 py-1 bg-white dark:bg-gray-600 rounded shadow']) ?>


                </div>

                <p class="text-sm text-gray-600 dark:text-gray-300">
                    <?= $this->Paginator->counter(
                        'Page {{page}} of {{pages}}, showing {{current}} of {{count}}'
                    ) ?>
                </p>
            </div>

        </div>
    </div>
</div>
