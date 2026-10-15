<?php
use Migrations\BaseMigration;

class AddCheaterToPlayers extends BaseMigration
{
    /**
     * Marked "Cheater" in Admin > Suspicious (player_reviews): left out of the
     * rankings, the Hall of Fame, the CTF rating, weekly achievements and
     * milestones. Profile, games and scoreboards stay as they are.
     */
    public function change(): void
    {
        $this->table('players')
            ->addColumn('cheater', 'boolean', ['null' => false, 'default' => false, 'after' => 'track'])
            ->update();
    }
}
