<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;

/**
 * Sets the password of the admin area (/admin): asks for it and writes its
 * bcrypt hash to config/admin_password.php (not in git, not deployed).
 *
 *   bin/cake admin_password
 */
class AdminPasswordCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        system('stty -echo 2>/dev/null');
        $password = $io->ask('New admin password (12+ characters)');
        $again = $io->ask('Once more');
        system('stty echo 2>/dev/null');
        $io->out('');

        if (strlen($password) < 12 || $password !== $again) {
            $io->error(strlen($password) < 12 ? 'Too short - 12 characters at least.' : 'The two differ.');

            return self::CODE_ERROR;
        }
        $file = CONFIG . 'admin_password.php';
        file_put_contents($file, "<?php\n// bin/cake admin_password\nreturn " . var_export(password_hash($password, PASSWORD_DEFAULT), true) . ";\n");
        chmod($file, 0600);
        $io->success('Admin password set - log in at /admin.');

        return self::CODE_SUCCESS;
    }
}
