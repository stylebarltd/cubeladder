<?php
declare(strict_types=1);

namespace App\Command;

use App\Model\Table\GamesTable;
use App\Model\Table\PlayerStatsPerGameTable;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

/**
 * Placement bonus of free-for-all games (DM, OSOK, LSS, KTF, PF, survivor -
 * every mode that is not a team mode): 1st / 2nd / 3rd by points get
 * 20 / 15 / 10 extra points. Equal points share the higher place; 2nd needs
 * 4+ players, 3rd 5+ (counted ones, see PlayerStatsPerGameTable::MIN_MINUTES),
 * so nobody farms places on an empty server.
 *
 * Stored in player_stats_per_game.bonus and included in total_score (the
 * import keeps it there, AcLogParser::recalculateTotalScoreInMemory).
 * Idempotent: it sets the bonus, it doesn't add to it.
 *
 *   bin/cake PlacementBonus            games that ended in the last 3 days (after each import)
 *   bin/cake PlacementBonus --all      every game (backfill)
 *   bin/cake PlacementBonus --dry-run  show the changes, write nothing
 */
class PlacementBonusCommand extends Command
{
    /** place => bonus points */
    public const BONUS = [1 => 20, 2 => 15, 3 => 10];
    /** place => counted players the game needs for it */
    public const MIN_PLAYERS = [1 => 4, 2 => 4, 3 => 5];

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Placement bonus (20 / 15 / 10) of free-for-all games')
            ->addOption('all', ['boolean' => true, 'help' => 'Every game, not only the last 3 days'])
            ->addOption('dry-run', ['boolean' => true, 'help' => 'Show the changes, write nothing']);
    }

    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $dry = (bool)$args->getOption('dry-run');
        $conn = $this->fetchTable('Games')->getConnection();

        $where = 'g.ended_at IS NOT NULL';
        if (!$args->getOption('all')) {
            $where .= ' AND g.ended_at >= ' . $conn->getDriver()->quote(date('Y-m-d H:i:s', strtotime('-3 days')));
        }
        $rows = $conn->execute("
            SELECT s.id, s.game_id, s.total_score, s.bonus, s.minutes_played, g.mode, g.inaccurate
            FROM player_stats_per_game s
            INNER JOIN games g ON g.id = s.game_id
            WHERE $where
            ORDER BY s.game_id")->fetchAll('assoc');

        $byGame = [];
        foreach ($rows as $r) {
            $byGame[$r['game_id']][] = $r;
        }

        $changed = $games = $awarded = 0;
        foreach ($byGame as $gameRows) {
            $bonus = $this->bonuses($gameRows);
            $games += $bonus ? 1 : 0;
            foreach ($gameRows as $r) {
                $new = $bonus[$r['id']] ?? 0;
                $awarded += $new;
                if ($new === (int)$r['bonus']) {
                    continue;
                }
                $changed++;
                if ($dry) {
                    $io->out(sprintf('game %s row %s: bonus %d -> %d', $r['game_id'], $r['id'], $r['bonus'], $new));
                    continue;
                }
                $conn->execute('UPDATE player_stats_per_game SET total_score = total_score - bonus + ?, bonus = ? WHERE id = ?',
                    [$new, $new, $r['id']]);
            }
        }

        $io->out(sprintf('%d games checked, %d with a placement bonus (%s points), %d rows %s',
            count($byGame), $games, number_format($awarded), $changed, $dry ? 'would change' : 'changed'));

        return self::CODE_SUCCESS;
    }

    /**
     * Bonus per stats row id of one game (rows without one left out).
     *
     * @param list<array> $rows the game's player_stats_per_game rows
     * @return array<int|string, int>
     */
    private function bonuses(array $rows): array
    {
        $mode = strtolower((string)$rows[0]['mode']);
        if ($rows[0]['inaccurate'] || $mode === '' || in_array($mode, GamesTable::TEAM_MODES, true)) {
            return [];
        }
        // counted players only, by their points without the bonus
        $counted = array_values(array_filter($rows, fn($r) => $r['minutes_played'] === null
            || (int)$r['minutes_played'] >= PlayerStatsPerGameTable::MIN_MINUTES));
        $players = count($counted);
        $points = array_map(fn($r) => (int)$r['total_score'] - (int)$r['bonus'], $counted);

        $bonus = [];
        foreach ($counted as $i => $r) {
            if ($points[$i] <= 0) {
                continue;
            }
            // equal points share the higher place
            $place = 1 + count(array_filter($points, fn($p) => $p > $points[$i]));
            if (isset(self::BONUS[$place]) && $players >= self::MIN_PLAYERS[$place]) {
                $bonus[$r['id']] = self::BONUS[$place];
            }
        }

        return $bonus;
    }
}
