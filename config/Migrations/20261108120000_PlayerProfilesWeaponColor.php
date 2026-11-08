<?php
use Migrations\BaseMigration;

class PlayerProfilesWeaponColor extends BaseMigration
{
    /**
     * Weapon colours (QR hunt unlock 'weapons', 6 points): the tint of a
     * player's weapon icons on their profile and in the rankings, a key of
     * QrHunt::WEAPON_COLORS.
     */
    public function change(): void
    {
        $this->table('player_profiles')
            ->addColumn('weapon_color', 'string', ['limit' => 16, 'null' => true, 'default' => null, 'after' => 'bio'])
            ->update();
    }
}
