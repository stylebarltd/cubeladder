<?php
namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\ORM\TableRegistry;
use GeoIp2\Database\Reader;
use Exception;

class GeoPlayersCommand extends Command
{
    protected string $geoDbPath = CONFIG . 'GeoLite2-City.mmdb';

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $playersTable = TableRegistry::getTableLocator()->get('Players');
        $reader = new Reader($this->geoDbPath);

        $query = $playersTable->find()
            ->where([
                'OR' => [
                    'latitude IS' => null,
                    'longitude IS' => null
                ]
            ]);

        $count = $query->count();
        $io->out("Players to geo-locate: {$count}");

        foreach ($query as $player) {
            try {
                $lat = null;
                $lon = null;
                $accuracy = null;

                // 1️⃣ Try IP first
                if (!empty($player->ip)) {
                    $record = $reader->city($player->ip);
                    $lat = $record->location->latitude;
                    $lon = $record->location->longitude;
                    $accuracy = $record->location->accuracyRadius;
                }

                // 2️⃣ Fallback: country centroid
                if (($lat === null || $lon === null) && !empty($player->country)) {
                    $record = $reader->country($player->country);

                    // MaxMind country has no lat/lon → use rough centroids
                    [$lat, $lon] = $this->countryCentroid($player->country);
                    $accuracy = 1000; // very rough
                }

                if ($lat === null || $lon === null) {
                    continue;
                }

                // 🔐 privacy random offset
//                $lat += rand(-5, 5) / 100;
//                $lon += rand(-5, 5) / 100;
                $radius = 100; // km

                $earthRadius = 6371; // km

                $randDist = mt_rand() / mt_getrandmax();
                $randDist = $radius * sqrt($randDist);

                $randAngle = mt_rand() / mt_getrandmax() * 2 * pi();

                $latOffset = ($randDist / $earthRadius) * (180 / pi());
                $lonOffset = ($randDist / $earthRadius) * (180 / pi()) / cos(deg2rad($lat));

                $lat += $latOffset * cos($randAngle);
                $lon += $lonOffset * sin($randAngle);

                $player->latitude = $lat;
                $player->longitude = $lon;
                $player->geo_accuracy = $accuracy;

                $playersTable->save($player);

            } catch (Exception $e) {
                // ignore invalid IPs / private ranges
                continue;
            }
        }

        $io->success('# Geo-location finished.');
        return true;
    }

    /**
     * Very rough country centroids (extend as needed)
     */
    protected function countryCentroid(string $iso2): array
    {
        return match (strtoupper($iso2)) {
            'DE' => [51.1657, 10.4515],
            'TH' => [13.7563, 100.5018],
            'US' => [39.8283, -98.5795],
            'FR' => [46.2276, 2.2137],
            'GB' => [55.3781, -3.4360],
            default => [20.0, 0.0],
        };
    }
}
