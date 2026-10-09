<?php
/**
 * "What's new" box on the front page. Newest first; the front page shows the
 * latest few. Add an entry here with every change players can notice.
 */
return [
    'Changelog' => [
        [
            'date' => '2026-10-09',
            'title' => 'New inbox',
            'items' => [
                'Weekly achievements arrive as cards: the week, your score in big numbers, and the map in the background for map champions.',
                'New messages are marked "new", and you can reply straight from the inbox.',
                'Fixed the stray "\n" in achievement messages.',
            ],
        ],
        [
            'date' => '2026-10-09',
            'title' => 'Fair play: inaccurate games',
            'items' => [
                'Games where flags were scored against an empty team (everyone else left) no longer count anywhere - rankings, Hall of Fame, achievements.',
                'Such games are marked "not counted" in your recent games; records set in them show as "not qualified".',
                'Old games were checked against the server logs: 7 games were taken out.',
                'Joining a game for less than 3 minutes no longer counts as a game played and no longer sets "best K/D" records (kills and points still count). Every scoreboard now shows the minutes played.',
                'New About page (the ? in the menu) with all rules, points, credits and this changelog.',
            ],
        ],
        [
            'date' => '2026-10-09',
            'title' => 'cubeLadder - a new look',
            'items' => [
                'New logo: a hand-drawn cube in a blue splash, with a ladder leaning on it.',
                'Games look like the in-game scoreboard: full-screen map with the stats on top.',
                'Team games show CLA (red) and RVSF (blue) side by side with the final team score and the winner.',
                'Arrows (or the arrow keys) jump to the previous / next game.',
                'Search is now in the header on phones too.',
                'Live is now an icon next to search that slowly pulses green while a game is on air (on phones it stays in the menu); the Live page shows the running map full-screen behind the scoreboard.',
                'Maps: full-screen map pages with records, top players and weekly winners, plus a map picker with thumbnails to jump to any of the maps played.',
                'Maps: recognised players see their own stats on each map - place, points, games and time, K/D, flags, headshots and best game.',
                'All Time Ranking in the same scoreboard style - click a column to sort by it.',
                'Join our Discord (link on the front page) for live game stats of ladder games and inters.',
                'Player pages: your most played map as background, personal records next to your top 10 nemesis and top 10 prey, weekly achievements and your last-100 stats.',
                'Player pages show your total time played and your time in the last 100 games.',
                'Recent games on your player page look like the game pages - flip through them with the arrows, your row is highlighted.',
                'Live: pick a server from a dropdown with map thumbnails, like on the maps page.',
                'Hall of Fame: one full-screen page per category - flip through with the arrows. It also loads much faster now.',
                'New front page: Hall of Fame record holders and last week\'s achievements at a glance.',
                'Your name in the menu opens your player page; from there "Edit profile" (only from the IP you play from) lets you pick an avatar or choose "Don\'t track me".',
            ],
        ],
        [
            'date' => '2026-09-09',
            'title' => 'Personal records',
            'items' => [
                'Player pages show your best game in every Hall of Fame category.',
            ],
        ],
        [
            'date' => '2026-09-02',
            'title' => 'Live page',
            'items' => [
                'Live server list with a match view per server, kill feed and the busiest server picked for you.',
                'Nemesis stats: who kills you most and whom you kill most.',
            ],
        ],
        [
            'date' => '2026-08-30',
            'title' => 'Kill streaks',
            'items' => [
                'Longest kill streak per game, with its own Hall of Fame category.',
            ],
        ],
    ],
];
