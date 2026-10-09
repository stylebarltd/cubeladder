# cubeLadder

Rankings, Hall of Fame, maps and live games for [AssaultCube](https://assault.cubers.net/) –
live at **[cubeladder.ovh](https://cubeladder.ovh)**.

The code is public so everybody can check how games are counted and ranked.
Nothing is entered by hand: every number comes from the game servers' own log files.

## How games are counted

| What | Where in the code |
|---|---|
| Reading the server logs (kills, flags, teams, minutes played) | [`src/Service/AcLogParser.php`](src/Service/AcLogParser.php) |
| Points per event (kill +1, headshot/knife/grenade +2, flag +5, teamkill/suicide −1, …) | `$pointsMap` in `AcLogParser.php` – also listed on [/about](https://cubeladder.ovh/about) |
| A game only counts if it **starts with at least 4 players** and is **finished** | `Ladder.minPlayersToRankGame` in `config/app.php`, game start / end in `AcLogParser.php` |
| **Inaccurate games** (5+ flags scored while the other team is empty, e.g. everybody left) count nowhere | `MAX_FREE_FLAGS` in `AcLogParser.php`; left out by `beforeFind()` in [`src/Model/Table/PlayerStatsPerGameTable.php`](src/Model/Table/PlayerStatsPerGameTable.php) and the game windows in [`src/Service/LastGamesTrait.php`](src/Service/LastGamesTrait.php) |
| Joining for **less than 3 minutes** is not a "game played" and sets no best-K/D record (kills and points still count) | `MIN_MINUTES` in `PlayerStatsPerGameTable.php` |
| Rankings: all time (this year, from 5,000 points), the last 100 games (`Ladder.maxGamesToRank`), Hall of Fame (best single game since `HOF_SINCE`) | [`src/Controller/PlayersController.php`](src/Controller/PlayersController.php), [`src/Service/LastGamesTrait.php`](src/Service/LastGamesTrait.php), [`src/Service/HallOfFameService.php`](src/Service/HallOfFameService.php) |
| Weekly achievements (every Sunday) | [`src/Command/CalculateAchievementsCommand.php`](src/Command/CalculateAchievementsCommand.php) |
| Marking a game as inaccurate by hand (logged with a reason, shown on the game page) | [`src/Command/MarkInaccurateCommand.php`](src/Command/MarkInaccurateCommand.php) |

Players are recognised by their AssaultCube login (pubkey), so name changes keep their stats.
Anyone can opt out of tracking on their own player page (*Edit profile → Don't track me*).

## Running it locally

Built with [CakePHP 5](https://cakephp.org) and Tailwind CSS, developed with [DDEV](https://ddev.com):

```bash
ddev start
ddev composer install
cp config/app_local.example.php config/app_local.php   # database, Discord webhooks, Ladder.admins, …
cp .env.local.example .env.local                        # SFTP logins of the game servers (process_logs*.sh)
ddev exec bin/cake migrations migrate
npm install
npx @tailwindcss/cli -i ./webroot/css/app.css -o ./webroot/css/tailwind.css --watch
```

Parse a server log: `bin/cake ProcessLogs <server-key> <file-name> <path-to-log>`
(see `process_logs.sh` for how the live servers are fetched).

Not included in this repository:

- **GeoLite2 databases** (`config/GeoLite2-City.mmdb`, `config/GeoLite2-Country.mmdb`) for player countries –
  free from [MaxMind](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data), but they may not be redistributed.
- **Player avatars and map screenshots** (`webroot/img/players/`, `webroot/img/maps/`) – the site falls back to
  a default picture without them. Map thumbnails: `bin/cake map_thumbs`.
- `config/app_local.php` and `.env.local` with passwords, webhooks and server logins, and database dumps.
