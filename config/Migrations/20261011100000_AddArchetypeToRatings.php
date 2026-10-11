<?php
use Migrations\BaseMigration;

class AddArchetypeToRatings extends BaseMigration
{
    /**
     * Character (archetype) of a rated player (bin/cake CalculateRatings): what they
     * stand out for in their last 100 CTF games - Headhunter, Butcher,
     * Bomber, Pistolero or Berserker - with their percentile and place in
     * that stat. Null for most players.
     */
    public function change(): void
    {
        $this->table('player_ratings')
            ->addColumn('archetype', 'string', ['limit' => 16, 'null' => true, 'default' => null, 'after' => 'weapon'])
            ->addColumn('archetype_pct', 'integer', ['null' => true, 'default' => null, 'after' => 'archetype'])
            ->addColumn('archetype_rank', 'integer', ['null' => true, 'default' => null, 'after' => 'archetype_pct'])
            ->update();
    }
}
