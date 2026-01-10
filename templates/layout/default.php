<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>
        CubeLadder
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind CSS -->
    <?= $this->Html->css('tailwind') ?>

    <!-- Optional: favicon -->
    <?= $this->Html->meta('icon', '/favicon.ico') ?>

    <!-- Additional head content -->
    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body class="bg-zinc-900 text-white font-mono">
<div class="min-h-screen bg-zinc-950 text-gray font-mono flex flex-col">


    <!-- Navigation -->
    <nav class="bg-zinc-900 text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <a href="/" class="text-3xl font-bold"><span class="text-blue-500">cube</span>Ladder<small class="text-xs">v1</small></a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/players" class="hover:text-zinc-500 transition">Players</a>
                    <a href="/games" class="hover:text-zinc-500 transition">Games</a>
                    <a href="/maps" class="hover:text-zinc-500 transition">Maps</a>

                </div>
                <div class="flex items-center space-x-4">


                    <button class="p-2 hover:text-zinc-500 transition" id="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if ($authPlayer): ?>
                        <a class="p-2 hover:text-zinc-500 transition relative" href="/messages/inbox">
                            <i class="fa fa-inbox"></i>
                            <?php if ($inboxUnread > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-blue-400 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center" id="cart-count"><?= $inboxUnread ?></span>
                            <?php endif; ?>
                        </a>

                        <div><?=h($authPlayer->name) ?></div>


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
                <a href="/players" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Players</a>
                <a href="/games" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Games</a>
                <a href="/maps" class="block px-3 py-2 hover:bg-zinc-600 rounded-md">Maps</a>

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


<?php if ($this->Flash->render()): ?>
    <div class="fixed top-4 right-4 z-50">
        <?= $this->Flash->render() ?>
    </div>
<?php endif; ?>

<!-- Page content -->
<?= $this->fetch('content') ?>
    <!-- Footer -->
    <footer class="text-center py-6 text-zinc-600 text-sm">
        © ZZ|<?= date('Y') ?> . <a href="/about" class="hover:text-zinc-300 transition">about</a>

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
