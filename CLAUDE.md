# cubeLadder (cubeladder.ovh)

<!-- This repo is PUBLIC (github.com/stylebarltd/cubeladder). Keep this file
     public-safe: no hostnames, logins, paths on the server or unreleased features.
     Server and deploy details live in Claude's per-machine memory. -->

## What this is
Rankings, Hall of Fame, maps and live games for the AssaultCube community, live at
https://cubeladder.ovh. Every number comes from the game servers' own log files;
nothing is entered by hand. The code is public so players can check how games are
counted (see the table in `README.md`).

## Stack & architecture
- CakePHP 5.2 + Tailwind CSS 4 (CLI build). Local: DDEV project `CubeLadder`
  (https://cubeladder.ddev.site).
- Pipeline: `process_logs.sh` fetches the game-server logs and runs the `bin/cake`
  chain (ProcessLogs → CleanUp → GeoPlayers → PlacementBonus → link_live_stats → …).
  `src/Service/AcLogParser.php` turns logs into games and per-player stats; ratings,
  milestones and achievements build on those; pages and Discord posts read them.
- Players are identified by their AssaultCube login (pubkey), not their name.
- Discord: live server feeds, results, Hall of Fame changes, achievements, welcome
  message, via webhooks configured only in `config/app_local.php`.

## Commands
```bash
ddev start && ddev composer install
cp config/app_local.example.php config/app_local.php   # DB, Discord webhooks, Ladder.admins
cp .env.local.example .env.local                        # game-server SFTP logins for process_logs*.sh
ddev exec bin/cake migrations migrate
npm install
npx @tailwindcss/cli -i ./webroot/css/app.css -o ./webroot/css/tailwind.css --watch
ddev exec bin/cake calculate_ratings      # after each import
ddev exec bin/cake map_thumbs             # after adding map screenshots
```
Other commands in `src/Command/`: achievements, milestones, Discord feeds,
merge players, mark a game inaccurate. Read the source before running one.

## Folder layout
- `src/Service/AcLogParser.php`: log parsing and the points map
- `src/Service/`: ratings, milestones, player cards, live status, Discord
- `src/Command/`: imports, calculations, Discord posting, maintenance
- `config/app.php`: `Ladder.*` rules (min players, games to rank, feature flags)
- `config/changelog.php`: the player-facing "What's new" box

## Gotchas
- **Never commit secrets.** SFTP logins live in `.env.local`, webhooks and admins in
  `config/app_local.php`, keys outside git; all gitignored. Check `git status` before
  every commit.
- **Commit identity** is the repo-local `pola|ZZ <295557460+stylebarltd@users.noreply.github.com>`.
  Keep commits on it.
- **Production has no git** and sometimes runs changes not in the repo. Compare files
  with prod before deploying (details in memory).
- **Every player-visible change gets a dated entry** at the top of `config/changelog.php`,
  written for players.
- **Longest streak:** only recorded for new games, no backfill. The banner is gated by
  `Ladder.streakBanner`.
- GeoLite2 databases and `webroot/img/players|maps` are not in git (licence / size).
- **QR hunt unlocks** are decided in `QrHunt::UNLOCKS` (level = codes found). The explorer avatars
  (`img/players/explorer-NN.jpg`, level 5) and the last avatars (`legend-NN.jpg`, level 10) are built
  by `bin/cake explorer_avatars` from `img/illustrated_players` and `img/legend_players` (their own folder); both
  folders are outside git, so run it
  on every machine. Uploaded pictures (level 7) are `img/players/upload-<random>.jpg`, re-encoded by
  `AvatarUpload`; `QrHunt::isHuntAvatar()` keeps all three out of the plain avatar list and random backgrounds.
- **Cave lighting in the 3D viewer** (`webroot/map3d/light.js`): a map config line `darkzone x0 y0 x1 y1 ambient top`
  makes the SPACE cubes in it dark, lit by LIGHT entities (per-vertex tint on floors, walls and models). Only big maps
  (>= 512) use it, today cl_lost's cave and the inside of cl_cube; every other map renders as before.
- **Map panels and sound** (browser maps only): `webroot/map3d/displays.js` draws `display` lines of a map config
  from `/run/halls` (Hall of Fame top 3 per list + the visitor's own card); `ambience.js` plays `mapsound`/SOUND,
  `synth` and `soundzone` lines (Web Audio, 3D). `tools/map3d/assets.mjs` copies the map's ambience files.
  `stars.js` draws a `stardome` line as a sky of players from `/run/stars` (cached in `rankings`, cleared on import;
  per star only country, games, rating; names only for the top 10 and the visitor). `lidzone` lines mark a stone layer
  (cl_cube's star chamber over its maze) that light and sound do not pass.
- **PHPUnit doesn't run** (checked 2026-10-19): the test bootstrap migrates the SQLite
  test DB and fails in `CreateWebVisits` ("no such table: main.players"). Verify
  changes in the browser until that's fixed.

## Reference docs
- `README.md`: how games are counted and ranked, with code pointers
- `/about` on the live site: rules and points for players
