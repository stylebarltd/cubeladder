<?php
declare(strict_types=1);

namespace App\View\Helper;

use Cake\View\Helper;

class LayoutHelper extends Helper
{


    public function gameMode($type): string
    {
if(!$type){
    $type = 'unknown';
}

        $gameMode = [
            'dm'    => 'deathmatch',
            'lss'   => 'last swiss standing',
            'osok'  => 'one shot, one kill',
            'pf'    => 'pistol frenzy',
            'surv'  => 'survivor',
            'htf'   => 'hunt the flag',
            'ktf'   => 'keep the flag',
            'tdm'   => 'team deathmatch',
            'tlss'  => 'team last swiss standing',
            'tosok' => 'team one shot one kill',
            'tpf'   => 'team pistol frenzy',
            'tsurv' => 'team survivor',
            'tktf'  => 'team keep the flag',
            'ctf'   => 'capture the flag',
            'unknown'   => 'unknown',
        ];

        $label = $gameMode[$type];

        //dd($gameMode['tktf']);

        return $label;
    }


    public function playerPicture($player = null, $achievementPlayers = []): string
    {

        if(empty($player->picture)){
            return "/img/acl.png";
        }
        return "/img/players/" . $player->picture;
    }

    /**
     * Display a country flag from ISO2 code
     * @param string $iso2 ISO2 country code
     * @return string HTML for the flag
     */
    public function flag(string $iso2): string
    {
        $iso2 = strtoupper($iso2);
        $offset = 0x1F1E6 - ord('A');

        return mb_chr(ord($iso2[0]) + $offset, 'UTF-8') .
            mb_chr(ord($iso2[1]) + $offset, 'UTF-8');
    }

//    public function weapon(array $stats, float $hybridThreshold = 0.2): array
//    {
//        $weapons = [
//            'rifle'    => 0,
//            'smg'      => 0,
//            'sniper'   => 0,
//            'shotgun'  => 0,
//            'carabine' => 0,
//            'grenade' => 0,
//            'knife' => 0,
//            'busted' => 0,
//        ];
//
//        $map = [
//            'shredded'   => 'rifle',
//            'sprayed'    => 'smg',
//            'punctured'  => 'sniper',
//            'headshot'   => 'sniper',
//            'splattered' => 'shotgun',
//            'peppered' => 'shotgun',
//            'picked_off' => 'carabine',
//            'gibbed' => 'grenade',
//            'slashed' => 'knife',
//            'busted' => 'pistol',
//        ];
//
//        foreach ($map as $field => $weapon) {
//            if (!empty($stats[$field])) {
//                $weapons[$weapon] += (int)$stats[$field];
//            }
//        }
//
//        arsort($weapons);
//
//        $values = array_values($weapons);
//        $keys   = array_keys($weapons);
//
//        $mainWeapon  = $keys[0];
//        $mainCount   = $values[0];
//        $secondWeapon = $keys[1];
//        $secondCount  = $values[1];
//
//        // No data at all
//        if ($mainCount === 0) {
//            return [
//                'label' => 'unknown',
//                'weapons' => [],
//            ];
//        }
//
//        // Hybrid detection
//        if ($secondCount > 0 && $secondCount >= ($mainCount * $hybridThreshold)) {
//            return [
//                'label' => $mainWeapon . ' / ' . $secondWeapon,
//                'weapons' => [
//                    $mainWeapon   => $mainCount,
//                    $secondWeapon => $secondCount,
//                ],
//                'hybrid' => true,
//            ];
//        }
//
//        return [
//            'label' => $mainWeapon,
//            'weapons' => [
//                $mainWeapon => $mainCount,
//            ],
//            'hybrid' => false,
//        ];
//    }
    public function weapon(
        array $stats,
        float $hybridThreshold = 0.65,
        float $specialThreshold = 0.2
    ): array {
        $primary = [
            'rifle'    => 0,
            'smg'      => 0,
            'sniper'   => 0,
            'shotgun'  => 0,
            'carabine' => 0,
        ];

        $special = [
            'knife'   => 0,
            'grenade' => 0,
        ];

        $map = [
            // primary
            'shredded'   => ['type' => 'primary', 'weapon' => 'rifle'],
            'sprayed'    => ['type' => 'primary', 'weapon' => 'smg'],
            'punctured'  => ['type' => 'primary', 'weapon' => 'sniper'],
            'headshot'   => ['type' => 'primary', 'weapon' => 'sniper'],
            'splattered' => ['type' => 'primary', 'weapon' => 'shotgun'],
            'picked_off' => ['type' => 'primary', 'weapon' => 'carabine'],

            // special
            'slashed' => ['type' => 'special', 'weapon' => 'knife'],
            'gibbed'  => ['type' => 'special', 'weapon' => 'grenade'],
        ];

        foreach ($map as $field => $cfg) {
            if (!empty($stats[$field])) {
                if ($cfg['type'] === 'primary') {
                    $primary[$cfg['weapon']] += (int)$stats[$field];
                } else {
                    $special[$cfg['weapon']] += (int)$stats[$field];
                }
            }
        }

        arsort($primary);

        $primaryKeys   = array_keys($primary);
        $primaryValues = array_values($primary);

        $mainWeapon   = $primaryKeys[0] ?? null;
        $mainCount    = $primaryValues[0] ?? 0;
        $secondWeapon = $primaryKeys[1] ?? null;
        $secondCount  = $primaryValues[1] ?? 0;

        if ($mainCount === 0) {
            return ['weapons' => []];
        }

        $weapons = [$mainWeapon];

        // Primary hybrid
        if ($secondCount > 0 && $secondCount >= ($mainCount * $hybridThreshold)) {
            $weapons[] = $secondWeapon;
        }

        // Special weapons
        if ($special['knife'] > 0 && $special['knife'] >= ($mainCount * $specialThreshold)) {
            $weapons[] = 'knife';
        }

        if ($special['grenade'] > 0 && $special['grenade'] >= ($mainCount * $specialThreshold)) {
            $weapons[] = 'grenade';
        }

        return [
            'weapons' => $weapons,
        ];
    }

}
