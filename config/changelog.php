<?php
/**
 * "What's new" box on the front page. Newest first; the front page shows the
 * latest few. Add an entry here with every change players can notice.
 */
return [
    'Changelog' => [
        [
            'date' => '2026-10-10',
            'title' => 'All-Rounders with their strongest side',
            'items' => [
                'All-Rounders now show their specialization - Attack (flag play), Defense (returns) or Combat (K/D and frags) - on player pages, in the All Time Ranking and in link previews.',
                'Every game and every map can now have two pictures: games show one or the other, new pictures for Casa, Favela, Kasa, Rapier, Syria, Village and VM Village.',
                'Maps show how often CLA and RVSF win there - on Favela RVSF wins two of three games.',
                'the last 100 got the CTF rating column and the player types too, and the Hall of Fame shows each player\'s rating, rank and type.',
                'Player pages are tidier: the header with your weapons of choice and CTF rating, fun facts right under it, and the rest in three tabs - Overview, Milestones & awards, Games.',
                'Points and K/D are now one chart, oldest to newest game - click a game in it to open it in the recent games below.',
                'Calmer, darker backgrounds on player pages, the front page, the rankings and the Hall of Fame - easier to read.',
                'Player pages show your weapons of choice with big icons and their share of your kills, and the link preview card shows them too - with the cubeLadder logo.',
            ],
        ],
        [
            'date' => '2026-10-09',
            'title' => 'New: milestones and fun facts',
            'items' => [
                'Player pages have a milestone wall: hours, games, days, maps, kills, headshots, knife and grenade kills, flags scored, returned and stolen, wins, win streaks and MVPs - each from Bronze up to Legend, with the date you reached it and how far it is to the next one.',
                'Specials: Marathon, Map addict, Tourist, Untouchable, Hat-trick and Super hat-trick.',
                'Fun facts: how many players and countries you have met, your most played teammate, favourite server and weekday, how often you said gg and more.',
                'Big milestones (Gold and up) are announced in our Discord #results channel.',
                'Weekly achievements on player pages now also list your "Best on map" wins, with the map.',
            ],
        ],
        [
            'date' => '2026-10-09',
            'title' => 'New: CTF rating and player types',
            'items' => [
                'Every player with 20+ CTF games now has a CTF rating from 0 to 10 (5.0 is the average player) on their player page - how much they help their team win, not just how many points they make.',
                'It counts flag play most, then K/D, the team result and discipline - per minute and against the others in the same game. Headshots barely decide CTF games, so they do not count extra.',
                'Your player type: All-Rounder, Flag Runner, Defender, Fragger, or Offensive / Defensive Team Player - plus your favourite weapon.',
                'Every CTF scoreboard shows each player\'s rating for that one game (rtg column).',
                'How it works: About page, "CTF rating & player types".',
            ],
        ],
        [
            'date' => '2026-10-09',
            'title' => 'New inbox',
            'items' => [
                'Weekly achievements arrive as cards: the week, your score in big numbers, and the map in the background for map champions.',
                'New messages are marked "new", and you can reply straight from the inbox.',
                'News from cubeLadder, like the invite to our Discord, shows as its own card.',
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
                'New Discord #results channel: the final scoreboard of every finished game, with the winner, best player, most frags and flags.',
                'See who is on our Discord right now: online members on the front page, the Discord widget on the About page.',
                'Discord live channel: every running game is shown as a scoreboard picture (map, CLA vs RVSF, flags, frags, deaths), with buttons to open the live pages.',
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
