<?php

namespace App\Services\Backup;

use Generator;
use RuntimeException;

/**
 * Writes and reads plain ustar archives (what `tar -cf` makes) piece by piece, so a backup of any size needs only
 * a few megabytes of memory. PharData was not used: it keeps the archive it builds in memory or temp files and
 * rewrites it as entries are added.
 */
class TarArchive
{
    private const BLOCK = 512;

    private const PIECE = 1048576;

    /**
     * @param  resource  $output
     */
    public function __construct(private $output) {}

    /**
     * Adds a file from disk without loading it.
     *
     * @return array{bytes: int, sha256: string}
     */
    public function addFile(string $name, string $sourcePath): array
    {
        $size = filesize($sourcePath);
        $source = fopen($sourcePath, 'rb');

        if ($size === false || $source === false) {
            throw new RuntimeException("Could not read {$sourcePath}.");
        }

        $this->write(self::header($name, $size, (int) filemtime($sourcePath)));
        $hash = hash_init('sha256');
        $copied = 0;

        try {
            while (! feof($source) && ($piece = fread($source, self::PIECE)) !== false && $piece !== '') {
                hash_update($hash, $piece);
                $this->write($piece);
                $copied += strlen($piece);
            }
        } finally {
            fclose($source);
        }

        if ($copied !== $size) {
            throw new RuntimeException("{$sourcePath} changed while it was being backed up.");
        }

        $this->pad($size);

        return ['bytes' => $size, 'sha256' => hash_final($hash)];
    }

    public function addString(string $name, string $contents): void
    {
        $this->write(self::header($name, strlen($contents), time()).$contents);
        $this->pad(strlen($contents));
    }

    public function close(): void
    {
        $this->write(str_repeat("\0", self::BLOCK * 2));
    }

    /**
     * Calls $onEntry(name, size, contentPieces) for every file in the archive, in order. Whatever the callback
     * leaves unread is skipped. Throws when the archive is damaged or cut short.
     *
     * @param  iterable<int, string>  $pieces  The archive's bytes, in pieces of any size.
     * @param  callable(string, int, Generator<int, string>): void  $onEntry
     */
    public static function read(iterable $pieces, callable $onEntry): void
    {
        $source = (static fn () => yield from $pieces)();
        $buffer = '';
        $take = static function (int $length) use ($source, &$buffer): string {
            while (strlen($buffer) < $length && $source->valid()) {
                $buffer .= $source->current();
                $source->next();
            }

            if (strlen($buffer) < $length) {
                throw new RuntimeException('The archive inside the backup is cut short.');
            }

            $taken = substr($buffer, 0, $length);
            $buffer = substr($buffer, $length);

            return $taken;
        };

        while (true) {
            $header = $take(self::BLOCK);

            if ($header === str_repeat("\0", self::BLOCK)) {
                return;
            }

            [$name, $size] = self::parseHeader($header);
            $content = (static function () use ($take, $size): Generator {
                for ($left = $size; $left > 0; $left -= self::PIECE) {
                    yield $take(min(self::PIECE, $left));
                }
            })();

            $onEntry($name, $size, $content);

            while ($content->valid()) {
                $content->next(); // Skip what the callback did not read.
            }

            if ($size % self::BLOCK !== 0) {
                $take(self::BLOCK - $size % self::BLOCK);
            }
        }
    }

    private static function header(string $name, int $size, int $mtime): string
    {
        $prefix = '';
        if (strlen($name) > 100) {
            $split = strrpos(substr($name, 0, 156), '/');
            if ($split === false || strlen($name) - $split - 1 > 100) {
                throw new RuntimeException("The path {$name} is too long for the backup archive.");
            }
            $prefix = substr($name, 0, $split);
            $name = substr($name, $split + 1);
        }

        $header = str_pad($name, 100, "\0")
            .sprintf('%07o', 0600)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%07o', 0)."\0"
            .sprintf('%011o', $size)."\0"
            .sprintf('%011o', $mtime)."\0"
            .'        '
            .'0'
            .str_repeat("\0", 100)
            ."ustar\0".'00'
            .str_repeat("\0", 32 + 32 + 8 + 8)
            .str_pad($prefix, 155, "\0")
            .str_repeat("\0", 12);

        $checksum = array_sum(array_map('ord', str_split($header)));

        return substr_replace($header, sprintf('%06o', $checksum)."\0 ", 148, 8);
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function parseHeader(string $header): array
    {
        $stored = octdec(trim(substr($header, 148, 8), "\0 "));
        $actual = array_sum(array_map('ord', str_split(substr_replace($header, '        ', 148, 8))));

        if (substr($header, 257, 5) !== 'ustar' || $stored !== $actual) {
            throw new RuntimeException('The archive inside the backup is damaged.');
        }

        if (! in_array($header[156], ['0', "\0"], true)) {
            throw new RuntimeException('The archive inside the backup holds something other than plain files.');
        }

        $name = rtrim(substr($header, 0, 100), "\0");
        $prefix = rtrim(substr($header, 345, 155), "\0");

        return [$prefix !== '' ? $prefix.'/'.$name : $name, (int) octdec(trim(substr($header, 124, 12), "\0 "))];
    }

    private function pad(int $size): void
    {
        if ($size % self::BLOCK !== 0) {
            $this->write(str_repeat("\0", self::BLOCK - $size % self::BLOCK));
        }
    }

    private function write(string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($this->output, $bytes);

            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the backup file (is the disk full?).');
            }

            $bytes = substr($bytes, $written);
        }
    }
}
