<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use RuntimeException;

/**
 * Keeps ONE Discord message up to date with the live status of our game
 * servers. The first run posts the message through the webhook (with
 * ?wait=true so Discord returns the message id); every following run
 * edits that same message (PATCH .../messages/{id}) instead of posting a
 * new one. The message id is remembered in Ladder.discord.state; when the
 * message was deleted in Discord (404) a fresh one is posted.
 *
 * Discord cannot embed a web page, so the Live page is rebuilt as embeds:
 * one embed per server with map / mode / time left, a monospaced
 * scoreboard per team (CLA | RVSF), links to the ladder profiles of the
 * players we track and a link to the game on the ladder when the log has
 * already been processed. Servers being played on get their scoreboard as
 * a picture instead (LiveScoreboardImage, uploaded with the message and
 * only re-uploaded when it changed); link buttons below the message open
 * the Live pages.
 */
class DiscordLiveService
{
    use LocatorAwareTrait;

    private const COLOR_ONLINE = 0x57F287;   // discord green
    private const COLOR_EMPTY = 0x5865F2;    // blurple
    private const COLOR_OFFLINE = 0xED4245;  // red
    private const MAX_PLAYERS_PER_SERVER = 12;

    private array $cfg;
    private Client $http;

    /**
     * Scoreboard pictures of the payload being built: server key =>
     * ['filename', 'hash', 'jpeg' (new upload) | 'id' (kept attachment)]
     */
    private array $images = [];

    /** Last uploaded picture per server key (from the state file) */
    private array $prevImages = [];

    /** Seconds until the next run (looping command), for the countdown */
    private ?int $nextIn = null;

    public function __construct(?array $cfg = null, ?Client $http = null)
    {
        $this->cfg = $cfg ?? (Configure::read('Ladder.discord') ?: []);
        if (empty($this->cfg['webhook'])) {
            throw new RuntimeException('Ladder.discord.webhook is not configured (DISCORD_LIVE_WEBHOOK)');
        }
        $this->http = $http ?? new Client(['timeout' => 10]);
    }

    /**
     * Poll the servers and post / edit the Discord message.
     *
     * @return array{action:string, message_id:string, players:int, online:int}
     */
    public function update(bool $forceNew = false, ?int $nextIn = null): array
    {
        $this->nextIn = $nextIn !== null && $nextIn > 0 ? $nextIn : null;
        $state = $this->readState();
        $servers = $this->poll();
        $state['live'] = $this->trackFlags($servers, $state['live'] ?? []);
        $this->notifyJoins($servers, $state);

        $action = 'edited';
        $messageId = $forceNew ? null : ($state['message_id'] ?? null);
        // kept attachments only exist on the remembered message
        $this->prevImages = $messageId !== null ? ($state['images'] ?? []) : [];
        $payload = $this->buildPayload($servers);

        if ($messageId !== null) {
            $res = $this->send('PATCH', $this->messageUrl($messageId), $payload);
            if ($res->getStatusCode() === 404) {
                Log::info('discord_live: message ' . $messageId . ' is gone, posting a new one');
                $messageId = null;
                // nothing to keep on a new message: upload every picture
                $this->prevImages = [];
                $payload = $this->buildPayload($servers);
            } elseif (!$res->isOk()) {
                throw new RuntimeException('Discord edit failed: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());
            }
        }

        if ($messageId === null) {
            $res = $this->send('POST', $this->cfg['webhook'] . '?wait=true', $payload);
            if (!$res->isOk()) {
                throw new RuntimeException('Discord post failed: HTTP ' . $res->getStatusCode() . ' ' . $res->getStringBody());
            }
            $messageId = (string)($res->getJson()['id'] ?? '');
            if ($messageId === '') {
                throw new RuntimeException('Discord did not return a message id');
            }
            $state['message_id'] = $messageId;
            $state['posted_at'] = time();
            $action = 'posted';
        }
        $state['images'] = $this->rememberImages((array)$res->getJson());
        $state['webhook_id'] = $this->webhookId();
        $this->writeState($state);

        $online = count(array_filter($servers, fn($s) => $s['online']));
        $players = array_sum(array_map(fn($s) => $s['numplayers'], $servers));

        return compact('action', 'messageId', 'players', 'online') + ['message_id' => $messageId];
    }

    /**
     * Query the configured subset of Ladder.servers via extinfo.
     */
    public function poll(): array
    {
        $all = Configure::read('Ladder.servers') ?: [];
        $keys = $this->cfg['servers'] ?? array_keys($all);
        $targets = [];
        foreach ($keys as $key) {
            if (isset($all[$key])) {
                $targets[$key] = $all[$key];
            }
        }
        if (!$targets) {
            throw new RuntimeException('Ladder.discord.servers matches no entry of Ladder.servers');
        }

        $ext = new AcExtInfoService();
        $result = $ext->queryMany($targets);

        // UDP: player packets do get lost. Re-ask servers that returned fewer
        // players than they claim to have (a couple of times) and keep the best.
        for ($try = 0; $try < 2; $try++) {
            $retry = array_filter($targets, fn($t, $k) => $result[$k]['online']
                && count($result[$k]['players']) < $result[$k]['numplayers'], ARRAY_FILTER_USE_BOTH);
            if (!$retry) {
                break;
            }
            foreach ($ext->queryMany($retry) as $key => $info) {
                if ($info['online'] && count($info['players']) > count($result[$key]['players'])) {
                    $result[$key] = $info;
                }
            }
        }

        foreach ($result as $key => &$info) {
            $info['key'] = $key;
            $info['name'] = $targets[$key]['name'] ?? $key;
        }
        unset($info);

        return $result;
    }

    /**
     * A quiet server filling up is worth a ping: when a server goes from
     * below Ladder.discord.notify.threshold players to at/above it, post a
     * NEW message (edits never notify anyone) – optionally mentioning a
     * role – limited by a per-server cooldown. Previous player counts and
     * notification times live in the state file.
     */
    private function notifyJoins(array $servers, array &$state): void
    {
        $cfg = ($this->cfg['notify'] ?? []) + ['threshold' => 4, 'cooldown' => 1800, 'mention' => null, 'ttl' => 900];
        $threshold = (int)$cfg['threshold'];
        $now = time();

        // clean up old join notices so the channel stays tidy (the unread
        // marker they caused disappears with them for those who missed it)
        foreach ($state['join_msgs'] ?? [] as $i => $msg) {
            if ((int)$cfg['ttl'] > 0 && $now - $msg['at'] >= (int)$cfg['ttl']) {
                $res = $this->http->delete($this->messageUrl((string)$msg['id']));
                if ($res->isOk() || $res->getStatusCode() === 404) {
                    unset($state['join_msgs'][$i]);
                }
            }
        }
        $state['join_msgs'] = array_values($state['join_msgs'] ?? []);

        foreach ($servers as $key => $s) {
            $n = $s['online'] ? (int)$s['numplayers'] : 0;
            $prev = $state['counts'][$key] ?? 0;
            $state['counts'][$key] = $n;

            if ($threshold <= 0 || $n < $threshold || $prev >= $threshold) {
                continue; // disabled, still quiet, or was already busy
            }
            if ($now - ($state['notified'][$key] ?? 0) < (int)$cfg['cooldown']) {
                continue;
            }
            $state['notified'][$key] = $now;

            $text = sprintf(
                '🔔 **%d players** are now on **%s**%s · `/connect %s %d`',
                $n,
                $s['name'],
                $s['map'] ? sprintf(' — %s · %s', $s['map'], $s['mode_name'] ?? '?') : '',
                $s['host'],
                (int)$s['port']
            );
            if (!empty($cfg['mention'])) {
                $text = $cfg['mention'] . ' ' . $text;
            }
            $res = $this->http->post($this->cfg['webhook'] . '?wait=true', json_encode([
                'username' => ($this->cfg['brand'] ?? 'CubeLadder') . ' Live',
                'avatar_url' => $this->avatarUrl(),
                'content' => $text,
                'allowed_mentions' => ['parse' => empty($cfg['mention']) ? [] : ['roles', 'everyone', 'users']],
            ]), ['type' => 'json']);
            if (!$res->isOk()) {
                Log::warning('discord_live: join notification failed: HTTP ' . $res->getStatusCode());
            } elseif (($id = (string)($res->getJson()['id'] ?? '')) !== '') {
                $state['join_msgs'][] = ['id' => $id, 'at' => $now];
            }
        }
    }

    /**
     * Who scored last? extinfo only gives each player's current flag count,
     * so we diff against the previous poll (kept in the state file): a
     * player whose count went up scored. The result is attached to the
     * server as 'last_flag' => ['players' => [[name, team, n]], 'at' => ts]
     * and persists until the map / mode changes.
     *
     * @param array $servers  polled servers (by key) – modified in place
     * @param array $live     previous snapshots by server key
     * @return array          new snapshots to store
     */
    private function trackFlags(array &$servers, array $live): array
    {
        $now = time();
        $out = [];
        foreach ($servers as $key => &$s) {
            if (!$s['online'] || empty($s['map'])) {
                continue; // offline / idle: forget the snapshot
            }
            $prev = $live[$key] ?? null;
            $sameGame = $prev && $prev['map'] === $s['map'] && $prev['mode'] === (int)$s['mode'];

            $counts = [];
            foreach ($s['players'] as $p) {
                $counts[$p['name']] = ['flags' => (int)$p['flags'], 'team' => strtoupper(trim($p['team']))];
            }

            $last = $sameGame ? ($prev['last_flag'] ?? null) : null;
            if ($sameGame) {
                $scored = [];
                foreach ($counts as $name => $c) {
                    $before = $prev['players'][$name]['flags'] ?? null;
                    if ($before !== null && $c['flags'] > $before) {
                        $scored[] = ['name' => $name, 'team' => $c['team'], 'n' => $c['flags'] - $before];
                    }
                }
                if ($scored) {
                    $last = ['players' => $scored, 'at' => $now];
                }
            }

            if ($last) {
                $s['last_flag'] = $last;
            }
            $out[$key] = [
                'map' => $s['map'],
                'mode' => (int)$s['mode'],
                'players' => $counts,
                'last_flag' => $last,
            ];
        }
        unset($s);

        return $out;
    }

    /** Discord: max embeds per message */
    private const MAX_EMBEDS = 10;

    /** A nick shared by more profiles than this is not linked (default names) */
    private const MAX_SAME_NAME = 3;

    /**
     * Build the webhook payload: a summary embed followed by one embed per
     * server (team columns as inline fields, top player's avatar as thumbnail).
     */
    public function buildPayload(array $servers): array
    {
        $profiles = $this->profiles($servers);
        $this->images = [];
        foreach ($servers as $s) {
            $this->scoreboardImage($s);
        }

        $online = 0;
        $players = 0;
        foreach ($servers as $s) {
            if ($s['online']) {
                $online++;
                $players += $s['numplayers'];
            }
        }
        $color = $online === 0 ? self::COLOR_OFFLINE : ($players > 0 ? self::COLOR_ONLINE : self::COLOR_EMPTY);

        $summary = [
            'title' => $this->cfg['title'] ?? 'cubeLadder – live servers',
            // Discord counts a future <t:…:R> down by itself ("in 12 seconds")
            'description' => sprintf(
                '**%d** player%s on **%d/%d** server%s · %s <t:%d:R>',
                $players,
                $players === 1 ? '' : 's',
                $online,
                count($servers),
                count($servers) === 1 ? '' : 's',
                $this->nextIn !== null ? 'next update' : 'updated',
                time() + ($this->nextIn ?? 0)
            ),
            'color' => $color,
        ];
        if ($this->site()) {
            $summary['url'] = $this->site() . '/live';
        }

        $summary['footer'] = ['text' => ($this->cfg['brand'] ?? 'CubeLadder') . ' · refreshes automatically, this message is edited in place'];
        $summary['timestamp'] = gmdate('c');

        $embeds = [$summary];
        foreach ($servers as $s) {
            $group = $this->serverEmbeds($s, $profiles);
            if (count($embeds) + count($group) > self::MAX_EMBEDS) {
                $group = array_slice($group, 0, max(0, self::MAX_EMBEDS - count($embeds)));
            }
            foreach ($group as $e) {
                $embeds[] = $e;
            }
        }

        $payload = [
            'username' => ($this->cfg['brand'] ?? 'CubeLadder') . ' Live',
            'avatar_url' => $this->avatarUrl(),
            'embeds' => $embeds,
            'allowed_mentions' => ['parse' => []],
            // the message keeps exactly these: kept pictures by id, new ones
            // by their index in the upload (see send())
            'attachments' => $this->attachmentList(),
        ];
        if (($components = $this->buttons($servers)) !== []) {
            $payload['components'] = $components;
        }

        return $payload;
    }

    /**
     * Render the scoreboard picture of a server being played on (unless
     * Ladder.discord.images is false). An unchanged scoreboard keeps the
     * attachment that is already on the message instead of a new upload.
     */
    private function scoreboardImage(array $s): void
    {
        if (($this->cfg['images'] ?? true) === false || !$s['online'] || empty($s['map']) || (int)$s['numplayers'] === 0) {
            return;
        }
        $players = array_map(
            fn($p) => [$p['name'], $p['team'], $p['frags'], $p['flags'], $p['deaths'], !empty($p['is_spectator'])],
            $s['players']
        );
        $hash = md5(json_encode([$s['map'], $s['mode'], $s['minremain'], $s['numplayers'], $s['maxclients'] ?? 0, $players]));
        $filename = 'live-' . preg_replace('/[^a-z0-9-]/', '', strtolower($s['key'])) . '.jpg';

        $prev = $this->prevImages[$s['key']] ?? null;
        if ($prev && $prev['hash'] === $hash && !empty($prev['id'])) {
            $this->images[$s['key']] = ['filename' => $filename, 'hash' => $hash, 'id' => $prev['id']];

            return;
        }
        try {
            $this->images[$s['key']] = ['filename' => $filename, 'hash' => $hash, 'jpeg' => (new LiveScoreboardImage())->render($s)];
        } catch (\Throwable $e) {
            // no picture: the embed falls back to the text scoreboard
            Log::warning('discord_live: scoreboard image for ' . $s['key'] . ' failed: ' . $e->getMessage());
        }
    }

    /**
     * Webhook "attachments" list: kept ones by id, new uploads by index.
     */
    private function attachmentList(): array
    {
        $list = [];
        $upload = 0;
        foreach ($this->images as $img) {
            $list[] = isset($img['jpeg'])
                ? ['id' => $upload++, 'filename' => $img['filename']]
                : ['id' => $img['id'], 'filename' => $img['filename']];
        }

        return $list;
    }

    /**
     * Attachment ids of the pictures on the returned message, by file name,
     * for the state file: the next run keeps unchanged pictures. Pictures
     * used by an embed are not listed under "attachments" – their id is in
     * the embed's image url (.../attachments/{channel}/{id}/{filename}).
     */
    private function rememberImages(array $message): array
    {
        $ids = [];
        foreach ($message['attachments'] ?? [] as $a) {
            $ids[$a['filename'] ?? ''] = (string)($a['id'] ?? '');
        }
        foreach ($message['embeds'] ?? [] as $e) {
            if (preg_match('#/attachments/\d+/(\d+)/([^/?]+)#', (string)($e['image']['url'] ?? ''), $m)) {
                $ids[$m[2]] = $m[1];
            }
        }
        $out = [];
        foreach ($this->images as $key => $img) {
            if (!empty($ids[$img['filename']])) {
                $out[$key] = ['hash' => $img['hash'], 'id' => $ids[$img['filename']]];
            }
        }

        return $out;
    }

    /**
     * Link buttons below the message: the Live page and the live scoreboard
     * of up to four servers being played on (fullest first). Discord takes
     * them from non-bot webhooks with ?with_components=true (see send()).
     */
    private function buttons(array $servers): array
    {
        if (($this->cfg['buttons'] ?? true) === false || !$this->site()) {
            return [];
        }
        $busy = array_filter($servers, fn($s) => $s['online'] && (int)$s['numplayers'] > 0 && !empty($s['map']));
        uasort($busy, fn($a, $b) => (int)$b['numplayers'] <=> (int)$a['numplayers']);

        $row = [[
            'type' => 2, 'style' => 5,
            'label' => 'Live servers',
            'emoji' => ['name' => '📡'],
            'url' => $this->site() . '/live',
        ]];
        foreach (array_slice($busy, 0, 4) as $s) {
            $row[] = [
                'type' => 2, 'style' => 5,
                'label' => mb_substr(sprintf('%s · %d/%d', $s['name'], (int)$s['numplayers'], (int)($s['maxclients'] ?? 0)), 0, 80),
                'url' => $this->site() . '/live/game/' . rawurlencode($s['key']),
            ];
        }

        return [['type' => 1, 'components' => $row]];
    }

    /**
     * POST / PATCH a webhook message: JSON, or multipart (payload_json +
     * files[n]) when there are new scoreboard pictures to upload.
     */
    private function send(string $method, string $url, array $payload): \Cake\Http\Client\Response
    {
        if (!empty($payload['components'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'with_components=true';
        }
        $uploads = array_values(array_filter($this->images, fn($img) => isset($img['jpeg'])));
        if (!$uploads) {
            $body = json_encode($payload);
            $options = ['type' => 'json'];
        } else {
            $form = new \Cake\Http\Client\FormData();
            $json = $form->newPart('payload_json', (string)json_encode($payload));
            $json->type('application/json');
            $form->add($json);
            foreach ($uploads as $i => $img) {
                $part = $form->newPart('files[' . $i . ']', $img['jpeg']);
                $part->filename($img['filename']);
                $part->type('image/jpeg');
                $form->add($part);
            }
            $body = (string)$form;
            $options = ['headers' => ['Content-Type' => $form->contentType()]];
        }

        return $method === 'PATCH'
            ? $this->http->patch($url, $body, $options)
            : $this->http->post($url, $body, $options);
    }

    /**
     * Embeds for one server: the server itself and – when we track some of
     * the people playing – a small box with links to their ladder profiles.
     *
     * @param array<string, array{id:string, picture:?string}> $profiles by player name
     * @return array<int, array>
     */
    private function serverEmbeds(array $s, array $profiles): array
    {
        $embed = $this->serverEmbed($s);
        $links = $s['online'] ? $this->profileLinks($s['players'], $profiles) : '';
        if ($links === '') {
            return [$embed];
        }

        return [$embed, [
            'title' => 'Visit players on cubeladder.ovh',
            'description' => $links,
            'color' => $embed['color'],
        ]];
    }

    /**
     * One embed for one server.
     */
    private function serverEmbed(array $s): array
    {
        $connect = sprintf('`/connect %s %d`', $s['host'], $s['port']);

        if (!$s['online']) {
            $why = str_starts_with((string)$s['error'], 'outgoing UDP blocked') ? 'cannot be polled from here' : 'offline / no reply';
            return [
                'author' => $this->serverAuthor($s, $s['name'] . ' — offline'),
                'description' => $why . ' · ' . $connect,
                'color' => self::COLOR_OFFLINE,
            ];
        }

        $n = (int)$s['numplayers'];
        $max = (int)($s['maxclients'] ?? 0);
        $embed = [
            'author' => $this->serverAuthor($s, sprintf('%s — %d/%d', $s['name'], $n, $max)),
            'color' => $n > 0 ? self::COLOR_ONLINE : self::COLOR_EMPTY,
        ];

        if ($n === 0 && empty($s['map'])) {
            // idle server: AC reports no map until somebody joins
            $embed['description'] = 'empty · ' . $connect;
            return $embed;
        }

        $plist = array_values(array_filter($s['players'], fn($p) => empty($p['is_spectator'])));
        $specs = count($s['players']) - count($plist);

        $embed['description'] = sprintf(
            '**%s** · %s%s',
            $s['map'] ?: '—',
            $s['mode_name'] ?? '?',
            $s['minremain'] !== null ? sprintf(' · %d min left', $s['minremain']) : ''
        );
        if ($specs > 0) {
            if (!empty($this->cfg['show_spectators'])) {
                $names = array_map(
                    fn($p) => mb_substr($p['name'], 0, 16),
                    array_values(array_filter($s['players'], fn($p) => !empty($p['is_spectator'])))
                );
                $embed['description'] .= "\n👁 Spectating: **" . $this->mdEscape(implode(', ', $names)) . '**';
            } else {
                $embed['description'] .= sprintf(' · _%d spec%s_', $specs, $specs === 1 ? '' : 's');
            }
        }
        if (!empty($s['last_flag']) && in_array((int)$s['mode'], AcExtInfoService::FLAG_MODES, true)) {
            $who = [];
            foreach ($s['last_flag']['players'] as $p) {
                $who[] = $this->mdEscape($p['name']) . ' (' . $p['team'] . ')' . ($p['n'] > 1 ? " ×{$p['n']}" : '');
            }
            $embed['description'] .= sprintf("\n🚩 Last flag: **%s** · <t:%d:R>", implode(', ', $who), $s['last_flag']['at']);
        }
        if ($this->site() && ($gameId = $this->gameId($s)) !== null) {
            $embed['description'] .= sprintf("\n📊 [this game on CubeLadder](%s/games/view/%s)", $this->site(), $gameId);
        }

        // map picture as full-width image (same lookup as the Maps / Games
        // pages). Besides looking like the in-game scoreboard this forces
        // Discord to render the embed at its maximum width, which is what
        // gives the scoreboards room. Only for servers being played on.
        if (isset($this->images[$s['key']])) {
            // the scoreboard picture already shows the map and both teams
            $embed['image'] = ['url' => 'attachment://' . $this->images[$s['key']]['filename']];
            $embed['footer'] = ['text' => sprintf('/connect %s %d', $s['host'], $s['port'])];

            return $embed;
        }
        if ($this->site() && !empty($s['map']) && $n > 0) {
            // no screenshot: the bullet artwork (as on the website), 1280x720
            $path = is_file(WWW_ROOT . 'img/maps/' . $s['map'] . '.jpg')
                ? '/img/maps/' . rawurlencode($s['map'] . '.jpg')
                : '/img/bullet-wide.jpg';
            $embed['image'] = ['url' => $this->site() . $path];
        }

        // footer is the only embed part rendered below the image
        $embed['footer'] = ['text' => sprintf('/connect %s %d', $s['host'], $s['port'])];
        if (!$plist) {
            return $embed;
        }

        $byFlags = in_array((int)$s['mode'], AcExtInfoService::FLAG_MODES, true);
        $key = $byFlags ? 'flags' : 'frags';
        $sort = fn($a, $b) => [$b[$key], $b['frags'], $a['deaths']] <=> [$a[$key], $a['frags'], $b['deaths']];

        $teamMode = in_array((int)$s['mode'], AcExtInfoService::TEAM_MODES, true);
        if (!$teamMode) {
            usort($plist, $sort);
            $embed['fields'] = [[
                'name' => 'scoreboard',
                'value' => $this->clip($this->scoreboard($plist, $byFlags)),
                'inline' => false,
            ]];
            return $embed;
        }

        // team mode: CLA above RVSF, full width, team score bar on top
        $teams = ['CLA' => [], 'RVSF' => []];
        foreach ($plist as $p) {
            $t = strtoupper(trim($p['team']));
            $teams[$t][] = $p;
        }
        $score = [];
        foreach ($teams as $team => &$members) {
            usort($members, $sort);
            $score[$team] = array_sum(array_column($members, $key));
        }
        unset($members);
        $top = max($score);
        $tied = count(array_filter($score, fn($v) => $v === $top)) > 1;
        foreach ($teams as $team => $members) {
            $frags = array_sum(array_column($members, 'frags'));
            $flags = array_sum(array_column($members, 'flags'));
            $leads = !$tied && $score[$team] === $top;
            $embed['fields'][] = [
                'name' => sprintf(
                    '%s%s · %d %s%s',
                    $leads ? '🏆 ' : '',
                    $team,
                    $score[$team],
                    $key,
                    $byFlags ? sprintf(' · %d frags', $frags) : sprintf(' · %d flags', $flags)
                ),
                'value' => $this->clip($this->scoreboard($members, $byFlags, $team, $score[$team])),
                'inline' => false,
            ];
        }

        return $embed;
    }

    /**
     * Embed author line: small round icon + server name. The icon is
     * webroot/img/servers/<server key>.<png|jpg> when present (drop a logo
     * there to brand a server), the cube logo otherwise.
     */
    private function serverAuthor(array $s, string $label): array
    {
        $author = ['name' => $label];
        if ($this->site()) {
            // clicking the server name opens its live match page
            $author['url'] = $this->site() . '/live/game/' . rawurlencode($s['key']);
            $icon = '/img/cube.png';
            foreach (['png', 'jpg'] as $ext) {
                if (is_file(WWW_ROOT . 'img/servers/' . $s['key'] . '.' . $ext)) {
                    $icon = '/img/servers/' . rawurlencode($s['key']) . '.' . $ext;
                    break;
                }
            }
            $author['icon_url'] = $this->site() . $icon;
        }

        return $author;
    }

    /**
     * "[name](profile) · [name](profile)" for the players we track (empty
     * string when none / no site url).
     */
    private function profileLinks(array $players, array $profiles): string
    {
        if (!$this->site()) {
            return '';
        }
        $ranks = $this->rankMap();
        $items = [];
        foreach ($players as $p) {
            $prof = $profiles[$p['name']] ?? null;
            if ($prof && !isset($items[$p['name']])) {
                $rank = $ranks[$prof['id']] ?? null;
                $items[$p['name']] = [
                    'rank' => $rank,
                    'md' => sprintf(
                        '%s[%s](%s/players/view/%s)',
                        $rank !== null ? "**#{$rank}** " : '',
                        $this->linkText($p['name']),
                        $this->site(),
                        $prof['id']
                    ),
                ];
            }
        }
        // best ladder rank first, unranked players last
        uasort($items, fn($a, $b) => ($a['rank'] ?? PHP_INT_MAX) <=> ($b['rank'] ?? PHP_INT_MAX));

        return implode(' · ', array_column($items, 'md'));
    }

    /**
     * Ladder rank by player id – the same ranking as the players index
     * (this year's games, tracked players with >= 5000 points, by total
     * score). Cached in "rankings", which ProcessLogsCommand clears after
     * every import.
     *
     * @return array<string, int>
     */
    private function rankMap(): array
    {
        return Cache::remember('discord_live_rank_map', function () {
            $rows = $this->fetchTable('Players')->find()
                ->select(['Players.id', 'total_score' => 'SUM(PlayerStatsPerGame.total_score)'])
                ->innerJoinWith('PlayerStatsPerGame.Games', fn($q) => $q->where(['Games.started_at >' => date('Y')]))
                ->where(['Players.track' => 1])
                ->groupBy(['Players.id'])
                ->having(['SUM(PlayerStatsPerGame.total_score) >=' => 5000])
                ->orderBy(['total_score' => 'DESC'])
                ->enableHydration(false);
            $map = [];
            $rank = 1;
            foreach ($rows as $r) {
                $map[(string)$r['id']] = $rank++;
            }

            return $map;
        }, 'rankings');
    }

    /**
     * Id of the running game on the ladder, when the log parser has already
     * created it: the latest game of this server must be on the same map in
     * the same mode. Null when there is none (yet).
     */
    private function gameId(array $s): ?string
    {
        $mode = AcExtInfoService::MODE_CODES[(int)$s['mode']] ?? null;
        if ($mode === null || empty($s['map'])) {
            return null;
        }
        $game = $this->fetchTable('Games')->find()
            ->select(['Games.id', 'Games.mode', 'Maps.name'])
            ->contain(['Maps'])
            ->where(['Games.server_name' => $s['key']])
            ->orderBy(['Games.started_at' => 'DESC'])
            ->enableHydration(false)
            ->first();
        if (!$game || $game['mode'] !== $mode || ($game['map']['name'] ?? null) !== $s['map']) {
            return null;
        }

        return (string)$game['id'];
    }

    /**
     * Ladder profiles (id, picture) of every live player we track, by name.
     */
    private function profiles(array $servers): array
    {
        $names = [];
        foreach ($servers as $s) {
            foreach ($s['players'] ?? [] as $p) {
                $names[$p['name']] = true;
            }
        }
        if (!$names) {
            return [];
        }
        $out = [];
        $count = [];
        $rows = $this->fetchTable('Players')->find()
            ->select(['id', 'name', 'picture'])
            ->where(['name IN' => array_keys($names), 'track' => 1])
            ->enableHydration(false);
        foreach ($rows as $r) {
            $count[$r['name']] = ($count[$r['name']] ?? 0) + 1;
            // several profiles with the same name: prefer the one with a picture
            if (!isset($out[$r['name']]) || (empty($out[$r['name']]['picture']) && !empty($r['picture']))) {
                $out[$r['name']] = ['id' => (string)$r['id'], 'picture' => $r['picture'] ?: null];
            }
        }
        // default / generic nicks ("unarmed") match dozens of profiles – no link
        foreach ($count as $name => $n) {
            if ($n > self::MAX_SAME_NAME) {
                unset($out[$name]);
            }
        }

        return $out;
    }

    private function site(): ?string
    {
        return !empty($this->cfg['site']) ? rtrim($this->cfg['site'], '/') : null;
    }

    private function mdEscape(string $s): string
    {
        return preg_replace('/([\\*_`~|\[\]])/', '\\\\$1', $s);
    }

    /**
     * Player name for use as masked-link text "[name](url)". Discord does not
     * honour backslash escapes inside link text of embeds (it shows the
     * backslash literally), so instead: brackets would end the link and are
     * replaced by parentheses, "*" and "`" (which pair up anywhere) by their
     * look-alikes U+2217 / U+02CB, and the two-character delimiters "**",
     * "__", "~~", "||" are split with a zero-width space. Underscores stay
     * unless the name could italicise as "_word_", then they become U+02CD.
     */
    private function linkText(string $s): string
    {
        $s = strtr($s, ['[' => '(', ']' => ')', '*' => "\u{2217}", '`' => "\u{2CB}"]);
        if (preg_match('/\b_(?:__|[^_])+?_\b/', $s)) {
            $s = str_replace('_', "\u{2CD}", $s);
        }

        return preg_replace('/([_~|])(?=\1)/', "$1\u{200B}", $s);
    }

    /** ANSI (discord ```ansi blocks): [foreground, background] – CLA red, RVSF dark blue */
    private const TEAM_ANSI = ['CLA' => [31, 41], 'RVSF' => [34, 40]];

    /**
     * Monospaced ranked scoreboard, full embed width, in the style of the
     * in-game one:
     *
     *   ▌CLA                  16▐  coloured bar with the team score
     *    # PLAYER     FRAGS FLAGS   header (white, deciding column underlined)
     *    1 mop           52     5   rows in the team colour
     *
     * $byFlags says which column decides the game (flags or frags) – that
     * one is printed bold.
     */
    private function scoreboard(array $players, bool $byFlags, ?string $team = null, ?int $teamScore = null): string
    {
        $ansi = $team !== null ? (self::TEAM_ANSI[$team] ?? null) : null;
        $fg = $ansi ? "\e[{$ansi[0]}m" : '';
        $bold = "\e[1m";
        $reset = "\e[0m";
        $white = "\e[37m";

        // keep the whole row within 24 chars: the Discord mobile app wraps
        // code blocks in embeds beyond that
        $fmt = '%2s %-10s%5s %5s';
        $width = strlen(sprintf($fmt, '', '', '', ''));
        $rows = ['```ansi'];

        if ($team !== null && $teamScore !== null) {
            // coloured bar: team name left, score right
            $num = (string)$teamScore;
            $bar = ' ' . str_pad($team, $width - strlen($num) - 2) . $num . ' ';
            $rows[] = ($ansi ? "\e[1;37;{$ansi[1]}m" : $bold) . $bar . $reset;
        }

        // header: deciding column bold + underlined
        $ul = "\e[1;4;37m";
        $rows[] = $white . sprintf('%2s %-10s', '#', 'PLAYER')
            . ($byFlags ? 'FRAGS' : $ul . 'FRAGS' . $reset . $white) . ' '
            . ($byFlags ? $ul . 'FLAGS' . $reset : 'FLAGS' . $reset);

        foreach (array_slice($players, 0, self::MAX_PLAYERS_PER_SERVER) as $i => $p) {
            $frags = sprintf('%5d', $p['frags']);
            $flags = sprintf('%5d', $p['flags']);
            if ($byFlags) {
                $flags = $bold . $flags . $reset . $fg;
            } else {
                $frags = $bold . $frags . $reset . $fg;
            }
            $rows[] = $fg . sprintf('%2s %-10s', $i + 1, mb_substr($p['name'], 0, 10)) . $frags . ' ' . $flags . $reset;
        }
        if (count($players) > self::MAX_PLAYERS_PER_SERVER) {
            $rows[] = $fg . sprintf('   … +%d more', count($players) - self::MAX_PLAYERS_PER_SERVER) . $reset;
        }
        $rows[] = '```';

        return implode("\n", $rows);
    }

    private function clip(string $value): string
    {
        if (mb_strlen($value) > 1024) {
            $value = mb_substr($value, 0, 1010) . "\n```";
        }

        return $value;
    }

    // -------------------------------------------------------------

    private function messageUrl(string $id): string
    {
        return $this->cfg['webhook'] . '/messages/' . $id;
    }

    private function webhookId(): string
    {
        // https://discord.com/api/webhooks/{id}/{token}
        $parts = explode('/', rtrim($this->cfg['webhook'], '/'));
        return $parts[count($parts) - 2] ?? '';
    }

    private function statePath(): string
    {
        return $this->cfg['state'] ?? TMP . 'discord_live.json';
    }

    /**
     * Stored message id – ignored when it belongs to another webhook.
     */
    private function readState(): array
    {
        $path = $this->statePath();
        if (!is_file($path)) {
            return [];
        }
        $state = json_decode((string)file_get_contents($path), true) ?: [];
        if (($state['webhook_id'] ?? null) !== $this->webhookId()) {
            return [];
        }

        return $state;
    }

    private function writeState(array $state): void
    {
        file_put_contents($this->statePath(), json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Webhook avatar: Ladder.discord.avatar, else the cubeLadder icon (for
     * feeds without an own brand). Discord applies it to new posts only,
     * an edited message keeps the avatar it was posted with. null = the
     * webhook's own avatar.
     */
    private function avatarUrl(): ?string
    {
        if (array_key_exists('avatar', $this->cfg)) {
            return $this->cfg['avatar'] ?: null;
        }
        if (!empty($this->cfg['brand']) || !$this->site()) {
            return null;
        }

        return $this->site() . '/img/brand/cubeladder-discord-icon-512.png';
    }
}
