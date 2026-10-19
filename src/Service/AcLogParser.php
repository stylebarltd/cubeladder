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

    /** killer|victim => ['kills' => n, 'teamkills' => n] for the current game (kill_pairs) */
    protected array $killBuffer = [];

    /** buffered chat rows for the events table (type 'chat') */
    protected array $chatBuffer = [];

    /**
     * Canned in-game voice commands (bound to keys, not typed) that are
     * not worth recording as chat. Compared case-insensitively.
     */
    public const IGNORED_CHAT = [
        'thanks', 'thanks!', 'thanks, man', 'nice shot', 'awesome', 'awesome!', 'gg',
        'stay together!', "there's no way, sir!", 'spread out!', 'come on, move!',
        'haha', 'recover the flag!', 'negative', 'affirmative', 'i made contact',
        "i'm on your team, man!", 'we did it!', 'sorry', 'report in!',
    ];

    /** chat messages shorter than this (in characters) are not recorded */
    public const MIN_CHAT_LENGTH = 4;

    /** Whether a chat line is worth recording as an event. */
    public static function isChatWorthRecording(string $msg): bool
    {
        $msg = trim($msg);

        return mb_strlen($msg) >= self::MIN_CHAT_LENGTH
            && !in_array(mb_strtolower($msg), self::IGNORED_CHAT, true);
    }
    protected $PlayerStatsPerGame;
    protected $Demos;

    // Caches
    protected array $playersCache = [];         // name => id
    protected array $pubkeyCache = [];          // pubkey => id
    protected array $mapsCache = [];            // name => entity
    protected array $geoCache = [];             // ip => iso2
    protected array $statsBuffer = [];          // "gameId:playerId" => stats array
    protected array $streakBuffer = [];         // playerId => current kill streak in the running game

    // AssaultCube default / non-identity names we never turn into player rows.
    // "unarmed" is the client default and is also the killer shown for
    // environmental deaths, so it is shared by countless distinct people.
    protected array $ignoredNames = ['unarmed'];
    // IP => pubkey of its latest login, for name changes after it
    protected array $ipPubkeys = [];

    // Current game in memory (ORM entity) and map for quick check
    protected $currentGame = null;
    protected $skipCurrentGame = false;
    protected array $currentGamePlayers = [];   // name => player_id

    // Players per team from the latest "Game status" block (CLA/RVSF => n)
    // and flags scored while the other team was empty. A game with too many
    // such free flags (everyone else left) is marked inaccurate at its end.
    protected array $teamPlayers = [];
    protected int $freeFlags = 0;
    // minute status blocks of a team game, and those where a team had one
    // player or none: too many of them (UNEVEN_SHARE) and the game is marked
    // inaccurate too
    protected int $statusBlocks = 0;
    protected int $unevenBlocks = 0;

    // Game whose "game finished" line was just seen: the final status block
    // (team totals) follows it and is saved to its team_scores.
    protected $finishedGame = null;
    protected array $finalTeamScores = [];
    // The final block lists only who is still connected: players who leave
    // during the intermission (after the result is decided) keep their last
    // minute's row in their team's total. Name => that row (timeline format).
    protected array $lastRows = [];
    protected array $intermissionLeavers = [];
    protected bool $intermission = false;
    // Scoreboard of every minute of the game (game_timelines): the columns of
    // the latest "cn name team flag ..." header, the snapshots and which game
    // they belong to (the finished game keeps its final block)
    protected ?array $statusCols = null;
    protected array $timeline = [];
    protected $timelineGame = null;

    // Far flag touches / pickups and kill bursts (events type 'anticheat')
    protected CheatSignals $cheatSignals;

    // Votes called and waiting for their result (map loaded, player kicked ...)
    protected array $pendingVotes = [];
    /** A vote without a visible result after this many seconds failed */
    public const VOTE_TIMEOUT = 90;
    /** Pings at or above this (lag, 9999 = unknown) don't count for avg_ping */
    public const MAX_PING = 1000;

    public const MAX_FREE_FLAGS = 4;
    /** Share of the minutes with a team of one player or none that makes a team game inaccurate */
    public const UNEVEN_SHARE = 0.5;
    /** ...counted from this many minute status blocks on */
    public const UNEVEN_MIN_BLOCKS = 3;

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
    protected string $voteRegex;

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
        'failed_to_score' => 1, //hunt the flag: got the enemy flag carrier while their flag wasn't on one of ours
        'teamkills' => -1, //don't do it
        'suicided' => -1, //omg
    ];

    private int $gamesParsed = 0;
    private int $eventsParsed = 0;
    private int $statsParsed = 0;
    private int $lineCount = 0;

    private array $players = [];

    protected string $serverName;
    // Time zone of the server's log when it isn't the one all game times are
    // stored in (Ladder.servers.<key>.logTz, e.g. a server in New York)
    protected ?\DateTimeZone $logTz = null;

    public function setServerName(string $server): void
    {
        $this->serverName = $server;
        $logTz = Configure::read("Ladder.servers.$server.logTz");
        $this->logTz = $logTz ? new \DateTimeZone($logTz) : null;
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

        $this->cheatSignals = new CheatSignals();
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
        $rest = (string)$rest; // timestamp-only lines (blank status block lines)

        if ($ts) {
            $this->currentTimestamp = $ts;
            $this->expireVotes($ts);
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

            $this->teamPlayers = [];
            $this->lastRows = [];
            $this->intermissionLeavers = [];
            $this->intermission = false;
            $this->freeFlags = 0;
            $this->statusBlocks = 0;
            $this->unevenBlocks = 0;
            $this->finishedGame = null;

            // the previous game's timeline is complete (its final block included)
            $this->saveTimeline();
            $this->timeline = [];
            $this->timelineGame = null;
            $this->resolveMapVotes($rawMode, trim($m[2]), $ts);
            $this->cheatSignals->reset();

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
                $this->killBuffer = [];
                $this->timelineGame = $existing; // rebuilt from its start
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
            $this->streakBuffer = [];
            $this->killBuffer = [];
            $this->timelineGame = $game;

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

        // name change: "[ip] unarmed changed name to kpzohua" - the new name
        // belongs to the profile of the key that logged in from that IP
        if (preg_match('~^\[([0-9a-f:\.]+)\]\s+\S+ changed name to (\S+)$~', $rest, $m)) {
            $pub = $this->ipPubkeys[$m[1]] ?? null;
            if ($pub && !in_array(strtolower($m[2]), $this->ignoredNames, true)) {
                $player = $this->findOrCreatePlayerCached($m[2], $pub, $m[1], $ts);
                $this->currentGamePlayers[$m[2]] = $player->id;
                if (!$this->skipCurrentGame) {
                    $this->ensurePlayerStatsBuffered($player->id);
                }
            }
            $this->eventsParsed++;
            return;
        }

        // login with pubkey
        if (preg_match($this->loginRegex, trim($rest), $m)) {
            $ip = $m[1];
            $name = $m[2];
            $pub = $m[3];
            // for a later name change (also from the default name)
            $this->ipPubkeys[$ip] = $pub;

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

        // votes: "[ip] client X called a vote: load map 'ac_casa' in mode 'capture the flag'"
        if (preg_match($this->voteRegex, $rest, $m)) {
            $this->recordVote(trim($m[2]), $m[3] === 'failed to call', $m[4], $ts);
            $this->eventsParsed++;
            return;
        }

        // the server's far flag touch / item pickup warnings
        if ($ts && ($signal = $this->cheatSignals->farPickup($rest, $ts->getTimestamp()))) {
            $this->queueCheatSignal($signal, $ts);
            $this->eventsParsed++;
            return;
        }

        // kicked / banned by vote: the vote against them passed
        if (preg_match('~^\[[0-9a-f:\.]+\]\s+disconnecting client (\S+) \((kicked|banned) by vote\)~i', $rest, $m)) {
            $this->resolveVote(fn($v) => in_array($v['kind'], ['kick', 'ban'], true) && $v['target'] === $m[1], 'passed');
            $this->eventsParsed++;
            return;
        }

        if (str_contains($rest, 'demorecord_beginintermission')) {
            $this->intermission = true;
            $this->eventsParsed++;
            return;
        }

        // disconnect
        if (preg_match($this->disconnectRegex, $rest, $m)) {
            $name = $m[1];
            $seconds = (int)$m[2];
            $pid = $this->getPlayerIdByNameCached($name);
            // left during the intermission: still counts for the team's result
            if ($this->currentGame && !$this->skipCurrentGame && isset($this->lastRows[$name]) && $this->inIntermission($ts)) {
                $this->intermissionLeavers[$name] = $this->lastRows[$name];
            }
            $this->eventsParsed++;
            return;
        }

        // chat: [ip] name says: 'message' - saved to events (type "chat").
        // Checked before the kill regex so a message quoting a kill verb
        // is not mistaken for a kill line.
        if (preg_match($this->chatRegex, $rest, $m)) {
            $name = trim($m[2]);
            $msg = trim($m[3]);
            if (strtolower($name) !== 'unarmed' && self::isChatWorthRecording($msg)) {
                $this->chatBuffer[] = [
                    'game_id' => $this->currentGame->id ?? null,
                    'event_time' => $ts ? $ts->format('Y-m-d H:i:s') : null,
                    'event_hash' => md5($this->getServerName() . '|' . ($ts ? $ts->format('c') : '') . '|' . $name . '|' . $msg),
                    'type' => 'chat',
                    'actor_id' => $this->getPlayerIdByNameCached($name),
                    'details' => json_encode(['name' => $name, 'msg' => $msg], JSON_UNESCAPED_UNICODE),
                ];
                if (count($this->chatBuffer) >= 200) {
                    $this->flushChats();
                }
            }
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

            // many kills within seconds (teamkills left out)
            if ($ts && !str_starts_with(strtolower($victimRaw), 'their teammate ') && !in_array(strtolower($killerName), $this->ignoredNames, true)
                && ($signal = $this->cheatSignals->kill($killerName, $verb, $ts->getTimestamp()))) {
                $this->queueCheatSignal($signal, $ts);
            }

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

            // who killed whom (nemesis stats) - one entry per actual kill
            if ($this->currentGame && $killerId && $victimId && $killerId !== $victimId) {
                $key = $killerId . '|' . $victimId;
                $this->killBuffer[$key] ??= ['kills' => 0, 'teamkills' => 0];
                $this->killBuffer[$key][$isTeamKill ? 'teamkills' : 'kills']++;
            }

            // TEAMKILL
            if ($isTeamKill) {

                if ($killerId) {
                    $this->incrementStatBuffered($killerId, 'teamkills', 1);
                    $this->incrementStatBuffered($killerId, 'kills', -1);
                }

                if ($victimId) {
                    $this->incrementStatBuffered($victimId, 'deaths', 1);
                    $this->breakStreak($victimId);
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

                $this->extendStreak($killerId);
            }

            // ✅ victim death ALWAYS if real player
            if ($victimId) {
                $this->incrementStatBuffered($victimId, 'deaths', 1);
                $this->breakStreak($victimId);
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
            if (str_starts_with($verb, 'scored, carrying')) {
                // keep the flag scores every 15 s of carrying: each score is a flag,
                // like on the server's scoreboard
                $verb = 'scored with the flag';
            }
            // hunt the flag: bringing your flag home while yours hunt theirs is the team's flag score
            if ($verb === 'hunted the flag') {
                $verb = 'scored with the flag';
            }

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
                'returned the flag' => 'returned_the_flag',
                // picking a dropped flag up again and dropping it on purpose: counted
                // only; failed to score (hunt the flag: got their carrier, but the team
                // didn't hold their flag): 1 point
                'picked up the flag' => 'picked_up_the_flag',
                'dropped the flag' => 'dropped_the_flag',
                'failed to score' => 'failed_to_score',
            ];

            if (!isset($map[$verb])) return;
            $statCol = $map[$verb];
            if ($statCol === 'scored_with_the_flag') {
                $team = strtoupper($m[4] ?? '');
                $opponent = ['CLA' => 'RVSF', 'RVSF' => 'CLA'][$team] ?? null;
                if ($opponent && ($this->teamPlayers[$opponent] ?? null) === 0) {
                    $this->freeFlags++;
                }
            }
            if($statCol=='suicided'){
                $this->incrementStatBuffered($playerId, 'deaths', 1);
                $this->incrementStatBuffered($playerId, 'kills', -1);
                $this->breakStreak($playerId);
                // (team) keep the flag: the server takes a flag off for a suicide
                $key = ($this->currentGame->id ?? '') . ':' . $playerId;
                if (in_array($this->currentGame->mode ?? null, ['ktf', 'tktf'], true)
                    && (int)($this->statsBuffer[$key]['scored_with_the_flag'] ?? 0) > 0) {
                    $this->incrementStatBuffered($playerId, 'scored_with_the_flag', -1);
                }
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
                if ($this->freeFlags > self::MAX_FREE_FLAGS) {
                    $this->currentGame->inaccurate = true;
                    $this->currentGame->inaccurate_reason =
                        "{$this->freeFlags} flags scored against an empty team";
                } elseif (self::unevenGame($this->statusBlocks, $this->unevenBlocks)) {
                    $this->currentGame->inaccurate = true;
                    $this->currentGame->inaccurate_reason = sprintf(
                        'a team with one player or none for %d of %d minutes',
                        $this->unevenBlocks, $this->statusBlocks);
                }
                $this->Games->save($this->currentGame);
                $this->eventsParsed++;

                // flush stats & events for finished game immediately
                $this->applyAvgPings();
                $this->flushStats();
                $this->flushKills();
                $this->flushChats();

                $this->finishedGame = $this->currentGame;
                $this->finalTeamScores = [];

                // reset in-memory game
                $this->currentGame = null;
                $this->currentGamePlayers = [];
                $this->streakBuffer = [];

                // the final scoreboard follows: the timeline's last minute
                $this->startSnapshot($ts);
            }
            return;
        }

        // status block team summary: "Team  CLA:  2 players,    6 frags,    2 flags"
        // (no flags part in non-flag modes)
        if (preg_match('~^Team\s+(CLA|RVSF):\s+(\d+) players,\s+(-?\d+) frags(?:,\s+(-?\d+) flags)?~', $rest, $m)) {
            // final block: add back who left during the intermission
            if ($this->finishedGame && !$this->currentGame) {
                foreach ($this->intermissionLeavers as $row) {
                    if ($row['team'] !== $m[1]) {
                        continue;
                    }
                    $m[2] = (string)((int)$m[2] + 1);
                    $m[3] = (string)((int)$m[3] + $row['fr']);
                    if (isset($m[4]) && $m[4] !== '') {
                        $m[4] = (string)((int)$m[4] + (int)$row['fl']);
                    }
                    if ($this->timeline && $this->timelineGame && !$this->skipCurrentGame) {
                        $this->timeline[array_key_last($this->timeline)]['p'][] = $row;
                    }
                }
            }
            $this->teamPlayers[$m[1]] = (int)$m[2];
            if ($this->timeline && $this->timelineGame && !$this->skipCurrentGame) {
                $this->timeline[array_key_last($this->timeline)]['t'][$m[1]] = [
                    (int)$m[2], (int)$m[3], isset($m[4]) && $m[4] !== '' ? (int)$m[4] : null,
                ];
            }
            // RVSF closes a status block of a running team game: were the teams even?
            if ($m[1] === 'RVSF' && $this->currentGame && isset($this->teamPlayers['CLA'])) {
                $this->statusBlocks++;
                if (self::unevenTeams($this->teamPlayers['CLA'], $this->teamPlayers['RVSF'])) {
                    $this->unevenBlocks++;
                }
            }
            if ($this->finishedGame && !$this->currentGame) {
                $this->finalTeamScores[$m[1]] = [
                    'players' => (int)$m[2],
                    'frags' => (int)$m[3],
                    'flags' => isset($m[4]) && $m[4] !== '' ? (int)$m[4] : null,
                ];
                if (count($this->finalTeamScores) === 2) {
                    $this->finishedGame->team_scores = json_encode($this->finalTeamScores);
                    $this->Games->save($this->finishedGame);
                    $this->finishedGame = null;
                    $this->saveTimeline();
                }
            }
            return;
        }

        // status block player row (once a minute):
        //   team modes " 0 ZZ|Nieukerk      RVSF    6   16     5  0   15 normal  1.2.3.4"
        //   other modes " 0 pacman            25    36   96 normal  1.2.3.4"
        // remembers the player's (last) team and counts a minute played -
        // spectators (SPEC / CSPC / RSPC) are listed but not playing
        // (its columns come from the header line before the rows, they differ per mode)
        if (str_starts_with($rest, 'cn name ')) {
            $this->statusCols = preg_split('~\s+~', $rest);
            return;
        }
        $this->cheatSignals->notePing($rest); // the ping that goes with a later warning
        if ($this->statusCols && ($row = $this->parseStatusRow($rest))) {
            $playerId = $this->getPlayerIdByNameCached($row['name']);
            $team = $row['team'];
            $playing = !in_array($team, ['SPEC', 'CSPC', 'RSPC'], true);
            if ($playerId && $this->currentGame && !$this->skipCurrentGame && $playing) {
                $this->ensurePlayerStatsBuffered($playerId);
                $key = $this->currentGame->id . ':' . $playerId;
                if ($team !== null) {
                    $this->statsBuffer[$key]['team'] = $team;
                }
                $this->statsBuffer[$key]['minutes_played'] = (int)($this->statsBuffer[$key]['minutes_played'] ?? 0) + 1;
            }
            $timelineRow = [
                'id' => $playerId, 'n' => $row['name'], 'team' => $team,
                'fl' => $row['flag'], 'fr' => $row['frag'], 'd' => $row['death'], 'tk' => $row['tk'], 'ping' => $row['ping'],
            ];
            if ($playing && $this->currentGame && !$this->skipCurrentGame) {
                $this->lastRows[$row['name']] = $timelineRow;
            }
            if ($playing && $this->timeline && $this->timelineGame && !$this->skipCurrentGame) {
                $this->timeline[array_key_last($this->timeline)]['p'][] = $timelineRow;
            }
            return;
        }

        // periodic status block: "Game status: capture the flag on ac_casa, 12 minutes remaining, open, 9 clients"
        if (strpos($rest, 'Game status:') === 0) {
            if ($this->currentGame && !$this->skipCurrentGame && str_contains($rest, ' remaining, ')) {
                $this->startSnapshot($ts);
            }
            if (preg_match('~(?:remaining|game finished), (\w+), \d+ clients~', $rest, $m)) {
                $this->resolveVote(fn($v) => $v['kind'] === 'mastermode' && $v['target'] === $m[1], 'passed');
            }
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
        // stored as wall time of the storage zone (LinkLiveStatsCommand::DEFAULT_TZ)
        if ($this->logTz) {
            $local = (new \DateTimeImmutable($fullDate->format('Y-m-d H:i:s'), $this->logTz))
                ->setTimezone(new \DateTimeZone(\App\Command\LinkLiveStatsCommand::DEFAULT_TZ));
            $fullDate = new Chronos($local->format('Y-m-d H:i:s'));
        }

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
                ->applyOptions(['includeInaccurate' => true])
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
                    'returned_the_flag' => 0, 'scored_with_the_flag' => 0, 'longest_streak' => 0,
                    'picked_up_the_flag' => 0, 'dropped_the_flag' => 0, 'failed_to_score' => 0,
                    'team' => null, 'minutes_played' => 0, 'avg_ping' => null,
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

    /**
     * One more kill without dying: bump the running streak and remember the
     * best one of the game in the stats row (longest_streak).
     */
    protected function extendStreak(string $playerId): void
    {
        if (!$this->currentGame) return;

        $streak = ($this->streakBuffer[$playerId] ?? 0) + 1;
        $this->streakBuffer[$playerId] = $streak;

        $key = $this->currentGame->id . ':' . $playerId;
        $this->ensurePlayerStatsBuffered($playerId);
        if ($streak > (int)($this->statsBuffer[$key]['longest_streak'] ?? 0)) {
            $this->statsBuffer[$key]['longest_streak'] = $streak;
        }
    }

    /**
     * Player died (killed, teamkilled or suicided): the streak is over.
     */
    protected function breakStreak(string $playerId): void
    {
        $this->streakBuffer[$playerId] = 0;
    }

    // -------------------------------
    // Minute scoreboards (game_timelines) and ping
    // -------------------------------

    /**
     * One player row of a status block, read by the columns of its header:
     *   "cn name team flag frag death tk ping role host" (columns vary per mode)
     * Fixed columns are taken from the end, so the name is whatever is left.
     * Null for anything that isn't such a row.
     */
    protected function parseStatusRow(string $rest): ?array
    {
        $cols = $this->statusCols;
        if (!preg_match('~^\d+\s+\S.*\s-?\d+\s+(?:normal|admin)\s+\S+$~', $rest)) {
            return null;
        }
        $tokens = preg_split('~\s+~', $rest);
        $tail = count($cols) - 2; // everything after cn and name
        if (count($tokens) < $tail + 2) {
            return null;
        }
        $values = array_combine(array_slice($cols, 2), array_slice($tokens, -$tail));
        $int = fn(string $c) => isset($values[$c]) ? (int)$values[$c] : null;

        return [
            'name' => implode(' ', array_slice($tokens, 1, count($tokens) - 1 - $tail)),
            'team' => $values['team'] ?? null,
            'flag' => $int('flag'),
            'frag' => (int)($values['frag'] ?? 0),
            'death' => (int)($values['death'] ?? 0),
            'tk' => $int('tk'),
            'ping' => (int)($values['ping'] ?? 0),
        ];
    }

    /**
     * A new minute of the timeline, 's' seconds into the game (the server's
     * "N minutes remaining" is rounded up, the log time is exact): rows and
     * team totals of the status block follow.
     */
    protected function startSnapshot(?Chronos $ts): void
    {
        if (!$this->timelineGame || !$ts || !$this->timelineGame->started_at) {
            return;
        }
        $seconds = max(0, $ts->getTimestamp() - $this->timelineGame->started_at->getTimestamp());
        // two blocks in the same second (game end): keep the later one
        if ($this->timeline && $this->timeline[array_key_last($this->timeline)]['s'] === $seconds) {
            array_pop($this->timeline);
        }
        $this->timeline[] = ['s' => $seconds, 't' => [], 'p' => []];
    }

    /**
     * The game's time is up: the server logged the intermission (when it
     * records demos), or the game's minutes have passed since its start.
     */
    protected function inIntermission(?Chronos $ts): bool
    {
        if ($this->intermission) {
            return true;
        }
        $game = $this->currentGame;
        if (!$ts || !$game || !$game->started_at || !$game->duration_minutes) {
            return false;
        }

        return $ts->getTimestamp() >= $game->started_at->getTimestamp() + 60 * (int)$game->duration_minutes;
    }

    /** Writes the timeline of the game it belongs to (again - idempotent) */
    protected function saveTimeline(): void
    {
        if (!$this->timelineGame || $this->timelineGame->id === null || !$this->timeline) {
            return;
        }
        try {
            ConnectionManager::get('default')->execute(
                'REPLACE INTO game_timelines (game_id, timeline) VALUES (?, ?)',
                [$this->timelineGame->id, json_encode($this->timeline, JSON_UNESCAPED_UNICODE)]);
        } catch (\Exception $e) {
            // never break log processing over the timeline
        }
    }

    /**
     * Each player's mean ping over the minutes of the running game (avg_ping).
     * Pings of 0 (not measured yet) and from MAX_PING on (lag) are left out.
     */
    protected function applyAvgPings(): void
    {
        if (!$this->currentGame || $this->timelineGame !== $this->currentGame) {
            return;
        }
        $pings = [];
        foreach ($this->timeline as $snapshot) {
            foreach ($snapshot['p'] as $p) {
                if ($p['id'] && $p['ping'] > 0 && $p['ping'] < self::MAX_PING) {
                    $pings[$p['id']][] = $p['ping'];
                }
            }
        }
        foreach ($pings as $playerId => $list) {
            $this->ensurePlayerStatsBuffered($playerId);
            $this->statsBuffer[$this->currentGame->id . ':' . $playerId]['avg_ping'] = (int)round(array_sum($list) / count($list));
        }
    }

    // -------------------------------
    // Votes (events type 'vote')
    // -------------------------------

    /**
     * What a vote was about, from its description:
     *   load map 'ac_casa' in mode 'capture the flag' / kick player X, reason: Y /
     *   ban player X, reason: Y / shuffle teams / change mastermode to 'match'
     */
    public static function describeVote(string $desc): array
    {
        $vote = ['kind' => 'other', 'desc' => $desc, 'map' => null, 'mode' => null, 'target' => null, 'reason' => null];
        if (preg_match("~^load map '(.+)' in mode '(.+)'$~", $desc, $m)) {
            $vote = ['kind' => 'map', 'map' => $m[1], 'mode' => $m[2]] + $vote;
        } elseif (preg_match('~^(kick|ban) player (\S+)(?:, reason: (.*))?$~', $desc, $m)) {
            $vote = ['kind' => $m[1], 'target' => $m[2], 'reason' => ($m[3] ?? '') !== '' ? $m[3] : null] + $vote;
        } elseif ($desc === 'shuffle teams') {
            $vote['kind'] = 'shuffle';
        } elseif (preg_match("~^change mastermode to '(\w+)'$~", $desc, $m)) {
            $vote = ['kind' => 'mastermode', 'target' => $m[1]] + $vote;
        }

        return $vote;
    }

    /**
     * A vote called (waits for its result) or refused by the server
     * ("failed to call a vote: ... (can't vote that often)" - saved right away).
     */
    protected function recordVote(string $name, bool $refused, string $desc, ?Chronos $ts): void
    {
        $error = null;
        if ($refused && preg_match('~^(.*) \(([^()]*)\)$~', $desc, $m)) {
            [$desc, $error] = [$m[1], $m[2]];
        }
        $vote = self::describeVote($desc) + [
            'name' => $name,
            'error' => $error,
            'result' => $refused ? 'refused' : null,
            'at' => $ts,
            'game_id' => $this->currentGame->id ?? $this->finishedGame->id ?? null,
        ];
        if ($refused) {
            $this->queueVote($vote);
        } else {
            $this->pendingVotes[] = $vote;
        }
    }

    /** Pending votes for which $match is true get $result and are saved */
    protected function resolveVote(callable $match, string $result): void
    {
        foreach ($this->pendingVotes as $i => $vote) {
            if ($match($vote)) {
                $vote['result'] = $result;
                $this->queueVote($vote);
                unset($this->pendingVotes[$i]);
            }
        }
    }

    /** A new game: the map vote for exactly it passed, other map votes failed */
    protected function resolveMapVotes(string $mode, string $map, ?Chronos $ts): void
    {
        $this->resolveVote(fn($v) => $v['kind'] === 'map' && $v['map'] === $map && strcasecmp($v['mode'], $mode) === 0, 'passed');
        $this->resolveVote(fn($v) => $v['kind'] === 'map', 'failed');
    }

    /** Votes without a result after VOTE_TIMEOUT: failed (shuffle shows none - unknown) */
    protected function expireVotes(Chronos $now): void
    {
        foreach ($this->pendingVotes as $i => $vote) {
            if ($vote['at'] && $now->getTimestamp() - $vote['at']->getTimestamp() > self::VOTE_TIMEOUT) {
                $vote['result'] = in_array($vote['kind'], ['map', 'kick', 'ban', 'mastermode'], true) ? 'failed' : null;
                $this->queueVote($vote);
                unset($this->pendingVotes[$i]);
            }
        }
    }

    /** A CheatSignals event for the events table (type 'anticheat') */
    protected function queueCheatSignal(array $signal, Chronos $ts): void
    {
        $this->chatBuffer[] = [
            'game_id' => $this->currentGame->id ?? null,
            'event_time' => $ts->format('Y-m-d H:i:s'),
            'event_hash' => CheatSignals::hash($this->getServerName(), $signal),
            'type' => 'anticheat',
            'actor_id' => in_array(strtolower($signal['name']), $this->ignoredNames, true) ? null : $this->getPlayerIdByNameCached($signal['name']),
            'details' => json_encode($signal['details'], JSON_UNESCAPED_UNICODE),
        ];
    }

    protected function queueVote(array $vote): void
    {
        $ts = $vote['at'];
        $actorId = strtolower($vote['name']) === 'unarmed' ? null : $this->getPlayerIdByNameCached($vote['name']);
        $targetId = in_array($vote['kind'], ['kick', 'ban'], true) && $vote['target'] ? $this->getPlayerIdByNameCached($vote['target']) : null;
        $details = array_intersect_key($vote, array_flip(['name', 'kind', 'desc', 'map', 'mode', 'target', 'reason', 'error', 'result']));
        $this->chatBuffer[] = [
            'game_id' => $vote['game_id'],
            'event_time' => $ts ? $ts->format('Y-m-d H:i:s') : null,
            'event_hash' => md5($this->getServerName() . '|vote|' . ($ts ? $ts->format('c') : '') . '|' . $vote['name'] . '|' . $vote['desc']),
            'type' => 'vote',
            'actor_id' => $actorId,
            'target_id' => $targetId,
            'details' => json_encode(array_filter($details, fn($v) => $v !== null), JSON_UNESCAPED_UNICODE),
        ];
    }

    protected function recalculateTotalScoreInMemory(string $key): void
    {
        $row = &$this->statsBuffer[$key];
        $total = 0;
        foreach ($this->pointsMap as $col => $pts) {
            $total += ($row[$col] ?? 0) * $pts;
        }
        // the placement bonus of a re-read game stays in (bin/cake PlacementBonus)
        $row['total_score'] = $total + (int)($row['bonus'] ?? 0);

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
            'picked_up_the_flag','dropped_the_flag','failed_to_score',
            'longest_streak','total_score','kd_ratio','team','minutes_played','avg_ping'
        ];
        $value = fn(array $row, string $col) => in_array($col, ['team', 'avg_ping'], true) ? ($row[$col] ?? null) : ($row[$col] ?? 0);

        $placeholders = [];
        $bindings = [];

        foreach ($this->statsBuffer as $key => $row) {
            $place = [];
            foreach ($columns as $col) {
                $place[] = '?';
                $bindings[] = $value($row, $col);
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
                    $conn->execute($singleSql, array_map(fn($c) => $value($row, $c), $columns));
                } catch (\Exception $ex) {
                    // ignore single failure
                }
            }
        }

        // clear buffer
        $this->statsBuffer = [];
    }

    /**
     * Write the current game's killer->victim pairs (kill_pairs). Same
     * last-write-wins upsert semantics as flushStats, so re-parsing a log
     * window (the offset rewind) does not double count. Only flushed at
     * game end / end of input - the buffer holds one game at most.
     */
    public function flushKills(): void
    {
        if (empty($this->killBuffer) || !$this->currentGame) {
            $this->killBuffer = [];
            return;
        }
        $gameId = $this->currentGame->id;
        $conn = ConnectionManager::get('default');

        $placeholders = [];
        $bindings = [];
        foreach ($this->killBuffer as $key => $row) {
            [$killerId, $victimId] = explode('|', $key);
            $placeholders[] = '(?,?,?,?,?)';
            array_push($bindings, $gameId, $killerId, $victimId, $row['kills'], $row['teamkills']);
        }

        $sql = 'INSERT INTO kill_pairs (game_id, killer_id, victim_id, kills, teamkills) VALUES '
            . implode(',', $placeholders)
            . ' ON DUPLICATE KEY UPDATE kills=VALUES(kills), teamkills=VALUES(teamkills)';
        try {
            $conn->execute($sql, $bindings);
        } catch (\Exception $e) {
            // never break log processing over nemesis stats
        }

        $this->killBuffer = [];
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
        // every earlier key too: a second PC, or a merged profile
        foreach (ConnectionManager::get('default')->execute('SELECT pubkey, player_id FROM player_pubkeys')->fetchAll('assoc') as $k) {
            $this->pubkeyCache[$k['pubkey']] ??= $k['player_id'];
        }
    }

    /**
     * Remembers that $playerId logged in with $pubkey (player_pubkeys), so
     * that key keeps leading to this profile after the next one is adopted.
     */
    protected function rememberPubkey(string $playerId, ?string $pubkey, Chronos $now): void
    {
        if (!$pubkey) {
            return;
        }
        ConnectionManager::get('default')->execute(
            'INSERT INTO player_pubkeys (pubkey, player_id, first_seen, last_seen) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE player_id = VALUES(player_id), last_seen = GREATEST(COALESCE(last_seen, VALUES(last_seen)), VALUES(last_seen))',
            [$pubkey, $playerId, $now->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
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
        $player = $pubkey && isset($this->pubkeyCache[$pubkey])
            ? $this->Players->find()->where(['id' => $this->pubkeyCache[$pubkey]])->first() : null;
        if ($player) {

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
            $this->rememberPubkey($player->id, $pubkey, $now);

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
                $this->rememberPubkey($existing->id, $pubkey, $now);

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
                $this->rememberPubkey($existing->id, $pubkey, $now);

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
            $this->rememberPubkey($player->id, $pubkey, $now);
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
        $this->disconnectRegex = '~^(?:\[[0-9a-f:\.]+\]\s+)?disconnected client ([^\s]+) cn \d+,\s*(\d+) seconds played, score saved~i';
        $this->chatRegex = '~^\[([0-9a-f:\.]+)\]\s+(.+?)\s+says:\s+\'(.+)\'$~i';

        $this->killRegex = '~^\[([0-9a-f:\.]+)\]\s+(.+?)\s+'
            . '(sprayed|headshot|punctured|busted|shredded|peppered|gibbed|splattered|slashed|picked off)'
            . '\s+(.+)$~i';

        // (team) keep the flag scores every 15 s of carrying: "X scored, carrying for 15 seconds, new score 1"
        $this->specialRegex = '~^\[([0-9a-f:\.]+)\]\s+(.+?)\s+(suicided|stole the flag|scored with the flag|hunted the flag|scored, carrying for \d+ seconds|lost the flag|returned the flag|picked up the flag|dropped the flag|failed to score)(?: for (\w+),.*|, new score \d+)?$~i';
        $this->voteRegex = '~^\[([0-9a-f:\.]+)\]\s+client (.+?) (called|failed to call) a vote: (.+)$~i';
        $this->gameFinishedRegex = '~Game status: .*game finished,.* (\d+) clients~i';
        //$this->changeNameRegex = '~^\[([0-9a-f:\.]+)\]\s+([^\s]+)\s+changed name\s+(.+)$/';
    }


    // -------------------------------
    // Utilities / normalization
    // -------------------------------
    /** A team with one player or none (1 vs 1, 1 vs 3, 0 vs 2 ...) - not a real team game */
    public static function unevenTeams(int $cla, int $rvsf): bool
    {
        return min($cla, $rvsf) <= 1;
    }

    /** Uneven for UNEVEN_SHARE of the minute status blocks (from UNEVEN_MIN_BLOCKS on) */
    public static function unevenGame(int $blocks, int $uneven): bool
    {
        return $blocks >= self::UNEVEN_MIN_BLOCKS && $uneven >= $blocks * self::UNEVEN_SHARE;
    }

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
    /**
     * Write buffered chat lines to events. INSERT IGNORE on the unique
     * event_hash makes re-parsing a log window (offset rewind) harmless.
     */
    public function flushChats(): void
    {
        if (empty($this->chatBuffer)) {
            return;
        }
        $conn = ConnectionManager::get('default');
        $placeholders = [];
        $bindings = [];
        foreach ($this->chatBuffer as $row) {
            $placeholders[] = '(?,?,?,?,?,?,?)';
            array_push($bindings, $row['game_id'], $row['event_time'], $row['event_hash'], $row['type'], $row['actor_id'], $row['target_id'] ?? null, $row['details']);
        }
        // a re-parsed running game gets a new id: its chat moves along to it (an
        // already imported game is skipped on a re-parse: no id, keep the one we have)
        // and a vote saved while still open gets its result
        $sql = 'INSERT INTO events (game_id, event_time, event_hash, type, actor_id, target_id, details) VALUES '
            . implode(',', $placeholders)
            . ' ON DUPLICATE KEY UPDATE game_id = COALESCE(VALUES(game_id), game_id), details = VALUES(details)';
        try {
            $conn->execute($sql, $bindings);
        } catch (\Exception $e) {
            // never break log processing over chat logging
        }
        $this->chatBuffer = [];
    }

    public function flush(): void
    {
        // a running game: its minutes so far (the next run writes them again)
        $this->applyAvgPings();
        $this->saveTimeline();
        // votes still open are saved without a result, the next run adds it
        foreach ($this->pendingVotes as $vote) {
            $this->queueVote($vote);
        }
        $this->pendingVotes = [];
        $this->flushStats();
        $this->flushKills();
        $this->flushChats();
    }
}
