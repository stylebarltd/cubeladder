<?php
use Migrations\BaseMigration;

class AddRatingTrend extends BaseMigration
{
    /**
     * Form of a rated player (bin/cake CalculateRatings): the average
     * per-game rating of their last 10 CTF games minus that of their last
     * 100. Shown as an up / down arrow next to the rating from +-0.5.
     */
    public function change(): void
    {
        $this->table('player_ratings')
            ->addColumn('trend', 'decimal', ['precision' => 4, 'scale' => 2, 'null' => true, 'default' => null, 'after' => 'rating'])
            ->update();
    }
}
