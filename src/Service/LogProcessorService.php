<?php
namespace App\Service;

use Cake\Chronos\ChronosDate;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\TableRegistry;
use RuntimeException;
use App\Service\AcLogParser;

class LogProcessorService
{
    use LocatorAwareTrait;

    private $LogOffsets;
    //private AcLogParser $acLogParser;

    public function __construct()
    {
        $this->LogOffsets = TableRegistry::getTableLocator()->get('LogOffsets');

    }

    /**
    /**
     * Entry point
     */
    public function processStream(string $server, string $fileIdentifier, ?string $localFilePath): void
    {

        [$safeOffset, $inode, $linesParsed, $filesize, $result] =
            $this->processLocalFile($server, $fileIdentifier, $localFilePath);

       // echo "# ✔ Parsed {$linesParsed} lines, safe offset={$lastSafeOffset}" . PHP_EOL;
        echo "# Lines: " . ($result['lines'] ?? 0) . PHP_EOL;
        echo "# Games parsed: " . ($result['gamesParsed'] ?? 0) . PHP_EOL;
        echo "# Events parsed: " . ($result['eventsParsed'] ?? 0) . PHP_EOL;
        echo "# Stats parsed: " . ($result['statsParsed'] ?? 0) . PHP_EOL;
        echo "# Players parsed: " . count($result['players'] ?? []) . PHP_EOL;
        echo "### END " . date('Y-m-d H:i:s') . " ###";

        if (!is_int($safeOffset)) {
            throw new RuntimeException('Invalid offset calculated');
        }

        $meaningfulLines =
            ($result['lines'] ?? 0)
            + ($result['gamesParsed'] ?? 0)
            + ($result['eventsParsed'] ?? 0);

        if ($meaningfulLines > 0) {
            $this->saveOffset($server, $fileIdentifier, $safeOffset, $inode);
        } else {
            echo "⚠ Parser ignored all new lines → offset NOT advanced\n";

            // Optional: very slow recovery rewind
            $offsetRow = $this->getOffsetRow($server, $fileIdentifier);
            if ($offsetRow && $filesize > $offsetRow->last_offset) {
                $this->saveOffset(
                    $server,
                    $fileIdentifier,
                    max(0, $offsetRow->last_offset - 1024 * 1024),
                    $inode
                );
            }
        }


        // Cleanup only if games were parsed
        if (($result['gamesParsed'] ?? 0) > 0) {
            $gamesTable = $this->fetchTable('Games');

            $games = $gamesTable->find()
                ->where([
                    'ended_at IS NULL',
                    'started_at <' => new \DateTime('-2 hours'),
                ]);

            foreach ($games as $game) {
                $gamesTable->delete($game);
            }
        }
    }


    /**
     * Parse and resume a local file
     */
    private function processLocalFile(string $server, string $fileIdentifier, string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("File not readable: $path");
        }

        $fp = fopen($path, 'r');
        if (!$fp) {
            throw new RuntimeException("Cannot open file: $path");
        }

        $stat     = fstat($fp);
        $inode    = $stat['ino'];
        $filesize = $stat['size'];

        $offsetRow   = $this->getOffsetRow($server, $fileIdentifier);
        $startOffset = 0;

        if ($offsetRow && $offsetRow->inode === $inode && $filesize >= $offsetRow->last_offset) {
            // normal resume with safety rewind
            //$startOffset = max(0, $offsetRow->last_offset - 8192);
            $startOffset = max(0, $offsetRow->last_offset - 10 * 1024 * 1024);

        }

        fseek($fp, $startOffset);
        echo "# ➡ Starting parse at byte {$startOffset}\n";

        // DISCARD PARTIAL LINE IF RESUMING
        if ($startOffset > 0) {
            fgets($fp);
        }

        $lastSafeOffset = ftell($fp);
        $linesParsed    = 0;

        $date = $this->dateFromLogFilename($fileIdentifier);
        $baseYear = (int)$date->format('Y');
        $this->acLogParser = new AcLogParser($baseYear);
        $this->acLogParser->setServerName($server);

        while (($line = fgets($fp)) !== false) {

            $line = rtrim($line, "\r\n");

            $this->acLogParser->parseLine($line);

            $lastSafeOffset = ftell($fp);
            $linesParsed++;
        }

        fclose($fp);

        $result = $this->acLogParser->getResult();

        return [$lastSafeOffset, $inode, $linesParsed, $filesize, $result];

    }

    /**
     * Get previously saved offset
     */

    private function getOffsetRow(string $server, string $path)
    {
        return $this->LogOffsets
            ->find()
            ->where([
                'server_name' => $server,
                'log_path'    => $this->offsetKey($path),
            ])
            ->first();
    }


    /**
     * Save offset + inode
     */
    private function saveOffset(string $server, string $fileIdentifier, int $offset, int $inode): void
    {
        $row = $this->getOffsetRow($server, $fileIdentifier);

        if (!$row) {
            $row = $this->LogOffsets->newEntity([
                'server_name' => $server,
                'log_path'    => $this->offsetKey($fileIdentifier),
                'last_offset' => $offset,
                'inode'       => $inode,
            ]);
        } else {
            $row->last_offset = $offset;
            $row->inode       = $inode;
        }

        if (!$this->LogOffsets->save($row)) {
            throw new RuntimeException(
                "Failed saving log offset: " . json_encode($row->getErrors())
            );
        }

        echo "# Saved byte_offset={$offset}, inode={$inode}\n";
    }

    private function offsetKey(string $fileIdentifier): string
    {
        return $fileIdentifier;
    }

    public static function dateFromLogFilename(string $filename): ?ChronosDate
    {
        if (!preg_match('/_(\d{8})_/', $filename, $m)) {
            return null;
        }

        return ChronosDate::createFromFormat('Ymd', $m[1]) ?: null;
    }
}
