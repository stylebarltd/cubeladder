<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\Radar;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/**
 * The maps of Radar (/radar): the Radar::POOL_SIZE answers most played in
 * the last --days days, written to resources/radar/pool.json. Maps with the
 * same layout (resources/radar/layouts.json, kasa = ac_casa) count as one
 * answer with their games added up; only maps with a plan (tools/radar/
 * radar_plans.py) can be in it. Each answer's factor (Radar::RARITY) makes
 * the rarer maps worth more.
 *
 *   bin/cake radar_pool [--days 90]
 */
class RadarPoolCommand extends Command
{
    public static function defaultName(): string
    {
        return 'radar_pool';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Build the Radar map pool from the games played')
            ->addOption('days', ['default' => '90', 'help' => 'games of the last N days count']);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $dir = ROOT . '/resources/radar';
        $layouts = is_file("$dir/layouts.json") ? (array)json_decode((string)file_get_contents("$dir/layouts.json"), true) : [];
        if (!$layouts) {
            $io->err("No $dir/layouts.json: build the plans first (tools/radar/radar_plans.py).");

            return self::CODE_ERROR;
        }
        $days = max(1, (int)$args->getOption('days'));
        $rows = ConnectionManager::get('default')->execute(
            'SELECT m.name, COUNT(*) AS plays FROM games g INNER JOIN maps m ON m.id = g.map_id
             WHERE g.started_at >= ? GROUP BY m.name', [gmdate('Y-m-d H:i:s', time() - $days * 86400)])->fetchAll('assoc');
        $answers = [];
        foreach ($rows as $r) {
            $answer = $layouts[$r['name']] ?? null;
            if ($answer === null || !is_file("$dir/$answer.png") || !is_file("$dir/$answer.json")) {
                continue;
            }
            $answers[$answer] ??= ['answer' => $answer, 'maps' => [], 'plays' => 0];
            $answers[$answer]['maps'][] = $r['name'];
            $answers[$answer]['plays'] += (int)$r['plays'];
        }
        usort($answers, fn($a, $b) => $b['plays'] <=> $a['plays']);
        $pool = array_slice($answers, 0, Radar::POOL_SIZE);
        foreach ($pool as $i => &$p) {
            $p['place'] = $i + 1;
            foreach (Radar::RARITY as $upTo => $factor) {
                if ($p['place'] <= $upTo) {
                    $p['factor'] = $factor;
                    break;
                }
            }
            sort($p['maps']);
            $io->out(sprintf('%2d. %-14s %5d games  x%.2f  %s', $p['place'], $p['answer'], $p['plays'], $p['factor'], implode(', ', $p['maps'])));
        }
        unset($p);
        if (count($pool) < Radar::CHOICES) {
            $io->err('Fewer than ' . Radar::CHOICES . ' maps with a plan were played: pool not written.');

            return self::CODE_ERROR;
        }
        file_put_contents("$dir/pool.json", json_encode($pool, JSON_PRETTY_PRINT));
        $io->success(count($pool) . " maps in $dir/pool.json");

        return self::CODE_SUCCESS;
    }
}
