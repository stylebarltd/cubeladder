<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>
        CubeLadder
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?= $this->Html->meta('csrfToken', $this->request->getAttribute('csrfToken')) ?>

    <!-- Tailwind CSS -->
    <?= $this->Html->css('tailwind.css?ver=1.7') ?>

    <!-- Optional: favicon -->
    <?= $this->Html->meta('icon', '/favicon.ico') ?>

    <!-- Additional head content -->
    <?= $this->fetch('meta') ?>
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
                    <a href="/" class="text-3xl font-bold"><span class="text-blue-500">cube</span>Ladder<small class="text-xs hidden md:inline">v1.7</small></a>
                </div>
                <div class="hidden md:flex items-center space-x-8">

                    <a href="/players" class="hover:text-zinc-500 transition">All Time Ranking</a>
                    <a href="/players/hall_of_fame" class="hover:text-zinc-500 transition">Hall of Fame</a>
                    <a href="/games/index" class="hover:text-zinc-500 transition">Games</a>
                    <a href="/live" class="hover:text-zinc-500 transition">Live</a>
                    <a href="/maps" class="hover:text-zinc-500 transition">Maps</a>
                    <a href="/players/thelast100" class="hover:text-zinc-500 transition">The last 100</a>


                </div>
                <div class="flex items-center gap-4">

                    <!-- Search -->
                    <button class="p-2 hover:text-zinc-500 transition hidden md:inline" id="search-btn">
                        <i class="fas fa-search"></i>
                    </button>

                    <!-- Inbox only if single authenticated player -->
                    <?php if (!empty($authPlayer)): ?>
                        <a class="relative p-2 hover:text-zinc-500 transition"
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
                                ['controller' => 'Players', 'action' => 'profile'],
                                ['escape' => false]
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

                <button class="md:hidden p-2" id="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        <!-- Mobile menu -->
        <div class="md:hidden hidden bg-zinc-900" id="mobile-menu">
            <div class="px-2 pt-2 pb-3 space-y-1">

                <a href="/players" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">All Time Ranking</a>
                <a href="/players/hall_of_fame" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Hall of Fame</a>
                <a href="/games/index" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Games</a>
                <a href="/live" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Live</a>
                <a href="/maps" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Maps</a>
                <a href="/players/thelast100" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">The last 100</a>


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
    <footer class="text-center py-6 text-zinc-600 text-sm">
       <a href="/about" class="hover:text-zinc-300 transition">about</a> . <a href="/players/map" class="hover:text-zinc-300 transition">map</a> <br>
        ©<?= date('Y') ?> by |ZZ

    </footer>

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
