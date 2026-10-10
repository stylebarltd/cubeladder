<!-- Whole front page on the bullet artwork (fixed while scrolling) -->
<div class="relative bg-zinc-900 bg-cover bg-center bg-fixed text-white"
     style="background-image: radial-gradient(ellipse at center, rgba(0,0,0,.15) 0%, rgba(0,0,0,.45) 35%, rgba(0,0,0,.9) 75%), url('/img/bullet.jpg');">

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

<!-- Hero: cubeLadder branding + live on-air card + what's new -->
<?php
$onAir = !empty($liveNow['live']);
$features = [
    ['/players?continent=europe', 'fa-earth-americas', 'text-lime-300', 'Best by country', 'rankings per continent &amp; country &ndash; who rules Brazil?'],
    ['/live', 'fa-tower-broadcast', 'text-green-400', 'Live scoreboards', 'every server, map in the background, 5 s updates'],
    ['/players/hall_of_fame', 'fa-crown', 'text-yellow-300', 'Hall of Fame', 'best single game in 7 categories'],
    ['/maps', 'fa-map', 'text-sky-300', 'Maps', 'records, top players &amp; weekly winners per map'],
    ['/players', 'fa-user-astronaut', 'text-blue-300', 'Player pages', 'nemesis, prey, records, time played'],
    ['/players/map', 'fa-earth-europe', 'text-emerald-300', 'Player map', 'where the ladder plays from'],
    ['https://discord.gg/tVX7FKCtK3', 'fa-brands fa-discord', 'text-indigo-300', 'Discord feeds', 'live stats for ladder &amp; inters'],
    ['/about', 'fa-scale-balanced', 'text-amber-300', 'Fair play', 'empty-team flags &amp; 3-min joins don&rsquo;t count'],
];
?>
<section class="relative flex min-h-[85svh] flex-col justify-center overflow-hidden">
    <!-- soft blue glow behind the logo -->
    <div class="pointer-events-none absolute -left-32 top-1/4 h-[28rem] w-[28rem] rounded-full bg-blue-600/25 blur-3xl"></div>

    <div class="relative mx-auto grid w-full max-w-7xl items-center gap-8 px-4 py-10 md:px-6 lg:grid-cols-[1fr_22rem]">

        <!-- Branding -->
        <div class="text-center lg:text-left">
            <div class="mb-5 inline-flex items-center gap-2 whitespace-nowrap rounded-full border border-white/20 bg-black/50 px-3 sm:px-4 py-1 text-[10px] sm:text-[11px] font-bold uppercase tracking-[0.12em] sm:tracking-[0.3em] text-zinc-200 backdrop-blur-[2px]">
                <span class="h-2 w-2 rounded-full bg-blue-500"></span> AssaultCube ranking &amp; live stats
            </div>
            <h1 class="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-2 sm:gap-4 text-5xl sm:text-7xl md:text-8xl font-bold tracking-tight leading-none drop-shadow-[0_6px_30px_rgba(0,0,0,.9)]">
                <img src="/img/brand/cubeladder-mark-hero.webp" alt="" class="h-32 sm:h-28 md:h-36 xl:h-44 w-auto shrink-0">
                <span class="flex items-end">
                    <span class="bg-gradient-to-b from-sky-300 to-blue-600 bg-clip-text text-transparent drop-shadow-[0_0_25px_rgba(59,130,246,.55)]">cube</span><span class="text-white">Ladder</span>
                </span>
            </h1>
            <p class="mx-auto lg:mx-0 mt-6 max-w-2xl text-lg md:text-2xl font-semibold text-white drop-shadow">
                Every frag. Every flag. Every streak.
            </p>
            <p class="mx-auto lg:mx-0 mt-2 max-w-2xl text-sm md:text-base text-zinc-300 drop-shadow">
                Rankings, Hall of Fame, maps and live games from the AssaultCube servers &ndash; fair, fast and in real time.
            </p>

            <?php if (!empty($heroStats)): ?>
                <div class="mt-7 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-center lg:justify-start sm:gap-3">
                    <?php foreach ([['games', 'fa-gamepad', 'games tracked'], ['players', 'fa-users', 'players'], ['maps', 'fa-map', 'maps played']] as [$k, $icon, $label]): ?>
                        <div class="sm:min-w-[7.5rem] rounded-xl border border-white/15 bg-black/55 backdrop-blur-[2px] px-2 py-2 sm:px-4 sm:py-2.5">
                            <div class="font-mono text-xl sm:text-2xl md:text-3xl font-extrabold text-white"><?= number_format((int)$heroStats[$k]) ?></div>
                            <div class="text-[9px] sm:text-[11px] uppercase tracking-wide sm:tracking-wider text-zinc-400"><span class="hidden sm:inline"><i class="fa-solid <?= $icon ?> mr-1"></i></span><?= $label ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mt-7 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-center lg:justify-start sm:gap-3 text-xs sm:text-base">
                <a href="/players" class="rounded-lg bg-blue-600 px-1 py-3 sm:px-5 whitespace-nowrap text-center font-semibold text-white shadow-lg shadow-blue-600/30 hover:bg-blue-700 transition">
                    <span class="hidden sm:inline"><i class="fa-solid fa-ranking-star mr-1"></i></span> Rankings
                </a>
                <a href="/players/hall_of_fame" class="rounded-lg border border-yellow-300/50 bg-black/50 px-1 py-3 sm:px-5 whitespace-nowrap text-center font-semibold text-yellow-200 backdrop-blur-[2px] hover:bg-yellow-500/20 transition">
                    <span class="hidden sm:inline"><i class="fa-solid fa-crown mr-1"></i></span> Hall of Fame
                </a>
            </div>
        </div>

        <!-- On air (refreshed from /live/onair) -->
        <a href="/live" id="hero-onair"
           class="group block rounded-xl border border-white/15 bg-black/55 backdrop-blur-[2px] p-5 hover:border-white/30 transition <?= $onAir ? 'ring-1 ring-green-400/40' : '' ?>">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-[0.25em] text-zinc-400">Live</span>
                <span id="hero-onair-badge" class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider <?= $onAir ? 'onair-pulse bg-green-500/20 text-green-300' : 'bg-white/10 text-zinc-400' ?>">
                    <?= $onAir ? '● on air' : 'off air' ?>
                </span>
            </div>
            <div id="hero-onair-server" class="mt-4 truncate text-xl font-bold text-white"><?= $onAir ? h($liveNow['server']) : 'No game right now' ?></div>
            <div id="hero-onair-sub" class="mt-1 text-sm text-zinc-300">
                <?= $onAir ? h($liveNow['players'] . ' playing') : 'Check the servers or join one yourself' ?>
            </div>
            <div class="mt-5 flex items-center justify-between text-sm font-semibold text-green-300">
                <span><i class="fa-solid fa-tower-broadcast mr-1"></i> Open live view</span>
                <i class="fa-solid fa-arrow-right transition group-hover:translate-x-1"></i>
            </div>
        </a>
    </div>

    <!-- What's new -->
    <div class="relative mx-auto w-full max-w-7xl px-4 pb-14 md:px-6">
        <div class="mb-3 text-xs font-bold uppercase tracking-[0.3em] text-zinc-400 drop-shadow">What&rsquo;s new</div>
        <!-- phones: one row to swipe through; larger screens: grid -->
        <div class="nice-scroll -mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-4 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-8">
            <?php foreach ($features as [$href, $icon, $color, $title, $sub]): $external = str_starts_with($href, 'http'); ?>
                <a href="<?= h($href) ?>" <?= $external ? 'target="_blank" rel="noopener"' : '' ?>
                   class="group w-40 shrink-0 snap-start sm:w-auto rounded-xl border border-white/15 bg-black/55 backdrop-blur-[2px] p-3 hover:border-white/30 hover:bg-black/70 transition">
                    <i class="<?= str_contains($icon, 'fa-brands') ? $icon : 'fa-solid ' . $icon ?> <?= $color ?> text-xl"></i>
                    <div class="mt-2 text-sm font-bold text-white"><?= $title ?></div>
                    <div class="mt-0.5 text-[11px] leading-snug text-zinc-400"><?= $sub ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <a href="#home-main" class="absolute bottom-3 left-1/2 -translate-x-1/2 text-zinc-400 hover:text-white animate-bounce" aria-label="Scroll down">
        <i class="fa-solid fa-chevron-down text-xl"></i>
    </a>
</section>

<!-- Main: all boxes on the bullet artwork -->
    <main id="home-main" class="flex-1 mx-auto w-full max-w-7xl px-4 md:px-6 py-8 md:py-12 space-y-6 scroll-mt-16">

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


        <!-- Row 1: all time ranks / hall of fame records / achievements last week -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- All time ranks -->
            <a href="/players" class="block bg-black/60 border border-white/15 backdrop-blur-[2px] rounded-xl p-6 hover:border-white/30 transition">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-4"><i class="fa-solid fa-ranking-star text-blue-400"></i> All time ranks</h3>
                <div class="space-y-1.5">
                    <?php foreach ($bestPlayersByScore as $i => $player): ?>
                        <div class="flex items-center gap-3 rounded-lg bg-white/5 px-2 py-1.5">
                            <div class="w-6 text-center text-lg font-bold <?= $i < 3 ? 'text-yellow-300' : 'text-zinc-400' ?>"><?= $i + 1 ?></div>
                            <img src="<?= $this->Layout->playerPicture($player) ?>" class="h-8 w-8 rounded-full object-cover" alt="">
                            <div class="min-w-0 flex-1 truncate font-semibold"><?= h($player->name) ?> <?= $this->Layout->flag($player->country) ?></div>
                            <div class="font-mono text-blue-400"><?= number_format($player->total_score) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </a>

            <!-- Hall of Fame: the record holder of every category -->
            <a href="/players/hall_of_fame" class="block bg-black/60 border border-white/15 backdrop-blur-[2px] rounded-xl p-6 hover:border-white/30 transition">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-4"><i class="fa-solid fa-crown text-yellow-300"></i> Hall of Fame records</h3>
                <div class="space-y-1.5">
                    <?php foreach ($hofRecords as $rec): $p = $rec['player']; ?>
                        <div class="flex items-center gap-3 rounded-lg bg-white/5 px-2 py-1.5">
                            <img src="<?= $this->Layout->playerPicture($p) ?>" class="h-8 w-8 rounded-full object-cover" alt="">
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] uppercase tracking-wide text-zinc-400"><?= h($rec['title']) ?></div>
                                <div class="truncate text-sm font-semibold"><?= h($p->name) ?> <?= $this->Layout->flag($p->country) ?></div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono text-lg font-bold leading-none text-yellow-300"><?= number_format((int)$p->value) ?></div>
                                <div class="mt-0.5 text-[10px] text-zinc-500"><?= h($p->map_name) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </a>

            <!-- Achievements last week (compact) -->
            <div class="bg-black/60 border border-white/15 backdrop-blur-[2px] rounded-xl p-6">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-4"><i class="fa-solid fa-trophy text-orange-300"></i> Achievements last week</h3>
                <div class="space-y-1.5">
                    <?php foreach ($this->Achievements->sort($achievementPlayers) as $a):
                        if ($a->event_type == 'best_on_map') continue; ?>
                        <div class="flex items-center gap-3 rounded-lg bg-white/5 px-2 py-1.5">
                            <img src="/img/achievements/<?= $this->Achievements->iconClass($a->event_type) ?>" class="h-7 w-7 shrink-0" alt="">
                            <div class="min-w-0 flex-1">
                                <div class="text-[10px] uppercase tracking-wide text-zinc-400"><?= h($this->Achievements->labelFor($a)) ?></div>
                                <div class="truncate text-sm font-semibold">
                                    <?= $this->Html->link(h($a->player->name) . ' ' . $this->Layout->flag($a->player->country),
                                        ['controller' => 'Players', 'action' => 'view', $a->player->id], ['class' => 'hover:text-blue-400', 'escape' => false]) ?>
                                </div>
                            </div>
                            <div class="font-mono font-bold text-orange-300"><?= $this->Achievements->format($a->event_type, $a->count) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Row 2: discord / the last 100 / servers -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <!-- Discord: live game feeds -->
            <a href="https://discord.gg/tVX7FKCtK3" target="_blank" rel="noopener"
               class="group flex flex-col rounded-xl border border-[#5865F2]/60 bg-[#5865F2]/25 p-6 backdrop-blur-[2px] hover:bg-[#5865F2]/35 transition">
                <h3 class="flex items-center gap-3 text-lg font-bold text-white"><i class="fa-brands fa-discord text-3xl"></i> Live game stats on Discord</h3>
                <p class="mt-3 flex-1 text-sm text-zinc-200">
                    Ladder games and inters live in your Discord: who is playing, map, score and flags &ndash;
                    updated every few seconds, with a ping when a server fills up.
                </p>
                <?php if (!empty($discord['online'])): ?>
                    <!-- who is on our Discord right now (public server widget) -->
                    <div class="mt-4 flex items-center gap-3">
                        <div class="flex -space-x-2">
                            <?php foreach (array_slice($discord['avatars'], 0, 6) as $avatar): ?>
                                <img src="<?= h($avatar) ?>" alt="" loading="lazy" class="h-7 w-7 rounded-full border-2 border-[#3b3f8f] bg-zinc-800">
                            <?php endforeach; ?>
                        </div>
                        <span class="flex items-center gap-1.5 text-sm font-semibold text-white">
                            <span class="h-2 w-2 rounded-full bg-green-400"></span><?= number_format($discord['online']) ?> online
                        </span>
                    </div>
                <?php endif; ?>
                <span class="mt-4 self-start rounded-lg bg-[#5865F2] px-4 py-2 text-sm font-semibold text-white group-hover:bg-[#4752c4] transition">
                    Join our Discord <i class="fa-solid fa-arrow-up-right-from-square ml-1 text-xs"></i>
                </span>
            </a>

            <!-- The last 100 -->
            <a href="/players/thelast100" class="group flex flex-col bg-black/60 border border-white/15 backdrop-blur-[2px] rounded-xl p-6 hover:border-white/30 transition">
                <h3 class="text-3xl font-rubik text-white">the last <?= $maxGamesToRank ?></h3>
                <p class="mt-3 flex-1 text-sm text-zinc-300">
                    Ranking over the last <?= $maxGamesToRank ?> games:<br>
                    <span class="text-zinc-100"><?= $lastGameDateRange['start'] ?> &ndash; <?= $lastGameDateRange['end'] ?></span>
                </p>
                <span class="mt-4 self-start rounded-lg bg-blue-600/80 px-4 py-2 text-sm font-semibold text-white group-hover:bg-blue-700 transition">
                    View Tournament
                </span>
            </a>

            <!-- Servers (last log import) -->
            <div class="flex flex-col bg-black/60 border border-white/15 backdrop-blur-[2px] rounded-xl p-6">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-3"><i class="fa-solid fa-server text-green-400"></i> Servers</h3>
                <?php if (!empty($lastLogs)): ?>
                    <ul class="space-y-1 text-sm">
                        <?php foreach ($lastLogs as $lastLog): ?>
                            <li class="flex justify-between gap-3">
                                <span class="font-semibold"><?= $this->Layout->serverName($lastLog->server_name) ?></span>
                                <span class="font-mono text-xs text-zinc-400"><?= $lastLog->modified->format('d M H:i') ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <p class="mt-3 text-[11px] text-zinc-500">Last stats import per server (UTC)</p>
                <?php endif; ?>
            </div>
        </div>



    </main>
</div>

<?php $this->start('scriptBottom'); ?>
<script>
// keep the hero's on-air card in step with the menu (same /live/onair check)
(function () {
    const card = document.getElementById('hero-onair');
    if (!card) return;
    async function refresh() {
        try {
            const res = await fetch('/live/onair', { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!res.ok) return;
            const s = await res.json();
            const badge = document.getElementById('hero-onair-badge');
            badge.textContent = s.live ? '● on air' : 'off air';
            badge.className = 'rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider ' +
                (s.live ? 'onair-pulse bg-green-500/20 text-green-300' : 'bg-white/10 text-zinc-400');
            document.getElementById('hero-onair-server').textContent = s.live ? s.server : 'No game right now';
            document.getElementById('hero-onair-sub').textContent = s.live ? s.players + ' playing' : 'Check the servers or join one yourself';
            card.classList.toggle('ring-1', s.live);
            card.classList.toggle('ring-green-400/40', s.live);
        } catch (e) {}
    }
    refresh();
    setInterval(refresh, 30000);
})();
</script>
<?php $this->end(); ?>
