<?php
use Migrations\BaseMigration;

class PlayerProfilesNameBanner extends BaseMigration
{
    /**
     * QR hunt unlocks 'name' (8 points) and 'banner' (9 points): the colour
     * of the frame around a player's name (a key of QrHunt::NAME_COLORS) and
     * the map behind their profile header (a maps.name with a screenshot,
     * QrHunt::bannerMaps()). The uploaded picture ('upload', 7) is
     * players.picture like every avatar, the last avatars ('avatars2', 10)
     * too: no columns for those.
     */
    public function change(): void
    {
        $this->table('player_profiles')
            ->addColumn('name_color', 'string', ['limit' => 16, 'null' => true, 'default' => null, 'after' => 'weapon_color'])
            ->addColumn('banner_map', 'string', ['limit' => 128, 'null' => true, 'default' => null, 'after' => 'name_color'])
            ->update();
    }
}
