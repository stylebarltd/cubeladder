<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Psr\Http\Message\ServerRequestInterface;
use App\Service\GeoIpService;



class WebTrackingService
{
    protected $WebVisits;
    protected $Players;
    protected GeoIpService $geoIp;

    public function __construct()
    {
        $this->WebVisits = TableRegistry::getTableLocator()->get('WebVisits');
        $this->Players = TableRegistry::getTableLocator()->get('Players');
        $this->geoIp = new GeoIpService();
    }

    public function track(ServerRequestInterface $request): void
    {
        $server = $request->getServerParams();

        // ✅ Proper IP detection (handle proxies safely)
        $ip = null;

        if (!empty($server['HTTP_X_FORWARDED_FOR'])) {
            // XFF may contain multiple IPs: client, proxy1, proxy2
            $ips = explode(',', $server['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else {
            $ip = $server['REMOTE_ADDR'] ?? null;
        }

        if (!$ip) {
            return;
        }

        // ✅ Prevent flood
        $recent = $this->WebVisits->find()
            ->where([
                'ip_address' => $ip,
                'created >=' => new \DateTimeImmutable('-10 seconds')
            ])
            ->count();

        if ($recent > 10) {
            return;
        }

        $ua = $request->getHeaderLine('User-Agent');
        $path = $request->getUri()->getPath();

        if (!$this->shouldTrack($path, $ua)) {
            return;
        }

        $isBot = $this->isBot($ua);
        $playerId = $this->matchPlayerByIp($ip);

        // ✅ Block certain players
        $blockedPlayerIds = [
            'ed947213-05f5-4030-a7bc-f1f67e5c5de8'
        ];

        if ($playerId && in_array($playerId, $blockedPlayerIds)) {
            return;
        }

        // ✅ GeoIP lookup (skip private/reserved IPs)
        $countryIso = $this->geoIp->ipToCountryCached($ip);
        //Log::debug('Country: ' . $countryIso);
//debug($ip);
        $visit = $this->WebVisits->newEntity([
            'ip_address' => $ip,
            'user_agent' => $ua,
            'is_bot' => $isBot,
            'path' => $path,
            'method' => $request->getMethod(),
            'referer' => $request->getHeaderLine('Referer'),
            'player_id' => $playerId,
            'country_iso' => $countryIso,
        ]);
       // debug($visit);

        $this->WebVisits->save($visit);
    }


//    public function track(ServerRequestInterface $request): void
//    {
//        $server = $request->getServerParams();
//        $ip = $server['HTTP_X_FORWARDED_FOR'] ?? $server['REMOTE_ADDR'] ?? null;
//        $recent = $this->WebVisits->find()
//            ->where([
//                'ip_address' => $ip,
//                'created >=' => new \DateTimeImmutable('-10 seconds')
//            ])
//            ->count();
//
//        if ($recent > 10) {
//            return;
//        }
//
//
//        $ua = $request->getHeaderLine('User-Agent');
//        $path = $request->getUri()->getPath();
//
//        if (!$this->shouldTrack($path, $ua)) {
//            return;
//        }
//
//        $isBot = $this->isBot($ua);
//        $playerId = $this->matchPlayerByIp($ip);
//
//        $blockedPlayerIds = [
//            'ed947213-05f5-4030-a7bc-f1f67e5c5de8'
//        ];
//
//        if ($playerId && in_array($playerId, $blockedPlayerIds)) {
//            return;
//        }
//
//        $visit = $this->WebVisits->newEntity([
//            'ip_address' => $ip,
//            'user_agent' => $ua,
//            'is_bot' => $isBot,
//            'path' => $path,
//            'method' => $request->getMethod(),
//            'referer' => $request->getHeaderLine('Referer'),
//            'player_id' => $playerId,
//        ]);
//
//        $this->WebVisits->save($visit);
//    }

    private function shouldTrack(string $path, ?string $ua): bool
    {
        if (empty($ua)) {
            return false;
        }

        if (str_starts_with($path, '/web-visits')) {
            return false;
        }

        // Ignore assets & API
        $excludedPrefixes = [
            '/css',
            '/js',
            '/img',
            '/favicon.ico',
            '/api'
        ];

        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function isBot(?string $ua): bool
    {
        if (empty($ua)) {
            return true;
        }

        $patterns = [
            'bot', 'crawl', 'spider',
            'slurp', 'bingpreview',
            'facebookexternalhit'
        ];

        $ua = strtolower($ua);

        foreach ($patterns as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchPlayerByIp(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        $player = $this->Players->find()
            ->where(['ip' => $ip])
            ->orderByDesc('modified')
            ->select(['id'])
            ->first();

        return $player?->id;
    }
}
