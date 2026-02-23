<?php
namespace App\Service;

use GeoIp2\Database\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

class GeoIpService
{
    protected ?Reader $geoReader = null;
    protected array $geoCache = [];

    /**
     * @throws InvalidDatabaseException
     */
    protected function initGeo(): void
    {
        if ($this->geoReader) {
            return;
        }

        $path = CONFIG . 'GeoLite2-Country.mmdb';

        if (!file_exists($path)) {
            return;
        }

        $this->geoReader = new Reader($path);
    }

    public function ipToCountryCached(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        if (isset($this->geoCache[$ip])) {
            return $this->geoCache[$ip];
        }

        // Skip private / reserved IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        try {
            $this->initGeo();

            if (!$this->geoReader) {
                return null;
            }

            $record = $this->geoReader->country($ip);
            $code = $record->country->isoCode ?? null;

            $this->geoCache[$ip] = $code;

            return $code;

        } catch (\Exception $e) {
            return null;
        }
    }
}

