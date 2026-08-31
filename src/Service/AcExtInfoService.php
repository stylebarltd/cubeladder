<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Client for the AssaultCube server "extinfo" UDP protocol.
 *
 * Every AC server answers ping / extended-info requests on <game port> + 1.
 * The server echoes the request bytes and appends its reply, ints use the
 * AC varint encoding (putint/getint) and strings are int-encoded chars
 * terminated by 0 (sendstring/getstring).
 *
 * Requests used here:
 *   [millis]                 standard ping → protocol, mode, players, minremain, map, desc, maxclients, flags
 *   [0][EXT_PLAYERSTATS][-1] one packet with the client ids (EXT_PLAYERSTATS_RESP_IDS) followed by
 *                            one packet per player (EXT_PLAYERSTATS_RESP_STATS), see extinfo_statsbuf()
 *   [0][EXT_UPTIME]          server uptime in seconds
 */
class AcExtInfoService
{
    public const EXT_ACK = -1;
    public const EXT_VERSION_MIN = 103;
    public const EXT_UPTIME = 0;
    public const EXT_PLAYERSTATS = 1;
    public const EXT_TEAMSCORE = 2;
    public const EXT_PLAYERSTATS_RESP_IDS = -10;
    public const EXT_PLAYERSTATS_RESP_STATS = -11;

    public const MODES = [
        0 => 'team deathmatch', 1 => 'coopedit', 2 => 'deathmatch', 3 => 'survivor',
        4 => 'team survivor', 5 => 'capture the flag', 6 => 'pistol frenzy', 7 => 'bot team deathmatch',
        8 => 'bot deathmatch', 9 => 'last swiss standing', 10 => 'one shot, one kill',
        11 => 'team one shot one kill', 12 => 'bot one shot one kill', 13 => 'hunt the flag',
        14 => 'team keep the flag', 15 => 'keep the flag', 16 => 'team pistol frenzy',
        17 => 'team last swiss standing', 18 => 'bot pistol frenzy', 19 => 'bot last swiss standing',
        20 => 'bot team survivor', 21 => 'bot team one shot one kill',
    ];

    public const GUNS = [
        0 => 'knife', 1 => 'pistol', 2 => 'carbine', 3 => 'shotgun', 4 => 'submachine gun',
        5 => 'sniper rifle', 6 => 'assault rifle', 7 => 'combat pistol', 8 => 'grenade', 9 => 'akimbo',
    ];

    public const STATES = [
        0 => 'alive', 1 => 'dead', 2 => 'spawning', 3 => 'lagged', 4 => 'editing', 5 => 'spectator',
    ];

    /** How long to wait for a server's answer to one request (seconds) */
    private float $firstReplyTimeout;

    public function __construct(float $firstReplyTimeout = 1.0)
    {
        $this->firstReplyTimeout = $firstReplyTimeout;
    }

    /**
     * Query one server: ping + player stats + uptime.
     *
     * @param string $host  IP / hostname
     * @param int $port     GAME port (the info port is port + 1)
     */
    public function query(string $host, int $port): array
    {
        return $this->queryMany(['s' => ['host' => $host, 'port' => $port]])['s'];
    }

    /**
     * Query several servers at once. Every request costs a full round trip
     * (~200ms, the server answers on its main-loop tick) so all servers are
     * driven in parallel through one select loop.
     *
     * @param array $targets  key => ['host' => .., 'port' => <game port>]
     * @return array          key => result (same shape as query())
     */
    public function queryMany(array $targets): array
    {
        $states = [];
        foreach ($targets as $key => $t) {
            $host = (string)$t['host'];
            $port = (int)$t['port'];
            $st = [
                'result' => $this->emptyResult($host, $port),
                'sock' => null,
                'stage' => null,
                'req' => '',
                'packets' => [],
                'expected' => 1,
                'deadline' => 0.0,
            ];
            $sock = @stream_socket_client("udp://{$host}:" . ($port + 1), $errno, $errstr, $this->firstReplyTimeout);
            if (!$sock) {
                $st['result']['error'] = $errstr ?: 'cannot open socket';
            } else {
                stream_set_blocking($sock, false);
                $st['sock'] = $sock;
                $this->send($st, 'ping');
            }
            $states[$key] = $st;
        }

        while (true) {
            $pending = array_filter($states, fn($st) => $st['sock'] !== null);
            if (!$pending) {
                break;
            }

            $now = microtime(true);
            $wait = max(0.0, min(array_map(fn($st) => $st['deadline'], $pending)) - $now);
            $r = array_map(fn($st) => $st['sock'], $pending);
            $w = $e = null;
            $n = @stream_select($r, $w, $e, (int)floor($wait), (int)(($wait - floor($wait)) * 1e6));

            if ($n) {
                foreach ($r as $sock) {
                    foreach ($states as $key => &$st) {
                        if ($st['sock'] !== $sock) continue;
                        $data = fread($sock, 8192);
                        if ($data === false || $data === '') {
                            // ICMP port unreachable etc. → treat as no reply
                            $this->advance($st);
                            break;
                        }
                        $st['packets'][] = $data;
                        if ($st['stage'] === 'stats' && count($st['packets']) === 1) {
                            $st['expected'] = 1 + $this->countIds($data, strlen($st['req']));
                            $st['deadline'] = microtime(true) + $this->firstReplyTimeout;
                        }
                        if (count($st['packets']) >= $st['expected']) {
                            $this->advance($st);
                        }
                        break;
                    }
                    unset($st);
                }
            }

            $now = microtime(true);
            foreach ($states as &$st) {
                if ($st['sock'] !== null && $now >= $st['deadline']) {
                    $this->advance($st); // timeout: go on with what we have
                }
            }
            unset($st);
        }

        return array_map(fn($st) => $st['result'], $states);
    }

    private function emptyResult(string $host, int $port): array
    {
        return [
            'host' => $host,
            'port' => $port,
            'online' => false,
            'error' => null,
            'protocol' => null,
            'mode' => null,
            'mode_name' => null,
            'numplayers' => 0,
            'minremain' => null,
            'map' => null,
            'description' => null,
            'maxclients' => null,
            'uptime' => null,
            'players' => [],
        ];
    }

    private function send(array &$st, string $stage): void
    {
        $st['req'] = match ($stage) {
            'ping'   => self::putint(1),
            'stats'  => self::putint(0) . self::putint(self::EXT_PLAYERSTATS) . self::putint(-1),
            'uptime' => self::putint(0) . self::putint(self::EXT_UPTIME),
        };
        $st['stage'] = $stage;
        $st['packets'] = [];
        $st['expected'] = 1;
        $st['deadline'] = microtime(true) + $this->firstReplyTimeout;
        // EPERM here means an outgoing firewall drops UDP to that port
        // (seen on shared hosting) – report it instead of raising a notice
        if (@fwrite($st['sock'], $st['req']) === false) {
            $st['result']['error'] = 'outgoing UDP blocked (' . (error_get_last()['message'] ?? 'send failed') . ')';
            $this->finish($st);
        }
    }

    /**
     * Current stage finished (all packets in, or timed out): consume the
     * packets and move to the next request, or close the socket when done.
     */
    private function advance(array &$st): void
    {
        $res = &$st['result'];
        $packets = $st['packets'];
        $reqLen = strlen($st['req']);

        switch ($st['stage']) {
            case 'ping':
                if (empty($packets)) {
                    $res['error'] = 'no reply';
                    $this->finish($st);
                    return;
                }
                $this->parsePing($packets[0], $reqLen, $res);
                $res['online'] = true;
                $this->send($st, 'stats');
                return;

            case 'stats':
                if (!empty($packets)) {
                    $res['players'] = $this->parsePlayerStats($packets, $reqLen);
                }
                $this->send($st, 'uptime');
                return;

            case 'uptime':
                if (!empty($packets)) {
                    $pos = $reqLen;
                    $ack = self::getint($packets[0], $pos);
                    self::getint($packets[0], $pos); // version
                    if ($ack === self::EXT_ACK) {
                        $res['uptime'] = self::getint($packets[0], $pos);
                    }
                }
                $this->finish($st);
                return;
        }
    }

    private function finish(array &$st): void
    {
        if ($st['sock']) {
            fclose($st['sock']);
        }
        $st['sock'] = null;
        $st['stage'] = null;
    }

    /**
     * Number of client ids in an EXT_PLAYERSTATS_RESP_IDS packet (0 on error).
     */
    private function countIds(string $p, int $pos): int
    {
        $ack = self::getint($p, $pos);
        $version = self::getint($p, $pos);
        $err = self::getint($p, $pos);
        $kind = self::getint($p, $pos);
        if ($ack !== self::EXT_ACK || $version < self::EXT_VERSION_MIN || $err !== 0
            || $kind !== self::EXT_PLAYERSTATS_RESP_IDS) {
            return 0;
        }
        $n = 0;
        while ($pos < strlen($p)) {
            self::getint($p, $pos);
            $n++;
        }
        return $n;
    }

    private function parsePing(string $p, int $pos, array &$out): void
    {
        $out['protocol']   = self::getint($p, $pos);
        $out['mode']       = self::getint($p, $pos);
        $out['mode_name']  = self::MODES[$out['mode']] ?? ('mode ' . $out['mode']);
        $out['numplayers'] = self::getint($p, $pos);
        $out['minremain']  = self::getint($p, $pos);
        $out['map']        = self::getstring($p, $pos);
        $out['description'] = self::stripColors(self::getstring($p, $pos));
        $out['maxclients'] = self::getint($p, $pos);
        $out['flags']      = self::getint($p, $pos);
    }

    /**
     * Decode the EXT_PLAYERSTATS packets (ids packet + one packet per player).
     */
    private function parsePlayerStats(array $packets, int $reqLen): array
    {
        $players = [];

        foreach ($packets as $p) {
            $pos = $reqLen;
            $ack = self::getint($p, $pos);
            $version = self::getint($p, $pos);
            if ($ack !== self::EXT_ACK || $version < self::EXT_VERSION_MIN) {
                continue;
            }
            $err = self::getint($p, $pos);
            if ($err !== 0) {
                continue;
            }
            $kind = self::getint($p, $pos);
            if ($kind !== self::EXT_PLAYERSTATS_RESP_STATS) {
                continue; // the ids packet – not needed, every player comes in its own packet
            }

            $pl = [
                'cn'        => self::getint($p, $pos),
                'ping'      => self::getint($p, $pos),
                'name'      => self::stripColors(self::getstring($p, $pos)),
                'team'      => self::getstring($p, $pos),
                'frags'     => self::getint($p, $pos),
                'flags'     => self::getint($p, $pos),
                'deaths'    => self::getint($p, $pos),
                'teamkills' => self::getint($p, $pos),
                'accuracy'  => self::getint($p, $pos),
                'health'    => self::getint($p, $pos),
                'armour'    => self::getint($p, $pos),
                'gun'       => self::getint($p, $pos),
                'role'      => self::getint($p, $pos),
                'state'     => self::getint($p, $pos),
            ];
            $pos += 3; // first 3 bytes of the ip (privacy protected) – not used
            $pl['damage'] = $pos < strlen($p) ? self::getint($p, $pos) : null;
            $pl['shotdamage'] = $pos < strlen($p) ? self::getint($p, $pos) : null;

            $pl['gun_name'] = self::GUNS[$pl['gun']] ?? null;
            $pl['state_name'] = self::STATES[$pl['state']] ?? null;
            $pl['is_admin'] = $pl['role'] === 1;
            $pl['is_spectator'] = $pl['state'] === 5 || str_contains(strtoupper($pl['team']), 'SPECT');

            $players[] = $pl;
        }

        usort($players, fn($a, $b) => [$b['frags'], $b['flags']] <=> [$a['frags'], $a['flags']]);

        return $players;
    }

    // -------------------------------------------------------------
    // AssaultCube wire encoding
    // -------------------------------------------------------------

    public static function putint(int $n): string
    {
        if ($n < 128 && $n > -127) {
            return chr($n & 0xff);
        }
        if ($n < 0x8000 && $n >= -0x8000) {
            return "\x80" . pack('v', $n & 0xffff);
        }
        return "\x81" . pack('V', $n & 0xffffffff);
    }

    public static function getint(string $p, int &$pos): int
    {
        if ($pos >= strlen($p)) {
            return 0;
        }
        $c = ord($p[$pos++]);
        if ($c > 127) $c -= 256; // signed char

        if ($c === -128) {
            $v = unpack('v', substr($p, $pos, 2))[1] ?? 0;
            $pos += 2;
            return $v >= 0x8000 ? $v - 0x10000 : $v;
        }
        if ($c === -127) {
            $v = unpack('V', substr($p, $pos, 4))[1] ?? 0;
            $pos += 4;
            return $v >= 0x80000000 ? $v - 0x100000000 : $v;
        }
        return $c;
    }

    public static function getstring(string $p, int &$pos): string
    {
        $s = '';
        while ($pos < strlen($p)) {
            $c = self::getint($p, $pos);
            if ($c === 0) break;
            $s .= chr($c & 0xff);
        }
        // AC strings are single-byte; make them valid UTF-8 for JSON/HTML
        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    /** Remove AC colour codes ("\f" + one char) */
    public static function stripColors(string $s): string
    {
        return preg_replace('/\f./', '', $s) ?? $s;
    }
}
