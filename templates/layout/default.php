<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
    // Pages without their own title: CakePHP fills in the template folder
    // ("Pages", "Live") - use the page's name instead
    $pageTitle = $this->fetch('title');
    if ($pageTitle === '' || $pageTitle === $this->getTemplatePath()) {
        $template = $this->getTemplate();
        $pageTitle = $template === 'home' ? 'cubeLadder'
            : \Cake\Utility\Inflector::humanize(\Cake\Utility\Inflector::underscore(in_array($template, ['index', 'display'], true) ? $this->getTemplatePath() : $template)) . ' · cubeLadder';
    }
    ?>
    <title><?= h($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $this->Html->meta('csrfToken', $this->request->getAttribute('csrfToken')) ?>

    <!-- Tailwind CSS -->
    <?= $this->Html->css('tailwind.css?ver=1.74') ?>

    <!-- cubeLadder icon (webroot/img/brand) -->
    <?= $this->Html->meta('icon', '/favicon.ico') ?>
    <link rel="icon" type="image/svg+xml" href="/img/brand/cubeladder-favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <meta name="theme-color" content="#0b1225">

    <!-- Additional head content -->
    <?= $this->fetch('meta') ?>
    <?php if (!str_contains($this->fetch('meta'), 'og:image')):
        // pages without their own link preview share the site card (PreviewsController::site)
        $ogSite = rtrim((string)(\Cake\Core\Configure::read('Ladder.discord.site') ?: 'https://cubeladder.ovh'), '/');
        $ogText = 'Rankings, CTF ratings, Hall of Fame and live games for AssaultCube - every number straight from the game servers\' logs.';
        ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="cubeLadder">
    <meta property="og:title" content="<?= h($pageTitle) ?>">
    <meta property="og:description" content="<?= h($ogText) ?>">
    <meta property="og:url" content="<?= h($ogSite . $this->request->getRequestTarget()) ?>">
    <meta property="og:image" content="<?= h($ogSite . '/previews/site?v=' . date('Ymd')) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="description" content="<?= h($ogText) ?>">
    <?php endif; ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>



    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


</head>
<body class="bg-zinc-900 text-white font-mono">




<div class="min-h-screen bg-zinc-950 text-gray font-mono flex flex-col">


    <!-- Navigation -->
    <nav class="bg-zinc-900 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <a href="/" class="flex items-center gap-2 text-2xl md:text-3xl font-bold"><img src="/img/brand/fav-48.png" alt="" class="hidden sm:block h-8 w-8"><span><span class="text-blue-500">cube</span>Ladder</span></a>
                </div>
                <div class="hidden md:flex items-center whitespace-nowrap md:space-x-4 md:text-sm lg:space-x-8 lg:text-base">

                    <a href="/players" class="hover:text-zinc-500 transition">All Time Ranking</a>
                    <a href="/players/hall_of_fame" class="hover:text-zinc-500 transition">Hall of Fame</a>
                    <a href="/games/index" class="hover:text-zinc-500 transition">Games</a>
                    <a href="/maps" class="hover:text-zinc-500 transition">Maps</a>


                </div>
                <div class="flex items-center gap-4">
                    <?php $onAir = !empty($liveNow['live']); ?>

                    <!-- Live (desktop; in the menu on phones): slowly pulses green while a game is on air (refreshed from /live/onair) -->
                    <a href="/live" class="js-onair-link hidden md:inline-block p-2 transition <?= $onAir ? 'onair-pulse text-green-400 hover:text-green-300' : 'hover:text-zinc-500' ?>"
                       title="<?= $onAir ? h('On air: ' . $liveNow['server'] . ' · ' . $liveNow['players'] . ' playing') : 'Live servers' ?>" aria-label="Live servers">
                        <i class="fa-solid fa-tower-broadcast"></i>
                    </a>

                    <!-- Search -->
                    <button class="p-2 hover:text-zinc-500 transition" id="search-btn" aria-label="Search players">
                        <i class="fas fa-search"></i>
                    </button>

                    <!-- About / rules -->
                    <a href="/about" class="hidden md:inline-block p-2 hover:text-zinc-500 transition" title="About, rules &amp; what's new" aria-label="About">
                        <i class="fa-solid fa-circle-question"></i>
                    </a>

                    <!-- Inbox only if single authenticated player (desktop; in the menu on phones) -->
                    <?php if (!empty($authPlayer)): ?>
                        <a class="relative p-2 hover:text-zinc-500 transition hidden md:inline-block"
                           href="<?= $this->Url->build(['controller' => 'Messages', 'action' => 'inbox']) ?>">
                            <i class="fa fa-inbox text-lg"></i>

                            <?php if ($inboxUnread > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs
                             rounded-full h-5 w-5 flex items-center justify-center">
                    <?= $inboxUnread ?>
                </span>
                            <?php endif; ?>
                        </a>

                        <div class="font-semibold hover:text-blue-400 transition">
                            <?= $this->Html->link(
                                h($authPlayer->name),
                                ['controller' => 'Players', 'action' => 'view', $authPlayer->id],
                                ['escape' => false, 'title' => 'Your player page']
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Multiple Player Dropdown -->
                    <?php if (!empty($multiplePlayers)): ?>

                        <?= $this->Form->create(null, [
                            'url' => ['controller' => 'Players', 'action' => 'select'],
                            'class' => 'inline-block'
                        ]) ?>

                        <?= $this->Form->control('player_id', [
                            'type' => 'select',
                            'options' => collection($multiplePlayers)->combine('id', 'name')->toArray(),
                            'empty' => 'Who are you?',
                            'label' => false,
                            'class' => '
                                text-sm
                                bg-black
                                border border-zinc-300
                                rounded-md
                                px-3 py-1.5
                                focus:outline-none
                                focus:ring-2
                                focus:ring-blue-400
                                focus:border-blue-400
                                hover:border-zinc-400
                                transition
                            ',
                            'onchange' => 'this.form.submit();'
                        ]) ?>

                        <?= $this->Form->end() ?>

                    <?php endif; ?>

                </div>

                <button class="md:hidden p-2 relative" id="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                    <span class="js-onair absolute top-0.5 right-0.5 flex h-2.5 w-2.5 <?= $onAir ? '' : 'hidden' ?>">
                        <span class="js-onair-dot absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                    </span>
                </button>
            </div>
        </div>
        <!-- Mobile menu -->
        <div class="md:hidden hidden bg-zinc-900" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">

                <a href="/players" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">All Time Ranking</a>
                <a href="/players/hall_of_fame" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Hall of Fame</a>
                <a href="/games/index" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Games</a>
                <a href="/live" class="flex items-center gap-2 px-3 py-2 hover:bg-zinc-600 rounded-md">
                    <span class="js-onair-link <?= $onAir ? 'onair-pulse font-bold text-green-400' : '' ?>">Live</span>
                    <span class="js-onair js-onair-text text-xs font-bold uppercase tracking-wide text-green-400 <?= $onAir ? '' : 'hidden' ?>">
                        on air<?= $onAir ? h(' · ' . $liveNow['players'] . ' playing') : '' ?>
                    </span>
                </a>
                <a href="/maps" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Maps</a>
                <a href="/about" class="flex items-center gap-2 px-3 py-2 hover:bg-zinc-600 rounded-md"><i class="fa-solid fa-circle-question"></i> About, rules &amp; what's new</a>
                <?php if (!empty($authPlayer)): ?>
                    <a href="<?= $this->Url->build(['controller' => 'Messages', 'action' => 'inbox']) ?>" class="flex items-center gap-2 px-3 py-2 hover:bg-zinc-600 rounded-md">
                        <i class="fa fa-inbox"></i> Inbox
                        <?php if ($inboxUnread > 0): ?>
                            <span class="bg-blue-500 text-white text-xs rounded-full h-5 min-w-5 px-1 flex items-center justify-center"><?= $inboxUnread ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>


            </div>
        </div>
        <div class="hidden bg-zinc-900" id="search-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <input id="player-search"
                       type="text"
                       placeholder="Search players..."
                       class="w-full px-4 py-2 rounded-lg shadow-sm bg-zinc-800"/>

                <div id="search-results"
                     class="mt-2 bg-zinc-900 text-white rounded-lg shadow-lg hidden">
                </div>
            </div>
        </div>
    </nav>


        <?= $this->Flash->render() ?>



<!-- Page content -->
<?= $this->fetch('content') ?>
    <!-- Footer -->


</div>


<script>
    const input = document.getElementById('player-search');
    const resultsBox = document.getElementById('search-results');
    let timer;

    input.addEventListener('keyup', () => {
        clearTimeout(timer);
        const query = input.value.trim();

        if (query.length < 2) {
            resultsBox.classList.add('hidden');
            return;
        }

        timer = setTimeout(() => {
            fetch(`/players/search?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(players => {
                    if (players.length === 0) {
                        resultsBox.innerHTML = `
                        <div class="p-3 text-blue-500">No results...</div>
                    `;
                    } else {
                        resultsBox.innerHTML = players.map(p => `
                        <a href="/players/view/${p.id}"
                           class="block px-4 py-2 border-b border-zinc-950 hover:bg-zinc-800 flex items-center gap-3">

                            <span>${p.name}</span>
                            <span class="ml-auto opacity-70"> ${p.country}</span>
                        </a>
                    `).join('');
                    }
                    resultsBox.classList.remove('hidden');
                });
        }, 300); // 🔥 delay avoids spam requests
    });
</script>




<script>


    // DOM elements

    // "On air" dot on Live: refresh every 30 s (cheap, cached server side)
    (function () {
        const links = document.querySelectorAll('.js-onair-link');
        async function refresh() {
            try {
                const res = await fetch('/live/onair', { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!res.ok) return;
                const s = await res.json();
                document.querySelectorAll('.js-onair').forEach(el => el.classList.toggle('hidden', !s.live));
                document.querySelectorAll('.js-onair-text').forEach(el => {
                    el.textContent = s.live ? 'on air · ' + s.players + ' playing' : '';
                });
                links.forEach(link => {
                    ['onair-pulse', 'text-green-400', 'font-bold'].forEach(c => link.classList.toggle(c, s.live));
                    link.title = s.live ? 'On air: ' + s.server + ' · ' + s.players + ' playing' : 'Live servers';
                });
            } catch (e) {}
        }
        refresh();
        setInterval(refresh, 30000);
    })();

    const mobileMenuBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const searchBtn = document.getElementById('search-btn');
    const searchMenu = document.getElementById('search-menu');

    // Toggle mobile menu
    function toggleMobileMenu() {
        mobileMenu.classList.toggle('hidden');
    }
    function toggleSearchBox() {
        searchMenu.classList.toggle('hidden');
    }

    // Event listeners
    mobileMenuBtn.addEventListener('click', toggleMobileMenu);
    searchBtn.addEventListener('click', toggleSearchBox);

    // Close mobile menu when clicking a link
    document.querySelectorAll('#mobile-menu a').forEach(link => {
        link.addEventListener('click', toggleMobileMenu);
    });

    document.querySelectorAll('#search-btn a').forEach(link => {
        link.addEventListener('click', toggleSearchBox);
    });

    // Initialize the page
   // displayMenuItems();
</script>


<!-- Optional scripts -->
<?= $this->fetch('scriptBottom') ?>
</body>
</html>
