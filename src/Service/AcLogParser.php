<?php
// src/Service/AcLogParser.php
namespace App\Service;

use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use GeoIp2\Database\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;
use RuntimeException;

/**
 * Highly optimized AssaultCube log parser
 *
 * - Streaming parsing (SplFileObject)
 * - Single compiled ignore regex
 * - Player/Map caches to avoid repeated DB hits
 * - Batched raw SQL inserts for events & player_stats_per_game
 * - GeoIP caching using MaxMind Reader
 */
class AcLogParser
{
    use LocatorAwareTrait;

    // Config
    private const BATCH_SIZE = 500; // flush every 500 rows

    // DB tables (ORM)
    protected $Games;
    protected $Maps;
    protected $Players;
    protected $Events;
    protected $PlayerStatsPerGame;
    protected $Demos;

    // Caches
    protected array $playersCache = [];         // name => id
    protected array $pubkeyCache = [];          // pubkey => id
    protected array $mapsCache = [];            // name => entity
    protected array $geoCache = [];             // ip => iso2
    protected array $statsBuffer = [];          // "gameId:playerId" => stats array

    // AssaultCube default / non-identity names we never turn into player rows.
    // "unarmed" is the client default and is also the killer shown for
    // environmental deaths, so it is shared by countless distinct people.
    protected array $ignoredNames = ['unarmed'];

    // Current game in memory (ORM entity) and map for quick check
    protected $currentGame = null;
    protected $skipCurrentGame = false;
    protected array $currentGamePlayers = [];   // name => player_id

    // Compiled regexes
    protected string $ignoreRegex;
    protected string $killRegex;
    protected string $specialRegex;
    protected string $loginRegex;
    protected string $connectRegex;
    protected string $disconnectRegex;
    protected string $chatRegex;
    protected string $gameStartRegex;
    protected string $gameFinishedRegex;

    // Dates
    protected ?Chronos $currentTimestamp = null;

    private int $year;
    private int $baseMonth;
    private ?int $previousMonth = null;


    // Geo reader
    protected $geoReader = null;

    // Points / weights used to compute total_score

    protected array $pointsMap = [
        'kills' => 1, //any kill
        'busted' => 1, //pistols
        'shredded' => 1, //rifle
        'peppered' => 1,//shotgun
        'sprayed' => 1, //smg
        'punctured' => 1, //sniper
        'splattered' => 1, //shotgun
        'picked_off' => 1, //carabine
        'headshot' => 2, //sniper
        'slashed' => 2, //knife
        'gibbed' => 2, //grenade
        'stole_the_flag' => 2, //good job
        'scored_with_the_flag' => 5, //awesome
        'lost_the_flag' => -1, //pitty
        'returned_the_flag' => 2, //well done
        'teamkills' => -1, //don't do it
        'suicided' => -1, //omg
    ];

    private int $gamesParsed = 0;
    private int $eventsParsed = 0;
    private int $statsParsed = 0;
    private int $lineCount = 0;

    private array $players = [];

    protected string $serverName;

    public function setServerName(string $server): void
    {
        $this->serverName = $server;
    }

    protected function getServerName(): string
    {
        return $this->serverName;
    }
    // Constructor - preload tables, compile regex, load small caches
    public function __construct(int $baseYear, int $baseMonth)
    {
        $this->year = $baseYear;
        $this->baseMonth = $baseMonth;
        $this->previousMonth = $baseMonth;

        $this->Games = $this->fetchTable('Games');
        $this->Maps = $this->fetchTable('Maps');
        $this->Players = $this->fetchTable('Players');
        $this->Events = $this->fetchTable('Events');
        $this->PlayerStatsPerGame = $this->fetchTable('PlayerStatsPerGame');
        $this->Demos = $this->fetchTable('Demos');

        $this->compileRegexes();
        $this->preloadPlayerCache();
        $this->preloadMapCache();
    }


    // -------------------------------
    // parse single line (compat)
    // -------------------------------
    public function parseLine(string $line)
    {
        $this->lineCount++;

        $line = trim($line);
        if ($line === '') return;


        [$ts, $rest] = $this->parseTimestamp($line);

        if ($ts) {
            $this->currentTimestamp = $ts;
        }

        if (!$ts && preg_match($this->gameStartRegex, $rest)) {
            throw new \RuntimeException(
                'Game start without timestamp – refusing to guess'
            );
        }

        // quick ignore test
        if (preg_match($this->ignoreRegex, $rest)) {
            return;
        }

        // Game start
        if (preg_match($this->gameStartRegex, $rest, $m)) {
            $rawMode = $m[1];
            $mode = $this->normalizeGameMode($rawMode);
            $mapName = trim($m[2]);
            $players = (int)$m[3];
            $duration = (int)$m[4];

            // skip tiny games
            if ($players < Configure::read('Ladder.minPlayersToRankGame')) {
                $this->currentGame = null;
                $this->currentGamePlayers = [];
                return;
            }

            $uniqueKey = $mapName . '_' .
                str_replace(' ', '_', ($mode ?? 'unknown')) . '_' .
                $ts->format('Ymd_His');
//echo $ts->format('Ymd_His')."\n";
            // check if exists
            $existing = $this->Games->find()->where(['unique_key' => $uniqueKey])->first();
            $this->skipCurrentGame = false;
            if(!empty($existing->ended_at)){
                $this->skipCurrentGame = true;
                return;
            }
            if ($existing) {
                $this->currentGame = $existing;
                $this->currentGamePlayers = [];
                return;
            }

            // create game via ORM (few writes)
            $mapEntity = $this->getOrCreateMap($mapName);

            $game = $this->Games->newEmptyEntity();
            $game->mode = $mode;
            $game->server_name = $this->getServerName();
            $game->unique_key = $uniqueKey;
            $game->map_id = $mapEntity->id;
            $game->started_at = $ts;
            $game->duration_minutes = $duration;
            $this->Games->save($game);
            $this->gamesParsed++;

            $this->currentGame = $game;
            $this->currentGamePlayers = [];

            $this->eventsParsed++;

            return;
        }

        // client connected
        if (preg_match($this->connectRegex, $rest, $m)) {
            $ip = $m[1];
            $this->eventsParsed++;
            $this->players[$ip] = true;
            return;
        }

        // login with pubkey
        if (preg_match($this->loginRegex, trim($rest), $m)) {
            $ip = $m[1];
            $name = $m[2];
            $pub = $m[3];

            // Never mint a profile for a shared default name (e.g. "unarmed").
            if (in_array(strtolower($name), $this->ignoredNames, true)) {
                $this->eventsParsed++;
                return;
            }

            $player = $this->findOrCreatePlayerCached($name, $pub, $ip, $ts);
            $this->currentGamePlayers[$name] = $player->id;

            $this->eventsParsed++;

            // ensure stats row exists in buffer
            $this->ensurePlayerStatsBuffered($player->id);

            return;
        }

        // disconnect
        if (preg_match($this->disconnectRegex, $rest, $m)) {
            $name = $m[1];
            $seconds = (int)$m[2];
            $pid = $this->getPlayerIdByNameCached($name);
            $this->eventsParsed++;
            return;
        }

        // kill events
// kill events
        if (preg_match($this->killRegex, $rest, $m)) {

            if ($this->skipCurrentGame) {
                return;
            }

            $ip         = $m[1];
            $killerName = trim($m[2]);
            $verb       = strtolower($m[3]);
            $victimRaw  = trim($m[4]);

            $killerIsUnarmed = strtolower($killerName) === 'unarmed';

            // detect teamkill
            $isTeamKill = false;
            $victimName = $victimRaw;

            if (preg_match('/^their teammate (\S+)/i', $victimRaw, $tm)) {
                $isTeamKill = true;
                $victimName = $tm[1];
            }

            $victimIsUnarmed = strtolower($victimName) === 'unarmed';

            // resolve IDs
            $killerId = null;
            if (!$killerIsUnarmed) {
                $killerId = $this->getPlayerIdByNameCached($killerName);
            }

            $victimId = null;
            if (!$victimIsUnarmed) {
                $victimId = $this->getPlayerIdByNameCached($victimName);
            }

            // TEAMKILL
            if ($isTeamKill) {

                if ($killerId) {
                    $this->incrementStatBuffered($killerId, 'teamkills', 1);
                    $this->incrementStatBuffered($killerId, 'kills', -1);
                }

                if ($victimId) {
                    $this->incrementStatBuffered($victimId, 'deaths', 1);
                }

                $this->eventsParsed++;
                return;
            }

            // normalize verb
            $verbCol = str_replace(' ', '_', $verb);

            // ✅ killer stats ONLY if real player
            if ($killerId) {

                // AssaultCube double kill rule
                if (in_array($verbCol, ['headshot','slashed'])) {
                    $this->incrementStatBuffered($killerId, 'kills', 1);
                }

                $this->incrementStatBuffered($killerId, 'kills', 1);

                if (isset($this->pointsMap[$verbCol])) {
                    $this->incrementStatBuffered($killerId, $verbCol, 1);
                    $this->statsParsed++;
                }
            }

            // ✅ victim death ALWAYS if real player
            if ($victimId) {
                $this->incrementStatBuffered($victimId, 'deaths', 1);
            }

            $this->eventsParsed++;

            return;
        }
//        if (preg_match($this->killRegex, $rest, $m)) {
//            if($this->skipCurrentGame){
//                return;
//            }
//            $ip = $m[1];
//            $killerName = trim($m[2]);
//            $verb = strtolower($m[3]);
//            $victimRaw = trim($m[4]);
//
//
//            // teamkill detection
//            $isTeamKill = false;
//            $victimName = $victimRaw;
//
//            if (preg_match('/^their teammate (\S+)/i', $victimRaw, $tm)) {
//                $isTeamKill = true;
//                $victimName = $tm[1];
//            }
//
//            $killerId = $this->getPlayerIdByNameCached($killerName);
//            $victimId = $this->getPlayerIdByNameCached($victimName);
//            if (!$killerId || !$victimId) return;
//
//            if ($isTeamKill) {
//                $this->incrementStatBuffered($killerId, 'teamkills', 1);
//                $this->incrementStatBuffered($killerId, 'kills', -1);
//                $this->incrementStatBuffered($victimId, 'deaths', 1);
//                $this->eventsParsed++;
//                return;
//            }
//
//            // verb mapping (e.g., picked off => picked_off)
//            $verbCol = str_replace(' ', '_', $verb);
//
//            if (in_array($verbCol, ['headshot','slashed'])) {
//                $this->incrementStatBuffered($killerId, 'kills', 1);
//            }
//
//            // normal kill
//            $this->incrementStatBuffered($killerId, 'kills', 1);
//            $this->incrementStatBuffered($victimId, 'deaths', 1);
//
//
//
//            // increment specific verb stat if column exists in pointsMap
//            if (array_key_exists($verbCol, $this->pointsMap) || in_array($verbCol, ['headshot','picked_off','busted','shredded','peppered','sprayed','punctured','splattered','slashed','gibbed'])) {
//                $this->incrementStatBuffered($killerId, $verbCol, 1);
//                $this->statsParsed++;
//            }
//
//            $this->eventsParsed++;
//
//            return;
//        }

        // special events: suicide / flag
        if (preg_match($this->specialRegex, $rest, $m)) {
            if($this->skipCurrentGame){
                return;
            }
            $playerName = trim($m[2]);
            $verb = strtolower($m[3]);

            $playerId = $this->getPlayerIdByNameCached($playerName);
            if (!$playerId) return;

            if (strtolower($playerName) === 'unarmed') {
                return;
            }

            $map = [
                'suicided' => 'suicided',
                'stole the flag' => 'stole_the_flag',
                'scored with the flag' => 'scored_with_the_flag',
                'lost the flag' => 'lost_the_flag',
                'returned the flag' => 'returned_the_flag'
            ];

            if (!isset($map[$verb])) return;
            $statCol = $map[$verb];
            if($statCol=='suicided'){
                $this->incrementStatBuffered($playerId, 'deaths', 1);
                $this->incrementStatBuffered($playerId, 'kills', -1);
            }

            $this->incrementStatBuffered($playerId, $statCol, 1);
            $this->statsParsed++;
            $this->eventsParsed++;

            return;
        }

        // game finished
        if (preg_match($this->gameFinishedRegex, $rest, $m)) {
            if($this->skipCurrentGame){
                return;
            }
            if ($this->currentGame) {
                $this->currentGame->ended_at = $ts;
                $this->Games->save($this->currentGame);
                $this->eventsParsed++;

                // flush stats & events for finished game immediately
                $this->flushStats();

                // reset in-memory game
                $this->currentGame = null;
                $this->currentGamePlayers = [];
            }
            return;
        }

        // periodic status block
        if (strpos($rest, 'Game status:') === 0) {
            $this->eventsParsed++;
            return;
        }

        $this->eventsParsed++;
    }

    // -------------------------------
    // Timestamp parsing
    // -------------------------------
    public function parseTimestamp(string $line): ?array
    {
        // Extract date/time + rest
        if (!preg_match(
            '/^([A-Z][a-z]{2}\s+\d{1,2}\s+\d{2}:\d{2}:\d{2})(?:\s+(.*))?$/',
            $line,
            $m
        )) {
            echo "Failed reading timestamp from: $line";
            return null;
        }

        // Parse timestamp without year
        $dt = Chronos::createFromFormat('M j H:i:s', $m[1]);
        if ($dt === false) {
            echo "Failed creating chronos time from: {$m[1]}";
            return null;
        }

        $month = (int)$dt->month;

        /*
         * CASE 1:
         * First parsed line.
         * If parsed month is smaller than baseMonth,
         * we already crossed into next year.
         */
        if ($this->previousMonth === $this->baseMonth && $month < $this->baseMonth) {
            $this->year++;
        }

        /*
         * CASE 2:
         * Normal rollover inside log stream (Dec -> Jan)
         */
        elseif ($this->previousMonth !== null && $month < $this->previousMonth) {
            $this->year++;
        }

        $this->previousMonth = $month;

        // Build full datetime with corrected year
        $fullDate = Chronos::create(
            $this->year,
            $dt->month,
            $dt->day,
            $dt->hour,
            $dt->minute,
            $dt->second
        );

        return [
            $fullDate,
            $m[2] ?? ''
        ];
    }

    // -------------------------------
    // Stats buffering
    // -------------------------------
    protected function ensurePlayerStatsBuffered(string $playerId): void
    {
        if (!$this->currentGame || $this->currentGame->id === null) {
            return;
        }
        $key = $this->currentGame->id . ':' . $playerId;
        if (!isset($this->statsBuffer[$key])) {
            // try to load existing DB row first (so we preserve previous values if parser runs multiple times)
            $existing = $this->PlayerStatsPerGame->find()
                ->where(['game_id' => $this->currentGame->id, 'player_id' => $playerId])
                ->first();

            if ($existing) {
                // convert entity to simple array
                $arr = (array)$existing->toArray();
                $this->statsBuffer[$key] = $arr;
            } else {
                $this->statsBuffer[$key] = [
                    'game_id' => $this->currentGame->id,
                    'player_id' => $playerId,
                    // init all stat columns we use
                    'kills' => 0, 'teamkills' => 0, 'deaths' => 0, 'headshot' => 0,
                    'busted' => 0, 'shredded' => 0, 'peppered' => 0, 'sprayed' => 0, 'punctured' => 0,
                    'splattered' => 0, 'slashed' => 0, 'gibbed' => 0, 'picked_off' => 0,
                    'suicided' => 0, 'stole_the_flag' => 0, 'lost_the_flag' => 0,
                    'returned_the_flag' => 0, 'scored_with_the_flag' => 0,
                    'total_score' => 0, 'kd_ratio' => 0
                ];
            }
        }
    }

    /**
     * Increment stat in buffer and schedule flush when needed.
     */
    protected function incrementStatBuffered(string $playerId, string $stat, int $amount = 1): void
    {

        if (!$this->currentGame) return;
        $key = $this->currentGame->id . ':' . $playerId;
        $this->ensurePlayerStatsBuffered($playerId);

        if (!isset($this->statsBuffer[$key][$stat])) {
            $this->statsBuffer[$key][$stat] = 0;
        }
        $this->statsBuffer[$key][$stat] += $amount;

        // update derived fields (fast in-memory)
        $this->recalculateTotalScoreInMemory($key);

        // flush if buffer too large
        if (count($this->statsBuffer) >= self::BATCH_SIZE) {
            $this->flushStats();
        }

    }

    protected function recalculateTotalScoreInMemory(string $key): void
    {
        $row = &$this->statsBuffer[$key];
        $total = 0;
        foreach ($this->pointsMap as $col => $pts) {
            $total += ($row[$col] ?? 0) * $pts;
        }
        $row['total_score'] = $total;

        // kd ratio (avoid division by zero)
        $deaths = $row['deaths'] ?? 0;
        $kills = $row['kills'] ?? 0;
        $row['kd_ratio'] = $deaths > 0 ? round($kills / $deaths, 2) : $kills;
    }


    public function flushStats(): void
    {

        if (empty($this->statsBuffer)) return;
        $conn = ConnectionManager::get('default');

        // We'll upsert per row using INSERT ... ON DUPLICATE KEY UPDATE (MySQL) or REPLACE
        // Build multi-row insert for MySQL
        $columns = [
            'game_id','player_id','kills','teamkills','deaths','headshot','busted','shredded',
            'sprayed','punctured','splattered','peppered','slashed','gibbed','picked_off','suicided',
            'stole_the_flag','lost_the_flag','returned_the_flag','scored_with_the_flag',
            'total_score','kd_ratio'
        ];

        $placeholders = [];
        $bindings = [];

        foreach ($this->statsBuffer as $key => $row) {
            $place = [];
            foreach ($columns as $col) {
                $place[] = '?';
                $bindings[] = $row[$col] ?? 0;
            }
            $placeholders[] = '(' . implode(',', $place) . ')';
        }

        $sql = 'INSERT INTO player_stats_per_game (' . implode(',', $columns) . ') VALUES ' . implode(',', $placeholders)
            . ' ON DUPLICATE KEY UPDATE '
            . implode(',', array_map(function ($c) { return "$c=VALUES($c)"; }, $columns));

        try {
            $conn->execute($sql, $bindings);
        } catch (\Exception $e) {
            // fallback to single-row updates to avoid total failure
            foreach ($this->statsBuffer as $row) {
                try {
                    $singleSql = 'INSERT INTO player_stats_per_game (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')'
                        . ' ON DUPLICATE KEY UPDATE ' . implode(',', array_map(function ($c) { return "$c=VALUES($c)"; }, $columns));
                    $conn->execute($singleSql, array_map(fn($c) => $row[$c] ?? 0, $columns));
                } catch (\Exception $ex) {
                    // ignore single failure
                }
            }
        }

        // clear buffer
        $this->statsBuffer = [];
    }

    // -------------------------------
    // Player / Map caching helpers
    // -------------------------------
    protected function preloadMapCache(): void
    {
        foreach ($this->Maps->find()->select(['id','name']) as $m) {
            $this->mapsCache[$m->name] = $m;
        }
    }
    protected function getOrCreateMap(string $name)
    {
        $name = trim($name);
        if (isset($this->mapsCache[$name])) {
            $map = $this->mapsCache[$name];
            $map->times_played = (int)$map->times_played + 1;
            $this->Maps->save($map);
            return $map;
        }

        $map = $this->Maps->newEmptyEntity();
        $map->name = $name;
        $map->times_played = 1;
        $map->cla_wins = 0;
        $map->rvsf_wins = 0;
        $this->Maps->save($map);
        $this->mapsCache[$name] = $map;
        return $map;
    }
    protected function preloadPlayerCache(): void
    {
        // load id & pubkey & name for quick lookups
        foreach ($this->Players->find()->select(['id','name','pubkey']) as $p) {
            $this->playersCache[$p->name] = $p->id;
            if (!empty($p->pubkey)) $this->pubkeyCache[$p->pubkey] = $p->id;
        }
    }

    protected function getPlayerIdByNameCached(string $name): ?string
    {
        return $this->playersCache[$name] ?? null;
    }
    protected function findOrCreatePlayerCached(
        string $name,
        ?string $pubkey = null,
        ?string $ip = null,
        ?Chronos $ts = null
    ) {
        $now = $ts ?? Chronos::now();

        // 1) Identity by pubkey
        if ($pubkey && isset($this->pubkeyCache[$pubkey])) {
            $player = $this->Players->get($this->pubkeyCache[$pubkey]);

            // IMPORTANT: only overwrite if this login is newer
            if (
                !$player->last_seen ||
                $now->getTimestamp() >= $player->last_seen->getTimestamp()
            ) {
                $player->name = $name;
                $player->ip = $ip;
                $player->last_seen = $now;
                $this->Players->save($player);
            }

            // always refresh name cache
            $this->playersCache[$name] = $player->id;

            return $player;
        }

        // 1b) Identity by name for a not-yet-claimed (pubkey-less) profile.
        //
        // A real player can already exist as a name-only row (e.g. imported or
        // created before pubkeys were tracked). Without this step the parser
        // would mint a *second* row the first time that player logs in with a
        // pubkey, which is exactly how the duplicate accounts were created.
        //
        // We intentionally only claim rows that have NO pubkey yet. We never
        // attach this pubkey to a row that already owns a different pubkey, so
        // distinct players that happen to share a name stay separate.
        if ($pubkey) {
            $existing = $this->Players->find()
                ->where([
                    'name' => $name,
                    'OR' => [['pubkey IS' => null], ['pubkey' => '']],
                ])
                ->first();

            if ($existing) {
                $existing->pubkey = $pubkey;
                $existing->ip = $ip;
                if ($existing->country === null) {
                    $existing->country = $this->ipToCountryCached($ip);
                }
                if (
                    !$existing->last_seen ||
                    $now->getTimestamp() >= $existing->last_seen->getTimestamp()
                ) {
                    $existing->last_seen = $now;
                }
                $this->Players->save($existing);

                $this->playersCache[$name] = $existing->id;
                $this->pubkeyCache[$pubkey] = $existing->id;

                return $existing;
            }
        }

        // 1c) Rotating-pubkey fallback.
        //
        // Many AssaultCube clients generate a BRAND-NEW pubkey every session,
        // so a returning player logs in with a pubkey we have never seen. The
        // pubkey cache misses, step 1b misses (their existing rows already own
        // a pubkey), and we would mint yet another duplicate every session.
        //
        // Reuse an existing profile with the SAME name on the SAME /24 subnet:
        // that combination is the same human on a dynamic IP. We deliberately
        // require the subnet match so distinct people who share a common name
        // (e.g. "test") but connect from different networks stay separate. The
        // fresh session key is adopted onto the existing row.
        if ($pubkey && $ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $net = implode('.', array_slice(explode('.', $ip), 0, 3));

            // ip is always a numeric dotted quad, so 'a.b.c.%' matches exactly
            // the /24 without over-matching neighbouring octets.
            $existing = $this->Players->find()
                ->where(function ($exp) use ($name, $net) {
                    return $exp
                        ->eq('name', $name)
                        ->like('ip', $net . '.%');
                })
                ->order(['last_seen' => 'DESC'])
                ->first();

            if ($existing) {
                $existing->pubkey = $pubkey;
                $existing->ip = $ip;
                if ($existing->country === null) {
                    $existing->country = $this->ipToCountryCached($ip);
                }
                if (
                    !$existing->last_seen ||
                    $now->getTimestamp() >= $existing->last_seen->getTimestamp()
                ) {
                    $existing->last_seen = $now;
                }
                $this->Players->save($existing);

                $this->playersCache[$name] = $existing->id;
                $this->pubkeyCache[$pubkey] = $existing->id;

                return $existing;
            }
        }

        // 2) Create new player
        $player = $this->Players->newEmptyEntity();
        $player->name = $name;
        $player->pubkey = $pubkey;
        $player->ip = $ip;
        $player->country = $this->ipToCountryCached($ip);
        $player->first_seen = $now;
        $player->last_seen = $now;

        try {
            $this->Players->save($player);
        } catch (\PDOException $e) {
            // Defensive: the unique index on pubkey blocks a duplicate that a
            // stale cache might otherwise create. Recover by reusing the row
            // that already owns this pubkey instead of failing the parse.
            if ($pubkey) {
                $existing = $this->Players->find()->where(['pubkey' => $pubkey])->first();
                if ($existing) {
                    $this->playersCache[$name] = $existing->id;
                    $this->pubkeyCache[$pubkey] = $existing->id;

                    return $existing;
                }
            }
            throw $e;
        }

        // cache
        $this->playersCache[$name] = $player->id;
        if ($pubkey) {
            $this->pubkeyCache[$pubkey] = $player->id;
        }

        return $player;
    }


    // -------------------------------
    // GeoIP (cached)
    // -------------------------------
    protected function initGeo()
    {
        if ($this->geoReader) return;
        // safe path - adjust if needed
        $path = CONFIG . 'GeoLite2-Country.mmdb';
        if (!file_exists($path)) return;
        $this->geoReader = new Reader($path);
    }

    protected function ipToCountryCached(?string $ip): ?string
    {
        if (!$ip) return null;
        if (isset($this->geoCache[$ip])) return $this->geoCache[$ip];

        try {
            $this->initGeo();
            if (!$this->geoReader) {
                $this->geoCache[$ip] = null;
                return null;
            }
            $record = $this->geoReader->country($ip);
            $code = $record->country->isoCode ?? null;
            $this->geoCache[$ip] = $code;
            return $code;
        } catch (\Exception $e) {
            $this->geoCache[$ip] = null;
            return null;
        }
    }

    // -------------------------------
    // Regex compilation
    // -------------------------------
    protected function compileRegexes(): void
    {
        // Raw pattern fragments for ignore rules (these are regex fragments)
        $patterns = [
            'logging local AssaultCube server',
            'dedicated server started',
            'Ctrl-C to exit',
            'looking up',
            'server description:',
            'server public key:',
            'map permission string:',
            'vote permission string:',
            'maxclients:',
            'master server URL:',
            'anticheat:',
            'holding up to',
            'all recorded demos',
            'added servermap',
            'line\s+\d+:',
            'nickname blacklist',
            'nickname whitelist',
            'blacklist entry .* got dropped',
            '^\s*\d{1,3}(?:\.\d{1,3}){3}\s*$',
            'accept\s+\S+',
            'nickname whitelist \(\d+ entries\):',
            'read \d+.*blacklist entries',
            'reading ip list',
            'reading nickname blacklist',
            'config/',
            'master server registration succeeded',
        ];

        // Escape the chosen delimiter (~) in patterns if present (very unlikely)
        $patterns = array_map(function($p) {
            return str_replace('~', '\\~', $p);
        }, $patterns);

        // Wrap each fragment into a non-capturing group and join with '|'
        $joined = implode('|', array_map(fn($p) => '(?:' . $p . ')', $patterns));

        // Final ignore regex uses ~ as delimiter
        $this->ignoreRegex = '~(?:' . $joined . ')~i';

        // Individual regexes — switch to ~ delimiters (safer, no need to escape /)
        $this->gameStartRegex = '~^Game start:\s*(.+?)\s+on\s+([^,]+),\s*(\d+)\s+players,\s*(\d+)\s+minutes,\s*mastermode\s+(\d+),.*map rev\s+([0-9/\']+)~i';
        $this->connectRegex = '~^\[([0-9a-f:\.]+)\]\s+client connected~i';
        $this->loginRegex = '~^\[([0-9a-f:\.]+)\]\s+([^\s]+)\s+logged in \([^)]*\),\s*AC:\s*\d+\|\d+,\s*pubkey:\s*([0-9a-f]{64})$~i';
        $this->disconnectRegex = '~^disconnected client ([^\s]+) cn \d+,\s*(\d+) seconds played, score saved~i';
        $this->chatRegex = '~^\[([0-9a-f:\.]+)\]\s+(.+?)\s+says:\s+\'(.+)\'$~i';

        $this->killRegex = '~^\[([0-9a-f:\.]+)\]\s+(.+?)\s+'
            . '(sprayed|headshot|punctured|busted|shredded|peppered|gibbed|splattered|slashed|picked off)'
            . '\s+(.+)$~i';

        $this->specialRegex = '~^\[([0-9a-f:\.]+)\]\s+(.+?)\s+(suicided|stole the flag|scored with the flag|lost the flag|returned the flag)(?: for (\w+),.*)?$~i';
        $this->gameFinishedRegex = '~Game status: .*game finished,.* (\d+) clients~i';
        //$this->changeNameRegex = '~^\[([0-9a-f:\.]+)\]\s+([^\s]+)\s+changed name\s+(.+)$/';
    }


    // -------------------------------
    // Utilities / normalization
    // -------------------------------
    protected function normalizeGameMode(string $modeString): ?string
    {
        $modeString = strtolower(trim($modeString));
        $map = [
            'deathmatch' => 'dm',
            'last swiss standing' => 'lss',
            'one shot, one kill' => 'osok',
            'pistol frenzy' => 'pf',
            'survivor' => 'surv',
            'hunt the flag' => 'htf',
            'keep the flag' => 'ktf',
            'team deathmatch' => 'tdm',
            'team last swiss standing' => 'tlss',
            'team one shot one kill' => 'tosok',
            'team pistol frenzy' => 'tpf',
            'team survivor' => 'tsurv',
            'team keep the flag' => 'tktf',
            'capture the flag' => 'ctf',
            'team capture the flag' => 'ctf',
        ];
        return $map[$modeString] ?? null;
    }

    public function getResult(): array
    {
        return [
            'lines'        => $this->lineCount,
            'gamesParsed'  => $this->gamesParsed,
            'eventsParsed' => $this->eventsParsed,
            'statsParsed'  => $this->statsParsed,
            'players'      => array_keys($this->players),
        ];
    }
    /**
     * Flush parser state at end-of-file.
     * Does NOT close unfinished games.
     */
    public function flush(): void
    {
        // Only flush stats buffer
        $this->flushStats();
    }
}
