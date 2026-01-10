<?php
namespace App\Model\Behavior;

use Cake\ORM\Behavior;
use GeoIp2\Database\Reader;
use Exception;

class GeoIpBehavior extends Behavior
{
    protected array $_defaultConfig = [
        'ipField' => 'ip',
        'latField' => 'latitude',
        'lonField' => 'longitude',
        'countryField' => 'country',
        'accuracyField' => 'geo_accuracy',
        'dbPath' => '/path/to/GeoLite2-City.mmdb',
        'randomize' => true,
    ];

    public function geoLocate($entity)
    {
        $ipField = $this->getConfig('ipField');

        if (empty($entity->{$ipField})) {
            return;
        }

        try {
            $reader = new Reader($this->getConfig('dbPath'));
            $record = $reader->city($entity->{$ipField});

            $lat = $record->location->latitude;
            $lon = $record->location->longitude;

            // 🔐 Privacy-safe random offset
            if ($this->getConfig('randomize')) {
                $lat += rand(-5, 5) / 100;
                $lon += rand(-5, 5) / 100;
            }

            $entity->{$this->getConfig('latField')} = $lat;
            $entity->{$this->getConfig('lonField')} = $lon;
            $entity->{$this->getConfig('countryField')} = $record->country->isoCode;
            $entity->{$this->getConfig('accuracyField')} = $record->location->accuracyRadius ?? null;

        } catch (Exception $e) {
            // Fail silently (invalid IPs happen)
        }
    }
}
