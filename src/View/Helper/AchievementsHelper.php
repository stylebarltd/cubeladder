<?php
namespace App\View\Helper;

use Cake\View\Helper;

class AchievementsHelper extends Helper
{
    protected array $definitions = [

        'kd_ratio' => [
            'label'  => 'Best KD Ratio',
            'icon'   => 'kd_ratio.svg',
            'format' => 'float',
            'decimals' => 2,
        ],
        'best_on_map' => [
            'label'  => 'Best on %s',
            'icon'   => 'fa-solid fa-map-location-dot',
            'format' => 'int',
        ],

        'headshot' => [
            'label'  => 'Headshot King',
            'icon'   => 'headshot.svg',
            'format' => 'int',
        ],

        'scored_with_the_flag' => [
            'label'  => 'Flag Scorer',
            'icon'   => 'scored_with_the_flag.svg',
            'format' => 'int',
        ],

        'slashed' => [
            'label'  => 'Knife Fighter',
            'icon'   => 'slashed.svg',
            'format' => 'int',
        ],

        'gibbed' => [
            'label'  => 'Gib Master',
            'icon'   => 'gibbed.svg',
            'format' => 'int',
        ],

        'suicided' => [
            'label'  => 'Suicidal',
            'icon'   => 'suicided.svg',
            'format' => 'int',
        ],

        'teamkills' => [
            'label'  => 'Teamkiller',
            'icon'   => 'teamkills.svg',
            'format' => 'int',
        ],

        'total_score' => [
            'label'  => 'Top Scorer',
            'icon'   => 'total_score.svg',
            'format' => 'int',
        ],
    ];

//    public function label(string $eventType): string
//    {
//        return $this->definitions[$eventType]['label']
//            ?? ucfirst(str_replace('_', ' ', $eventType));
//    }
    public function labelFor($achievement): string
    {
        $def = $this->definitions[$achievement->event_type] ?? null;

        if (!$def) {
            return ucfirst(str_replace('_', ' ', $achievement->event_type));
        }
//dd($achievement);
        if ($achievement->event_type === 'best_on_map' && $achievement->map) {
            return sprintf($def['label'], $achievement->map->name);
        }

        return $def['label'];
    }

    public function iconClass(string $eventType): string
    {
        return $this->definitions[$eventType]['icon'] ?? 'fa-solid fa-question';
    }

    public function format(string $eventType, $value): string
    {
        $def = $this->definitions[$eventType] ?? [];

        return ($def['format'] ?? 'int') === 'float'
            ? number_format((float)$value, $def['decimals'] ?? 2)
            : number_format((int)$value);
    }

    public function sort(array $achievements): array
    {
        $order = array_keys($this->definitions);

        usort($achievements, fn ($a, $b) =>
            array_search($a->event_type, $order, true)
            <=> array_search($b->event_type, $order, true)
        );

        return $achievements;
    }
}

