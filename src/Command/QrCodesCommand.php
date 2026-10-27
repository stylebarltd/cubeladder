<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Datasource\ConnectionManager;

/**
 * QR hunt codes (QrHunt, /q/<token>). The tokens are the secret: they only
 * live in this table and in the map's model pack, never in the public map
 * repository.
 *
 *   bin/cake qr_codes list
 *   bin/cake qr_codes add <map> "<where it is hidden>" [--hint "riddle shown after a find"] [--token T]
 *     --token: the token a map's code already carries (ac-maps make_qr.py makes them); without it a new one
 *   bin/cake qr_codes retire <id>     a leaked code: "this code has moved", finds stay
 */
class QrCodesCommand extends Command
{
    public static function defaultName(): string
    {
        return 'qr_codes';
    }

    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('QR hunt codes hidden in maps')
            ->addArgument('action', ['choices' => ['list', 'add', 'retire'], 'required' => true])
            ->addArgument('a', ['help' => 'add: map name / retire: id'])
            ->addArgument('b', ['help' => 'add: where it is hidden (admin note)'])
            ->addOption('hint', ['help' => 'add: a riddle shown after a find'])
            ->addOption('token', ['help' => 'add: use this token (8-24 letters / digits) instead of a new one']);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $conn = ConnectionManager::get('default');
        $site = 'https://cubeladder.ovh';
        switch ($args->getArgument('action')) {
            case 'add':
                $map = (string)$args->getArgument('a');
                $label = (string)$args->getArgument('b');
                if (!preg_match('/^[\w-]{1,64}$/', $map) || $label === '') {
                    $io->err('usage: bin/cake qr_codes add <map> "<where it is hidden>" [--hint "..."]');

                    return self::CODE_ERROR;
                }
                // 10 uppercase letters / digits without look-alikes: QR alphanumeric mode, a small code, ~8e14 tokens
                $token = (string)$args->getOption('token');
                if ($token !== '' && !preg_match('/^[A-Za-z0-9]{8,24}$/', $token)) {
                    $io->err('--token: 8 to 24 letters or digits');

                    return self::CODE_ERROR;
                }
                if ($token === '') {
                    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
                    for ($i = 0; $i < 10; $i++) {
                        $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                    }
                }
                $conn->execute('INSERT INTO qr_codes (token, map, label, hint, active, created) VALUES (?, ?, ?, ?, 1, ?)',
                    [$token, $map, $label, $args->getOption('hint'), date('Y-m-d H:i:s')]);
                $io->out("$site/Q/$token");

                return self::CODE_SUCCESS;
            case 'retire':
                $n = $conn->execute('UPDATE qr_codes SET active = 0 WHERE id = ?', [(int)$args->getArgument('a')])->rowCount();
                $io->out($n ? 'retired' : 'no such code');

                return self::CODE_SUCCESS;
            default:
                foreach ($conn->execute('SELECT c.id, c.map, c.label, c.token, c.active, COUNT(f.id) finds FROM qr_codes c
                    LEFT JOIN qr_finds f ON f.qr_code_id = c.id GROUP BY c.id ORDER BY c.id')->fetchAll('assoc') as $r) {
                    $io->out(sprintf('#%d %-14s %s%s  %d find(s)  %s/Q/%s', $r['id'], $r['map'], $r['label'],
                        $r['active'] ? '' : ' (retired)', $r['finds'], $site, $r['token']));
                }

                return self::CODE_SUCCESS;
        }
    }
}
