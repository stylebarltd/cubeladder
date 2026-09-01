
<?php if (!empty($recordStreak)): ?>
<!-- Longest streak record banner (values overlaid on the artwork) -->
<style>
.streak-banner { position: relative; container-type: inline-size; display: block; width: 100%; max-width: 72rem; margin: 0 auto; }
.streak-banner img.bg { display: block; width: 100%; height: auto; }
.streak-banner .ov { position: absolute; color: #fff; font-family: 'Rubik', sans-serif; line-height: 1; white-space: nowrap; }
.streak-kills { left: 66.5%; top: 39.8%; width: 12.6%; text-align: center; transform: translateY(-50%);
    font-size: 4cqw; font-weight: 800; letter-spacing: .02em; }
.streak-avatar { left: 61.1%; top: 58.6%; width: 4.8%; height: 17%; object-fit: cover; border-radius: 8%;
    position: absolute; box-shadow: 0 0 0 2px rgba(255,255,255,.12); }
.streak-name { left: 67%; top: 63.6%; transform: translateY(-50%); font-size: 1.7cqw; font-weight: 700;
    max-width: 14.5%; overflow: hidden; text-overflow: ellipsis; }
.streak-date { left: 73.8%; top: 73%; transform: translateY(-50%); font-size: 1.1cqw; color: #9ca3af; letter-spacing: .05em; }
.streak-flag { left: 83.7%; top: 67%; transform: translate(-50%, -50%); font-size: 2.7cqw; }
</style>
<a href="/players/view/<?= h($recordStreak['player']['id']) ?>" class="streak-banner" title="all-time longest streak – view profile">
    <img class="bg" src="/img/longest-streak.png" alt="Longest streak in AssaultCube">
    <span class="ov streak-kills"><?= (int)$recordStreak['streak'] ?></span>
    <img class="streak-avatar"
         src="<?= h(!empty($recordStreak['player']['picture']) ? '/img/players/' . $recordStreak['player']['picture'] : '/img/acl.png') ?>" alt="">
    <span class="ov streak-name"><?= h($recordStreak['player']['name']) ?></span>
    <span class="ov streak-date"><?= h(strtoupper((new \DateTime((string)$recordStreak['game']['started_at']))->format('M j, Y'))) ?></span>
    <?php $iso = strtoupper((string)($recordStreak['player']['country'] ?? '')); if (strlen($iso) === 2): ?>
        <span class="ov streak-flag"><?= mb_chr(0x1F1E6 + ord($iso[0]) - 65) . mb_chr(0x1F1E6 + ord($iso[1]) - 65) ?></span>
    <?php endif; ?>
</a>
<?php endif; ?>

<!-- Hero Section -->
<section id="home" class="relative h-screen flex items-center justify-center bg-coffee-900 text-white overflow-hidden">
    <img src="/img/bullet.jpg"
         alt="Coffee beans"
         class="absolute w-full h-full object-cover opacity-50">
    <div class="relative z-5 text-center px-4">
        <h1 class="text-4xl md:text-7xl mb-6 font-rubik">the last <?=$maxGamesToRank?></h1>
        <p class="text-xl mb-8 max-w-2xl mx-auto"><?= $lastGameDateRange['start'] ?> until <?= $lastGameDateRange['end'] ?></p>
<!--        <p class="text-xs md:text-2xl mb-8 max-w-2xl mx-auto">AssaultCube_v1.3.0.2_LockdownEdition_RC1</p>-->

        <a href="/players/thelast100" class="bg-blue-600/70 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300">
            View Tournament

        </a>
    </div>

</section>

    <!-- Main -->
    <main class="flex-1 mx-auto max-w-5xl px-6 py-14">

        <!-- SYSTEM STATUS -->
        <div class="mb-10">

            <!-- 🟢 STATUS: OPERATIONAL -->
            <!--
            <div class="flex items-start gap-4 bg-emerald-900/30 border border-emerald-700 rounded-xl p-6">
                <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-emerald-600 text-white">
                    🟢 Operational
                </span>
                <div>
                    <h4 class="text-lg font-bold text-white">All systems operational</h4>
                    <p class="text-emerald-200 text-sm">
                        Servers are online. Games, stats, rankings, and achievements are updating normally.
                    </p>
                </div>
            </div>
            -->

            <!-- 🟡 STATUS: DEGRADED / TECHNICAL ISSUES -->
            <!--
            <div class="flex items-start gap-4 bg-yellow-900/30 border border-yellow-700 rounded-xl p-6">
                <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-yellow-500 text-black">
                    🟡 Degraded
                </span>
                <div>
                    <h4 class="text-lg font-bold text-white">Temporary technical issues</h4>
                    <p class="text-yellow-200 text-sm">
                        Some services may be slow or unavailable. Your games are safe and not lost.
                        Please check back later or tomorrow.
                    </p>
                </div>
            </div>


            <!-- 🔴 STATUS: MAINTENANCE / OFFLINE -->
            <!--
            <div class="flex items-start gap-4 bg-red-900/30 border border-red-700 rounded-xl p-6">
                <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-red-600 text-white">
                    🔴 Maintenance
                </span>
                <div>
                    <h4 class="text-lg font-bold text-white">Maintenance in progress</h4>
                    <p class="text-red-200 text-sm">
                        Stats processing is currently offline. No data is lost.
                        Everything will be back online soon.
                    </p>
                </div>
            </div>
            -->

            <!-- 🔵 STATUS: INFO / PARTIAL SYSTEM -->
            <!--
            <div class="flex items-start gap-4 bg-blue-900/30 border border-blue-700 rounded-xl p-6">
                <span class="inline-flex items-center px-3 py-1 text-sm font-semibold rounded-full bg-blue-600 text-white">
                    🔵 Info
                </span>
                <div>
                    <h4 class="text-lg font-bold text-white">Partial system availability</h4>
                    <p class="text-blue-200 text-sm">
                        Servers are online, but some stats or achievements may update with delay.
                    </p>
                </div>
            </div>
            -->

        </div>






        <?php if (!empty($chatterbox)): ?>
        <!-- Chatterbox -->
        <div class="bg-zinc-900 rounded-xl p-8 mb-8">
            <div class="text-4xl mb-4"><i class="fa-solid fa-comments"></i></div>
            <h3 class="text-xl font-bold mb-1 text-white">Chatterbox</h3>
            <div class="text-xs text-zinc-500 mb-4">who talks the most in game &middot; since Sep 2026</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <?php foreach ($chatterbox['top'] as $i => $p): ?>
                    <a href="/players/view/<?= h($p['id']) ?>" class="flex items-center gap-3 p-2 bg-zinc-800 rounded-lg hover:bg-zinc-700 transition">
                        <div class="text-2xl font-bold w-8 text-center"><?= $i + 1 ?>.</div>
                        <img src="<?= !empty($p['picture']) ? '/img/players/' . h($p['picture']) : '/img/acl.png' ?>" class="w-10 h-10 rounded-full object-cover" alt="">
                        <div class="flex-1 font-semibold text-white"><?= h($p['name']) ?> <?= $this->Layout->flag($p['country']) ?></div>
                        <div class="text-blue-500 font-mono text-lg"><?= number_format($p['n']) ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($chatterbox['quotes'])): ?>
                <div class="space-y-2">
                    <div class="text-xs text-zinc-500"><?= h($chatterbox['top'][0]['name']) ?>'s latest words:</div>
                    <?php foreach ($chatterbox['quotes'] as $q): ?>
                    <div class="p-2 bg-zinc-800 rounded-lg text-sm text-zinc-300 italic">&ldquo;<?= h($q) ?>&rdquo;</div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-8 mb-8">
            <!-- Players -->
            <a href="/players"
               class="block bg-zinc-900 rounded-xl p-8">
                <div class="text-4xl mb-4"><i class="fa-solid fa-user"></i></div>
                <h3 class="text-xl font-bold mb-2 text-white">All time ranks</h3>

                <div class="space-y-2">
                    <?php foreach ($bestPlayersByScore as $i => $player): ?>
                        <div class="flex items-center gap-3 p-2 bg-zinc-800 rounded-lg">

                            <div class="text-2xl font-bold w-8 text-center">
                                <?= $i + 1 ?>.
                            </div>

                            <img
                                src="<?= $this->Layout->playerPicture($player) ?>"
                                class="w-10 h-10 rounded-full"
                                alt="pic"
                            >

                            <div class="flex-1">
                                <div class="font-semibold"><?= h($player->name) ?></div>
                                <div class="text-xs text-blue-500">
                                    <?= $this->Layout->flag($player->country) ?>
                                    <?= h($player->country) ?>
                                </div>
                            </div>

                            <div class="text-blue-500 font-mono text-lg">
                                <?= number_format($player->total_score) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </a>
            <!-- Maps -->
            <a href="/maps"
               class="block bg-zinc-900 rounded-xl p-8">
                <div class="text-4xl mb-4"><i class="fa-solid fa-map"></i></div>
                <h3 class="text-xl font-bold mb-2 text-white">Maps</h3>

                <div class="grid grid-cols-1 md:grid-cols-1 gap-4">

                    <?php foreach ($bestPlayersByMap as $item): ?>
                        <div class="bg-zinc-800 p-4 rounded-lg shadow">
                            <div class="text-xl font-bold text-white">
                                <?= h($item['map']->name) ?>
                                <span class="text-blue-500 text-sm">
                                    Played <?= $item['times_played'] ?> times
                                </span>
                            </div>



                            <?php if ($item['best_player']): ?>
                                <div class="mt-3 flex items-center gap-3">
                                    <img src="<?= $this->Layout->playerPicture($item['best_player']) ?>" class="w-10 h-10 rounded-full">
                                    <div>
                                        <div class="font-semibold">
                                            <?= h($item['best_player']->name) ?>
                                        </div>
                                        <div class="text-xs text-blue-500">
                                            <?= $this->Layout->flag($item['best_player']->country) ?>
                                            <?= h($item['best_player']->country) ?><br>

                                        </div>

                                    </div>
                                    <div class="flex-1 text-blue-500 font-mono text-lg text-right">
                                        <?= $item['best_player']->total_score ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                </div>

            </a>
        </div>
        <div class="grid grid-cols-1 gap-8 mt-8">


            <!-- Games -->
            <div
                class="block bg-zinc-900 rounded-xl p-8">
                <div class="text-4xl mb-4"><i class="fa-solid fa-trophy"></i></div>
                <h3 class="text-xl font-bold mb-2 text-white">Achievements last week</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
                    <?php foreach ($this->Achievements->sort($achievementPlayers) as $a):
                        if($a->event_type=='best_on_map')continue;
                        ?>
                        <div class="achievement-card">
                            <img src="/img/achievements/<?= $this->Achievements->iconClass($a->event_type) ?>" class="w-8 h-8">
                            <div class="label">
                                <?= h($this->Achievements->labelFor($a)) ?>
                            </div>
                            <div class="player font-bold">


                                <?= $this->Html->link(h($a->player->name)." ". $this->Layout->flag($a->player->country), ['controller' => 'Players', 'action' => 'view', $a->player->id], ['class' => 'hover:text-blue-400', 'escape' => false]) ?>

                                <?= h($a->player->country) ?>
                            </div>

                            <div class="value text-blue-500">
                                <?= $this->Achievements->format($a->event_type, $a->count) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>


        <div class="grid grid-cols-1 gap-8 mt-8 mb-8">


            <!-- Last updates -->
            <div
                class="block bg-zinc-900 rounded-xl p-8">
                <div class="text-4xl mb-4"><i class="fa-solid fa-server"></i></div>
                <h3 class="text-xl font-bold mb-2 text-white">Servers</h3>
                <?php if (!empty($lastLogs)): ?>
                    <?php foreach($lastLogs as $lastLog): ?>
                        <i class="fa-solid fa-server"></i> <strong> <?= $this->Layout->serverName($lastLog->server_name) ?>, <?= $lastLog->modified->format('Y-m-d H:i:s') ?> (UTC)</strong>
                        <br>
                    <?php endforeach ?>
                <?php endif; ?>
            </div>
        </div>


    </main>







