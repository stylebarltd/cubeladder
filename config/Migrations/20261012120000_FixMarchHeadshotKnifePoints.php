<?php
use Migrations\BaseMigration;

class FixMarchHeadshotKnifePoints extends BaseMigration
{
    /**
     * Games of 9 - 27 March 2026 were imported with headshots and knife kills
     * worth 1 point instead of 2: total_score of 1,868 rows was short by
     * exactly headshot + slashed. Adds it back where total_score is below the
     * points formula (AcLogParser::$pointsMap + bonus), so it is idempotent.
     */
    public function up(): void
    {
        $this->execute('
            UPDATE player_stats_per_game
            SET total_score = total_score + headshot + slashed
            WHERE total_score + headshot + slashed =
                kills + busted + shredded + peppered + sprayed + punctured + splattered + picked_off
                + 2 * headshot + 2 * slashed + 2 * gibbed + 2 * stole_the_flag + 5 * scored_with_the_flag
                - lost_the_flag + 2 * returned_the_flag - teamkills - suicided + bonus
              AND headshot + slashed > 0');
    }

    public function down(): void
    {
    }
}
