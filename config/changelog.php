<?php
/**
 * "What's new" box on the front page. Newest first; the front page shows the
 * latest few. Add an entry here with every change players can notice.
 */
return [
    'Changelog' => [
        [
            'date' => '2026-10-27',
            'title' => 'cl_angkorwat - the first official cubeLadder map',
            'items' => [
                'Our first own map is out: **cl_angkorwat**, a CTF map in the ruins of Angkor Wat. Five temple towers, vaulted hallways, sunken courtyards, a flooded pool, and the jungle taking it all back. Only on the **aCKa servers**.',
                'Both bases on the upper floor, red in the south-west tower, blue in the north-east. Three lanes: through the courtyards and the great hall, over the north-west tower, or down to the ground floor and back up. Sniper balconies in the great hall, ladders onto the roofs.',
                '**Install the model pack once** - a server only sends the map, not its models: download from [github.com/stylebarltd/cl_angkorwat](https://github.com/stylebarltd/cl_angkorwat) and unzip `cubeladder_khmer_pack.zip` into `packages/models/mapmodels/`. Without it the roofs and towers are missing.',
                '**QR hunt:** two QR codes are hidden in the temple. Find one, scan it off your screen with your phone and you level up on cubeLadder. Every code is a point: 1 point unlocks your own colour and tagline on your share card, 2 points a short text about yourself on your player page. More unlocks with every new map - 10 codes in total. (The code on the cubeLadder logo cube just links to the site.)',
            ],
        ],
        [
            'date' => '2026-10-27',
            'title' => 'QR codes unlock things',
            'link' => '/players/profile',
            'items' => [
                'Every hidden QR code you find in our maps is a point, and points unlock things on your profile. 1 point: pick the colour of your share card and add a tagline after your name. 2 points: write a short text about yourself on your player page. More unlocks come with more maps - your profile shows them all.',
                'Editing your profile works from anywhere now: if cubeLadder does not recognise your connection, your profile page shows a code - say it in a game and that browser is yours for a year.',
            ],
        ],
        [
            'date' => '2026-10-27',
            'title' => 'Something is hidden in cl_angkorwat',
            'items' => [
                'Two QR codes are hidden in the temple. Scan one with your phone and you level up on cubeLadder: your level shows as a badge on your profile, and the QR hunters list who found what first. Not part of the ladder - just for the explorers. The cubeLadder logo\'s code is not one of them.',
            ],
        ],
        [
            'date' => '2026-10-26',
            'title' => 'Privacy: less data, and a privacy page',
            'link' => '/privacy',
            'items' => [
                'cubeLadder no longer keeps the game chat. Only a link code like cl-48213 is saved, and only for a day. Because of that, the "said gg" count is gone from player pages.',
                'Old server logs are deleted after 30 days, your IP address 90 days after your last game, and inbox messages after 12 months.',
                'The site no longer loads anything from other websites until you click: the Discord widget on the About page and the map background on the player map wait for your click.',
                'Everything is explained on the new privacy page: the shield icon at the top of every page.',
            ],
        ],
        [
            'date' => '2026-10-26',
            'title' => 'Aim trainer says "guest" although you play here?',
            'link' => '/play',
            'items' => [
                'Some connections (VPNs, some internet providers, phones) reach the website from a different address than the game, so cubeLadder could not tell who you are. The aim trainer page now shows you a code like cl-48213: say it in the chat of any game on a cubeLadder server, and within about 10 minutes the page recognises you by itself - on that browser for a year, also on your phone.',
            ],
        ],
        [
            'date' => '2026-10-25',
            'title' => 'New servers on the ladder: the Vortex CTF servers',
            'link' => '/live',
            'items' => [
                'Games on Vortex CTF USA West, USA East, Brazil, Germany and Singapore count for the ladder from today on. The servers are on the Live page too.',
            ],
        ],
        [
            'date' => '2026-10-24',
            'title' => 'Aim trainer: weekly achievement and Hall of Fame',
            'link' => '/players/hall_of_fame/aim-trainer',
            'items' => [
                'The best aim trainer round of the week is a weekly achievement now, like Most points or Best K/D: a Sharpshooter badge next to your name for a week, a message in your inbox and a line in the Discord achievements post.',
                'The all-time top 10 is a Hall of Fame category (/players/hall_of_fame/aim-trainer) with its own share card, and your best round sits with your personal records on your player page. Entering the top 10 or beating your own record makes the Hall of Fame news in Discord.',
                'After a round the result tells you where you stand all time, not only this week.',
                'The pop-up names the streak bonus ("+30 HEADSHOT + STREAK"), and the points per hit are in a table next to the boards.',
                'On a phone the page shows the boards and the points only - the game itself is played with a mouse.',
            ],
        ],
        [
            'date' => '2026-10-21',
            'title' => 'Share the aim trainer',
            'link' => '/play',
            'items' => [
                'Post cubeladder.ovh/play in Discord or WhatsApp and the link shows its own card: the all-time top 10 and this week\'s leader to beat. Free places say "your name here?" - go take one.',
            ],
        ],
        [
            'date' => '2026-10-21',
            'title' => 'Aim trainer',
            'link' => '/play',
            'items' => [
                'Waiting for a server to fill up? 40 seconds of targets on our maps: head 20, body 10, every 5 hits in a row +10. The best round of the week and of all time go on a board - just for fun, not part of the ladder.',
                '5 grenades per round (right-click or G, on a phone tap the grenade): 15 per gib, +10 for every further gib of the same grenade - wait until they stand close together.',
                'With sound: a pistol shot for every hit, "Headshot!" for every headshot and an explosion for every grenade - the button under the game turns it off.',
            ],
        ],
        [
            'date' => '2026-10-21',
            'title' => 'Weapon icons and Hall of Fame pictures',
            'items' => [
                'The live kill feed shows the weapon of every kill, and its own icon for headshots, knife kills, gibs, teamkills and flags. Players are in their team\'s colour: CLA red, RVSF blue.',
                'Hall of Fame news in Discord now comes with a picture: the new top 10 with every value, the players the news is about highlighted. Flag Helper also shows how many steals and returns made the score.',
                'Every Hall of Fame category has its own link now (e.g. /players/hall_of_fame/flag-helper) and a Share button: the link shows the category\'s top 10 as a picture in Discord, WhatsApp & co.',
            ],
        ],
        [
            'date' => '2026-10-20',
            'title' => 'Live: win chance, highlights and the final whistle',
            'link' => '/live',
            'items' => [
                'Win chance on live CTF games: a bar above the teams, from the teams\' ratings and the flag lead - early on a lead means little, in the last minutes it decides. How it works is on the About page.',
                'Highlights while you watch: hat-tricks, a map\'s flag record in danger or broken, career milestones in reach - and who is on fire (5+ frags without dying).',
                'Rating and rank next to every player in the live scoreboard; hover for their player type.',
                'When a game ends: the winner big, the top player, and an upset badge when the weaker team won.',
            ],
        ],
        [
            'date' => '2026-10-20',
            'title' => 'See who\'s watching, and other AC servers',
            'link' => '/live',
            'items' => [
                'Live match pages now show how many people are following the game on cubeladder.ovh right now, you included - players who are logged in by name, everybody else as guests. Spectators in the game are listed separately, as before.',
                'The "this game on the ladder" link on a live match no longer points to the previous round when the same map is played again.',
                'Other AC servers: the Live page now lists the other public AssaultCube servers too, busiest first - also the empty ones, like the server browser in the game.',
            ],
        ],
        [
            'date' => '2026-10-19',
            'title' => 'Keep the flag, name changes and B[] New York',
            'items' => [
                'Your flags now come straight from the server\'s scoreboard in every flag mode, so they match what you saw in the game. Keep the flag: every 15 seconds you carry the flag is a flag (before, a whole carry was one). Hunt the flag: a hunt while you carry the flag counts 2, as on the server. Each flag is still worth 5 points.',
                'Most Flags scored (Hall of Fame, map records and the weekly award) counts capture the flag games only - a hunt or 15 seconds of carrying isn\'t a capture.',
                'Changing your name during a game no longer loses your stats from that moment on - also when you joined as "unarmed" and picked your name later.',
                'Games on the B[] New York server showed up 6 hours too early - its log runs on New York time. They now sit at the right time between the other servers\' games, and get their accuracy and damage too.',
                'Leaving during the end-of-game scoreboard no longer takes your flags and frags out of your team\'s result. The server drops whoever left from its final totals, so a won game could show up as lost.',
            ],
        ],
        [
            'date' => '2026-10-15',
            'title' => 'Trends and the busiest hours',
            'link' => '/stats',
            'items' => [
                'Community stats: this week so far against last week, players and games week by week, and how many newcomers come back.',
                'A heatmap of every day and hour, in your own time zone: see when the servers are full before you join.',
            ],
        ],
        [
            'date' => '2026-10-15',
            'title' => 'Community stats',
            'link' => '/stats',
            'items' => [
                'Where do all those players on the front page come from? Click the numbers: the new stats page shows how many play regularly, how many are online at once by time of day, month by month and by server.',
            ],
        ],
        [
            'date' => '2026-10-15',
            'title' => 'Accuracy',
            'items' => [
                'Game scoreboards show each player\'s accuracy (acc): the damage you did out of the damage your shots could have done, as the server counts it. The damage itself has its own column (dmg) on wide screens.',
                'Player pages show your accuracy over all games with it - hover for your damage per minute.',
                'It comes from the live view, so it starts with today\'s games, and clan matches (match mode) have none - the server keeps it secret there.',
            ],
        ],
        [
            'date' => '2026-10-15',
            'title' => 'Minute by minute',
            'items' => [
                'Game pages: the score over the whole game as a chart, and a slider to see the scoreboard of every minute - who scored when, and everyone\'s ping.',
                'Game pages also list the votes of the game: map, kick, ban, shuffle and mastermode votes, who called them and whether they passed.',
                'Player pages: your average ping over the last 90 days (hover it for each server).',
                'Hunt the flag: hunting the flag now counts as a flag scored (5 points), like it shows in the game. Getting their flag carrier while your team doesn\'t have their flag on a player ("failed to score") gives 1 point.',
                'New games only - the servers\' minute scoreboards were not kept before.',
            ],
        ],
        [
            'date' => '2026-10-12',
            'title' => 'B[] New York joins the ladder!',
            'items' => [
                "Barry's | B[] | New York server is now a ladder server: games there count for the ranking, Hall of Fame, achievements and milestones, just like aCKa, Banana and POTATO.",
                'Our first server in America - good ping for players from North and South America.',
                'Join: /connect 107.173.23.18 28763 - or find it on the Live page.',
                'Thanks to Barry for bringing it back!',
            ],
        ],
        [
            'date' => '2026-10-12',
            'title' => 'Merge your old profile',
            'items' => [
                'Two profiles of yours (another PC, a reinstall)? Open the old one and click "This was my profile" - an admin merges it into your current profile with all games, points and awards, and you get a message when it is done.',
                'After the merge both PCs log in to the same profile. Links to the old profile lead to the new one.',
            ],
        ],
        [
            'date' => '2026-10-12',
            'title' => 'Points fix for March',
            'items' => [
                'Games played 9 - 27 March 2026 counted headshots and knife kills as 1 point instead of 2. Fixed: 8,642 points added back to 260 players.',
            ],
        ],
        [
            'date' => '2026-10-12',
            'title' => 'Placement bonus',
            'items' => [
                'Free-for-all games (DM, OSOK, LSS, KTF, PF, survivor): 1st place gets +20 points, 2nd +15, 3rd +10. 1st and 2nd need 4+ players, 3rd 5+. Counted for all earlier games too.',
                'The scoreboard shows the bonus next to the points, e.g. 112 +20 - see About.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Game cards',
            'items' => [
                'Games is now a page of game cards, last played first: the map, the result, the top 3 players, server, players and minutes - 50 per page. Click a card to open the game.',
                'On a game page the arrows (and the ← → keys) go on in the same order: ← the newer game, → the older one.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Ranking by rates',
            'items' => [
                'All Time Ranking: the stat columns are now rates instead of totals, so they show who is good, not only who plays the most - kills per 10 minutes, headshot / grenade / knife kills in % of your kills, flags per hour, teamkills per 100 kills (fewest first) and points per game. The total stays small under each number.',
                'The "Totals" switch next to the region filter shows the year\'s totals as before. Sorting by a rate needs 5 hours played this year.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Share a game',
            'items' => [
                'Game pages: a "Share result" button, with a preview of the result picture - copy the link (Discord shows the scoreboard), WhatsApp, Telegram, X, Facebook, Reddit or download the picture.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Browse the players',
            'items' => [
                'Player pages: arrows on the left and right to the previous and next player by CTF rating - hover for their picture, name, flag and rank. Also the ← and → keys (on the Games tab they still flip through the games).',
                'The name and flag now sit in one row above the picture.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Skill trend',
            'items' => [
                'The skill rating now has a trend arrow, like the CTF rating: ▲ when your last 10 games are clearly better than your last 100, ▼ when they are worse. In the rankings, on the player page, the hover card and the shared card.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Upsets and giant slayers',
            'items' => [
                'Game pages and the Discord results: the "Upset of the game" - when a much weaker player (3+ skill lower) wins the duel against a much stronger one, e.g. 5-2 in kills on each other.',
                'Player pages: "Giant slayer" - your duels won against much stronger players, the biggest ones first.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Skill rating',
            'items' => [
                'New: the skill rating (0-10) - your individual rating in every mode, next to the CTF rating. It counts your kills (headshot, knife and grenade kills a bit more) and your K/D most, plus flags (scored, taken, returned), kill streaks, a little team wins and how strong the lobby was. See About.',
                'Rankings: a new "Skill" column - sort by it to see the best fighters. Also on the player page, the hover card and the shared player card.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Share your card',
            'items' => [
                'Player pages: a "Share card" button - copy the link for Discord, or share to WhatsApp, Telegram, X, Facebook and Reddit, or download the card. On phones it opens the share menu with the card picture.',
                'Rankings and Hall of Fame: your character shows as a small emblem next to your name - hover it for the full title.',
                'All Time Ranking, Hall of Fame, map and game pages: hovering a name now shows a mini player card in the style of the shared card - tier frame, character, rating and stats.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'More weapon icons',
            'items' => [
                'Weapons: a second gun now shows from 40% of your main gun\'s kills (was 65%), knife and grenade from 12% (was 20%) - more players get more than one weapon. Shotgun "peppered" kills now count too.',
                'Pistol kills now count for the weapon icons too - a pistol-heavy game shows the pistol.',
            ],
        ],
        [
            'date' => '2026-10-11',
            'title' => 'Characters and epic player cards',
            'items' => [
                'Characters: the top 10% in headshots, knife kills, grenade kills, pistol kills or kill streaks become Headhunter, Butcher, Bomber, Pistolero or Berserker - with an emblem on their player page and card. The #1 in each is "The Butcher" & co.',
                'Player cards now get more epic with the CTF rating: Silver from 5.0, Gold from 6.0, Diamond from 7.0 and Legend from 8.0 - frames, glow, light rays and sparkles.',
            ],
        ],
        [
            'date' => '2026-10-10',
            'title' => 'New player cards',
            'items' => [
                'Sharing a player page in Discord, WhatsApp & co. now shows a new card: CTF rating and trend, type, rank, weapons of choice on the favourite map, attack / defense / combat and the totals - kills, flags, wins and MVPs.',
            ],
        ],
        [
            'date' => '2026-10-10',
            'title' => 'Map rankings explained',
            'items' => [
                'Map pages: the best CTF rating list counts players with 10 rated CTF games on that map - your own stats show how many you have, e.g. "5 of 10 games to be ranked".',
                'About: the points table shows the weapon of every kill type.',
            ],
        ],
        [
            'date' => '2026-10-10',
            'title' => 'Fairer games and a quieter Discord',
            'items' => [
                'Fairer stats: team games where a team had one player or none for half of the game no longer count - 362 earlier games are marked "not counted".',
                'Keep the flag: one flag per carry - holding the flag longer does not add more.',
                'Only one post still makes a sound: when a game starts while all ladder servers were empty - time to join!',
                'Game results, milestones, Hall of Fame news, weekly achievements and site updates now arrive silently.',
                'Milestone posts now show the player\'s card.',
            ],
        ],
        [
            'date' => '2026-10-10',
            'title' => 'Only active players in the rankings',
            'items' => [
                'Players without a game in the last 90 days are inactive: they leave the All Time Ranking, the CTF rating and the map ratings, and are back with their next game.',
                'Their Hall of Fame records stay, marked inactive - and their player pages say so too.',
                'Keep the flag and team keep the flag games now count every score as a flag scored - earlier games got their flags filled in.',
            ],
        ],
        [
            'date' => '2026-10-10',
            'title' => 'New player pages, hover cards and the best by country',
            'items' => [
                'All-Rounders now show their specialization - Attack (flag play), Defense (returns) or Combat (K/D and frags) - on player pages, in the All Time Ranking and in link previews.',
                'Every game and every map can now have two pictures: games show one or the other, new pictures for Casa, Favela, Kasa, Rapier, Syria, Village and VM Village.',
                'Maps show how often CLA and RVSF win there - on Favela RVSF wins two of three games.',
                'the last 100 got the CTF rating column and the player types too, and the Hall of Fame shows each player\'s rating, rank and type.',
                'Player pages are tidier: the header with your weapons of choice and CTF rating, fun facts right under it, and the rest in three tabs - Overview, Milestones & awards, Games.',
                'Points, K/D and your CTF rating are now one chart, oldest to newest game - kills and flags can be switched on in its legend, and a click on a game opens it in the recent games below.',
                'Last week\'s best (Best K/D, Most flags, Top scorer ...) now show as blue badges next to the player\'s flag in the rankings, the Hall of Fame and on player pages.',
                'Maps: your own stats now show your average CTF rating on that map, compared with your overall rating - and hovering any player on a map page shows the same for them, with their CTF rating and type.',
                'Maps: the top 12 is now ranked by CTF rating on that map (from 10 rated games) instead of total points.',
                'Rankings: hover a player\'s name for a small player card - CTF rating, type, attack / defense / combat, weapons of choice and totals.',
                'All Time Ranking: filter by continent or country - who is the best in Europe, in South America, in Brazil? (from 1,000 points this year)',
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
