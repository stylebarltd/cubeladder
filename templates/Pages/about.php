<?php
/**
 * About: rules, points, credits and the changelog on the bullet artwork.
 *
 * @var \App\View\AppView $this
 * @var array $changelog config/changelog.php, newest first
 */
$check = '<span class="text-green-400">✔</span>';
$rules = [
    'Games must <b>start with at least 4 players</b> and be <b>finished</b> to count.',
    'Players are recognised by their <b>AssaultCube login (pubkey)</b> &ndash; name changes keep your stats. The default name <b>unarmed</b> is not tracked.',
    'Joining a game for <b>less than 3 minutes</b> does not count as a game played. Your kills and points still count.',
    'Games with <b>inaccurate data</b> (e.g. flags scored against an empty team after everyone left) do not count anywhere &ndash; they are marked &ldquo;not counted&rdquo;.',
    '<b>All Time Ranking</b>: points of this year, from <b>5,000 points</b> on.',
    '<b>the last 100</b>: ranking over the last 100 games played on our servers.',
    '<b>Hall of Fame</b>: best single game per category since <b>' . date('M Y', strtotime(\App\Controller\PlayersController::HOF_SINCE)) . '</b>.',
    '<b>Weekly achievements</b> are awarded every <b>Sunday</b>.',
    'Teams, minutes played and final scores come from the server logs, the live view straight from the game servers (every 5 seconds).',
];
?>
<!-- About on the bullet artwork -->
<div class="relative min-h-[calc(100svh-4rem)] bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: linear-gradient(to bottom, rgba(0,0,0,.55), rgba(0,0,0,.4) 40%, rgba(0,0,0,.8)), url('/img/bullet.jpg');">
<div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-16 md:py-10">

    <h1 class="mb-8 text-3xl md:text-5xl font-extrabold tracking-wide drop-shadow-lg">
        about <span class="text-blue-500">cube</span>Ladder
    </h1>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Rules & info -->
        <section class="rounded-xl border border-white/15 bg-black/60 p-6 backdrop-blur-[2px] lg:col-span-2">
            <h2 class="mb-4 text-lg font-bold"><i class="fa-solid fa-scale-balanced mr-2 text-blue-400"></i>Rules &amp; Info</h2>
            <ul class="space-y-2 text-sm text-zinc-200">
                <?php foreach ($rules as $rule): ?>
                    <li class="flex gap-2"><?= $check ?><span><?= $rule ?></span></li>
                <?php endforeach; ?>
                <li class="flex gap-2">
                    <span class="text-yellow-400">⚠</span>
                    <span>Don&rsquo;t want to be tracked? Open your player page &rarr; <b>Edit profile</b> &rarr; <b>Don&rsquo;t track me</b>
                        (from the connection you play from), or
                        <?= $this->Html->link('write us a message', ['controller' => 'Messages', 'action' => 'sendToAdmin'], ['class' => 'underline hover:text-blue-300']) ?>.</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-blue-400">?</span>
                    <span>Questions or ideas? <?= $this->Html->link('Write us a message', ['controller' => 'Messages', 'action' => 'sendToAdmin'], ['class' => 'underline hover:text-blue-300']) ?>
                        or join our <a href="https://discord.gg/tVX7FKCtK3" target="_blank" rel="noopener" class="underline hover:text-blue-300">Discord</a>.</span>
                </li>
                <li class="flex gap-2">
                    <span class="text-pink-400">♥</span>
                    <span>The world would be better if we were all
                        <a href="https://zz.ketar.eu/in-loving-memory-of-elias-armed/" class="underline italic hover:text-blue-300" target="_blank" rel="noopener">Armed</a>.</span>
                </li>
            </ul>
        </section>

        <!-- Player map -->
        <a href="/players/map" class="group flex flex-col rounded-xl border border-white/15 bg-black/60 p-6 backdrop-blur-[2px] hover:border-white/30 transition">
            <h2 class="mb-3 text-lg font-bold"><i class="fa-solid fa-earth-europe mr-2 text-green-400"></i>Player map</h2>
            <p class="text-sm text-zinc-300">Where in the world cubeLadder players come from &ndash; every country on one map.</p>
            <div class="flex flex-1 items-center justify-center py-6">
                <i class="fa-solid fa-earth-europe text-8xl text-white/15 group-hover:text-green-400/40 transition"></i>
            </div>
            <span class="mt-4 self-start rounded-lg bg-blue-600/80 px-4 py-2 text-sm font-semibold group-hover:bg-blue-700 transition">Open the map &rarr;</span>
        </a>

        <!-- Points -->
        <section class="rounded-xl border border-white/15 bg-black/60 p-6 backdrop-blur-[2px] lg:col-span-2 overflow-x-auto">
            <h2 class="mb-4 text-lg font-bold"><i class="fa-solid fa-calculator mr-2 text-yellow-300"></i>Points</h2>
            <table class="min-w-full text-sm text-white">
            <thead class="border-b border-white/10 text-zinc-400">
            <tr>
                <th class="px-3 py-2 text-left">Event</th>
                <th class="px-3 py-2 text-center">Points</th>
                <th class="px-3 py-2 text-left">Description</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-white/10">
            <!-- Standard kills -->
            <tr class="bg-white/5">
                <td class="px-3 py-2 font-mono">kills</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Any kill</td>
            </tr>

            <!-- Weapon kills -->
            <tr>
                <td class="px-3 py-2 font-mono">busted</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Pistol kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">shredded</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Rifle kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">sprayed</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">SMG kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">punctured</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Sniper kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">splattered</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Shotgun kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">peppered</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Shotgun kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">picked_off</td>
                <td class="px-3 py-2 text-center font-bold text-green-400">+1</td>
                <td class="px-3 py-2">Carbine kill</td>
            </tr>

            <!-- Skill kills -->
            <tr class="bg-white/5">
                <td class="px-3 py-2 font-mono">headshot</td>
                <td class="px-3 py-2 text-center font-bold text-blue-400">+2</td>
                <td class="px-3 py-2">Precision headshot</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">slashed</td>
                <td class="px-3 py-2 text-center font-bold text-blue-400">+2</td>
                <td class="px-3 py-2">Knife kill</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">gibbed</td>
                <td class="px-3 py-2 text-center font-bold text-blue-400">+2</td>
                <td class="px-3 py-2">Grenade kill</td>
            </tr>

            <!-- Objective play -->
            <tr class="bg-white/5">
                <td class="px-3 py-2 font-mono">stole_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-emerald-400">+2</td>
                <td class="px-3 py-2">Flag stolen</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">returned_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-emerald-400">+2</td>
                <td class="px-3 py-2">Flag returned</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">scored_with_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-yellow-400">+5</td>
                <td class="px-3 py-2">Flag captured</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">lost_the_flag</td>
                <td class="px-3 py-2 text-center font-bold text-red-400">-1</td>
                <td class="px-3 py-2">Flag lost</td>
            </tr>

            <!-- Penalties -->
            <tr class="bg-white/5">
                <td class="px-3 py-2 font-mono">teamkills</td>
                <td class="px-3 py-2 text-center font-bold text-red-400">-1</td>
                <td class="px-3 py-2">Team kill penalty</td>
            </tr>
            <tr>
                <td class="px-3 py-2 font-mono">suicided</td>
                <td class="px-3 py-2 text-center font-bold text-red-400">-1</td>
                <td class="px-3 py-2">Suicide</td>
            </tr>
            </tbody>
        </table>
        </section>

        <div class="flex flex-col gap-6">
            <?php if ($guild = \Cake\Core\Configure::read('Ladder.discord.guild')): ?>
            <!-- Discord server widget: who is online, join button -->
            <section class="rounded-xl border border-[#5865F2]/60 bg-[#5865F2]/25 p-4 backdrop-blur-[2px]">
                <h2 class="mb-3 px-2 text-lg font-bold"><i class="fa-brands fa-discord mr-2 text-indigo-300"></i>Community</h2>
                <iframe src="https://discord.com/widget?id=<?= h(rawurlencode((string)$guild)) ?>&amp;theme=dark" title="cubeLadder on Discord"
                        class="block h-[420px] w-full rounded-lg" loading="lazy" allowtransparency="true" frameborder="0"
                        sandbox="allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"></iframe>
            </section>
            <?php endif; ?>

            <!-- Credits -->
            <section class="rounded-xl border border-white/15 bg-black/60 p-6 backdrop-blur-[2px]">
                <h2 class="mb-4 text-lg font-bold"><i class="fa-solid fa-handshake mr-2 text-orange-300"></i>Credits</h2>
                <ul class="space-y-2 text-sm text-zinc-200">
                    <li class="flex gap-2"><?= $check ?><span>ZZ|Perros for adding the [aCKa] servers to the ladder</span></li>
                    <li class="flex gap-2"><?= $check ?><span>ZZ|Ketar* for sponsoring the .ovh domain</span></li>
                    <li class="flex gap-2"><?= $check ?><span>Chobbz for adding the Banana &amp; POTATO servers to the ladder</span></li>
                </ul>
            </section>

            <!-- Copyright -->
            <section class="rounded-xl border border-white/15 bg-black/60 p-6 backdrop-blur-[2px] text-center">
                <div class="text-3xl font-bold"><span class="text-blue-500">cube</span>Ladder</div>
                <div class="mt-2 text-sm text-zinc-300">&copy;<?= date('Y') ?> by <b class="text-white">pola|ZZ</b></div>
            </section>
        </div>

        <?php if (!empty($changelog)): ?>
        <!-- What's new (config/changelog.php) -->
        <section class="rounded-xl border border-white/15 bg-black/60 p-6 backdrop-blur-[2px] lg:col-span-3">
            <h2 class="mb-4 text-lg font-bold"><i class="fa-solid fa-bullhorn mr-2 text-pink-300"></i>What&rsquo;s new</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($changelog as $entry): ?>
                    <div>
                        <div class="flex flex-wrap items-baseline gap-x-3">
                            <span class="font-semibold text-white"><?= h($entry['title']) ?></span>
                            <span class="text-xs text-blue-400"><?= date('d M Y', strtotime($entry['date'])) ?></span>
                        </div>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-zinc-300">
                            <?php foreach ($entry['items'] as $item): ?>
                                <li><?= h($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
</div>
