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
- **PHPUnit doesn't run** (checked 2026-10-19): the test bootstrap migrates the SQLite
  test DB and fails in `CreateWebVisits` ("no such table: main.players"). Verify
  changes in the browser until that's fixed.

## Reference docs
- `README.md`: how games are counted and ranked, with code pointers
- `/about` on the live site: rules and points for players
