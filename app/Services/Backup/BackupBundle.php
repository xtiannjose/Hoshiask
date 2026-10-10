<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use Throwable;

/**
 * One backup = one file holding the database dump, every uploaded file and a manifest that lists them with their
 * sizes and SHA-256 sums: helpdesk-YYYYmmdd-HHMMSS.tar.enc (encrypted, see BackupEncryption) or .tar (no key set).
 * Making, checking and unpacking all stream, so memory stays flat whatever the size.
 */
class BackupBundle
{
    public const MANIFEST = 'manifest.json';

    /** Disks whose files go into the backup, with the folder on that disk (empty = the whole disk). */
    private const FILE_SOURCES = ['attachments' => 'attachments', 'department-logos' => ''];

    public function __construct(private readonly DatabaseDumper $dumper) {}

    public function directory(): string
    {
        $disk = (string) config('helpdesk.backup.disk', config('filesystems.default', 'local'));
        $path = trim((string) config('helpdesk.backup.path', 'backups'), '/');
        Storage::disk($disk)->makeDirectory($path);

        return Storage::disk($disk)->path($path);
    }

    public function key(): ?string
    {
        $key = trim((string) config('helpdesk.backup.encryption_key'));

        return $key === '' ? null : $key;
    }

    /**
     * The newest bundle in a folder, or null.
     */
    public static function newestIn(string $directory): ?string
    {
        $bundles = array_merge(glob($directory.'/helpdesk-*.tar.enc') ?: [], glob($directory.'/helpdesk-*.tar') ?: []);
        usort($bundles, fn (string $a, string $b): int => strcmp(basename($b), basename($a)));

        return $bundles[0] ?? null;
    }

    /**
     * @return array{path: string, bytes: int, encrypted: bool, file_count: int, files_bytes: int, skipped_disks: list<string>, driver: string}
     */
    public function create(): array
    {
        $directory = $this->directory();
        $connectionName = (string) config('database.default');
        $connection = (array) config("database.connections.{$connectionName}");
        $driver = (string) ($connection['driver'] ?? '');
        [$sources, $skipped] = $this->fileSources();

        $this->checkFreeSpace($directory, $driver, $connection, $sources);

        $stamp = now()->format('Ymd-His');
        $work = $directory.'/.work-'.$stamp;
        File::ensureDirectoryExists($work, 0700); // holds the plaintext dump until it is encrypted
        $key = $this->key();
        $final = $directory.'/helpdesk-'.$stamp.($key !== null ? '.tar.enc' : '.tar');

        try {
            $raw = $work.'/dump.raw';
            $this->dumper->dump($connection, $raw, sqliteSnapshot: true);
            $tables = $driver === 'sqlite' ? self::sqliteRowCounts($raw) : null;
            $dumpName = $driver === 'sqlite' ? 'database/database.sqlite.gz' : 'database/dump.sql.gz';
            $gz = $work.'/dump.gz';
            self::gzip($raw, $gz);
            File::delete($raw);

            if ($tables === null) {
                $inspection = self::inspectSqlDump($gz, $driver);
                if (! $inspection['complete']) {
                    throw new RuntimeException('The database dump is incomplete (its closing line is missing).');
                }
                $tables = $inspection['tables'];
            }
            $this->checkEveryTableIsInTheDump($tables, $driver);

            $tarPath = $work.'/bundle.tar';
            $tarHandle = fopen($tarPath, 'wb') ?: throw new RuntimeException("Could not create {$tarPath}.");
            $tar = new TarArchive($tarHandle);
            $dump = $tar->addFile($dumpName, $gz);
            $items = [];
            foreach ($sources as $label => $root) {
                foreach (File::allFiles($root, true) as $file) {
                    if (! is_file($file->getPathname())) {
                        continue; // Deleted since the folder was listed.
                    }
                    $path = 'files/'.$label.'/'.str_replace('\\', '/', $file->getRelativePathname());
                    $items[] = ['path' => $path] + $tar->addFile($path, $file->getPathname());
                }
            }

            $tar->addString(self::MANIFEST, (string) json_encode([
                'format_version' => 1,
                'created_at' => now()->toIso8601String(),
                'app_version' => (string) config('app.version'),
                'php_version' => PHP_VERSION,
                'database' => [
                    'driver' => $driver,
                    'connection' => $connectionName,
                    'dump_path' => $dumpName,
                    'dump_bytes' => $dump['bytes'],
                    'dump_sha256' => $dump['sha256'],
                    'tables' => $tables,
                ],
                'files' => [
                    'count' => count($items),
                    'bytes' => array_sum(array_column($items, 'bytes')),
                    'items' => $items,
                    'skipped_disks' => $skipped,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $tar->close();
            fclose($tarHandle);

            if ($key !== null) {
                $in = fopen($tarPath, 'rb');
                $out = fopen($final.'.partial', 'wb');
                try {
                    (new BackupEncryption($key))->encrypt($in, $out);
                } finally {
                    fclose($in);
                    fclose($out);
                }
                rename($final.'.partial', $final);
            } else {
                rename($tarPath, $final);
            }
            @chmod($final, 0600);

            return [
                'path' => $final,
                'bytes' => (int) filesize($final),
                'encrypted' => $key !== null,
                'file_count' => count($items),
                'files_bytes' => array_sum(array_column($items, 'bytes')),
                'skipped_disks' => $skipped,
                'driver' => $driver,
            ];
        } catch (Throwable $exception) {
            File::delete([$final, $final.'.partial']);

            throw $exception;
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Opens a bundle and checks everything in it. Needs no database server.
     *
     * @return array{checks: array<string, string|null>, manifest: array<string, mixed>|null}
     *                                                                                        Each check maps to null (passed) or the reason it failed.
     */
    public function verify(string $path, ?string $key): array
    {
        $checks = ['Opens completely' => null, 'Manifest present' => null, 'Every part matches its checksum' => null, 'Database dump is usable' => null, 'File count and size match' => null];
        $seen = [];
        $manifestJson = null;
        $dumpCopy = tempnam(sys_get_temp_dir(), 'hbk');

        try {
            try {
                $this->read($path, $key, function (string $name, int $size, iterable $content) use (&$seen, &$manifestJson, $dumpCopy): void {
                    $hash = hash_init('sha256');
                    $copy = str_starts_with($name, 'database/') ? fopen($dumpCopy, 'wb') : null;
                    $json = '';
                    foreach ($content as $piece) {
                        hash_update($hash, $piece);
                        if ($copy !== null) {
                            fwrite($copy, $piece);
                        }
                        if ($name === self::MANIFEST) {
                            $json .= $piece;
                        }
                    }
                    if ($copy !== null) {
                        fclose($copy);
                    }
                    if ($name === self::MANIFEST) {
                        $manifestJson = $json;
                    }
                    $seen[$name] = ['bytes' => $size, 'sha256' => hash_final($hash)];
                });
            } catch (Throwable $exception) {
                $checks['Opens completely'] = $exception->getMessage();

                return ['checks' => self::skipRest($checks), 'manifest' => null];
            }

            $manifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
            if (! is_array($manifest) || ($manifest['format_version'] ?? null) !== 1) {
                $checks['Manifest present'] = 'manifest.json is missing or not understood.';

                return ['checks' => self::skipRest($checks), 'manifest' => null];
            }

            $expected = [$manifest['database']['dump_path'] => ['bytes' => $manifest['database']['dump_bytes'], 'sha256' => $manifest['database']['dump_sha256']]];
            foreach ($manifest['files']['items'] as $item) {
                $expected[$item['path']] = ['bytes' => $item['bytes'], 'sha256' => $item['sha256']];
            }
            $wrong = [];
            foreach ($expected as $name => $want) {
                if (! isset($seen[$name])) {
                    $wrong[] = "{$name} is missing";
                } elseif ($seen[$name]['sha256'] !== $want['sha256'] || $seen[$name]['bytes'] !== $want['bytes']) {
                    $wrong[] = "{$name} does not match";
                }
            }
            $checks['Every part matches its checksum'] = $wrong === [] ? null : implode('; ', array_slice($wrong, 0, 5)).(count($wrong) > 5 ? ' (and '.(count($wrong) - 5).' more)' : '');

            $checks['Database dump is usable'] = $this->checkDump($dumpCopy, (string) $manifest['database']['driver'], (array) $manifest['database']['tables']);

            $files = array_filter($seen, fn (string $name): bool => str_starts_with($name, 'files/'), ARRAY_FILTER_USE_KEY);
            $checks['File count and size match'] = count($files) === $manifest['files']['count'] && array_sum(array_column($files, 'bytes')) === $manifest['files']['bytes']
                ? null
                : 'The backup holds '.count($files).' files ('.array_sum(array_column($files, 'bytes')).' bytes); the manifest lists '.$manifest['files']['count'].' ('.$manifest['files']['bytes'].' bytes).';

            return ['checks' => $checks, 'manifest' => $manifest];
        } finally {
            @unlink($dumpCopy);
        }
    }

    /**
     * Unpacks a bundle into an empty folder. Never touches the live database or the live upload folders.
     *
     * @return array<string, mixed> The manifest.
     */
    public function restore(string $path, ?string $key, string $directory): array
    {
        if (File::isDirectory($directory) && (File::files($directory, true) !== [] || File::directories($directory) !== [])) {
            throw new RuntimeException("{$directory} is not empty. Restore into an empty folder.");
        }
        File::ensureDirectoryExists($directory);
        $manifest = null;

        $this->read($path, $key, function (string $name, int $size, iterable $content) use ($directory, &$manifest): void {
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)|\\\\|^[A-Za-z]:#', $name) === 1) {
                throw new RuntimeException("The backup holds an unsafe path ({$name}); nothing more was unpacked.");
            }
            $target = $directory.'/'.$name;
            File::ensureDirectoryExists(dirname($target));
            $out = fopen($target, 'wb') ?: throw new RuntimeException("Could not write {$target}.");
            foreach ($content as $piece) {
                fwrite($out, $piece);
            }
            fclose($out);
            if ($name === self::MANIFEST) {
                $manifest = json_decode((string) file_get_contents($target), true);
            }
        });

        if (! is_array($manifest)) {
            throw new RuntimeException('The backup has no manifest.');
        }

        return $manifest;
    }

    /**
     * @param  callable(string, int, iterable<int, string>): void  $onEntry
     */
    private function read(string $path, ?string $key, callable $onEntry): void
    {
        $handle = @fopen($path, 'rb') ?: throw new RuntimeException("Could not open {$path}.");

        try {
            $encrypted = fread($handle, strlen(BackupEncryption::MAGIC)) === BackupEncryption::MAGIC;
            rewind($handle);

            if ($encrypted && $key === null) {
                throw new RuntimeException('This backup is encrypted. Give the key with --key or set HELPDESK_BACKUP_ENCRYPTION_KEY.');
            }

            $pieces = $encrypted
                ? (new BackupEncryption((string) $key))->decrypt($handle)
                : (static function () use ($handle) {
                    while (! feof($handle) && ($piece = fread($handle, 1048576)) !== false && $piece !== '') {
                        yield $piece;
                    }
                })();

            TarArchive::read($pieces, $onEntry);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, int|null>  $tables
     */
    private function checkDump(string $gzPath, string $driver, array $tables): ?string
    {
        if ($driver === 'sqlite') {
            $plain = tempnam(sys_get_temp_dir(), 'hbk');
            $pdo = null;
            try {
                self::gunzip($gzPath, $plain);
                $pdo = new PDO('sqlite:'.$plain, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
                $integrity = $pdo->query('PRAGMA integrity_check')?->fetchColumn();
                if ($integrity !== 'ok') {
                    return 'SQLite integrity check says: '.$integrity;
                }
                $pdo = null;
                $counts = self::sqliteRowCounts($plain);
            } catch (Throwable $exception) {
                return 'The SQLite database could not be opened: '.$exception->getMessage();
            } finally {
                $pdo = null;
                @unlink($plain);
            }

            return self::compareTables($tables, $counts);
        }

        $inspection = self::inspectSqlDump($gzPath, $driver);
        if (! $inspection['header']) {
            return 'The dump does not start like a '.($driver === 'pgsql' ? 'pg_dump' : 'mysqldump').' file.';
        }
        if (! $inspection['complete']) {
            return 'The dump is cut short (its closing line is missing).';
        }

        return self::compareTables($tables, $inspection['tables']);
    }

    /**
     * @param  array<string, int|null>  $expected
     * @param  array<string, int|null>  $actual
     */
    private static function compareTables(array $expected, array $actual): ?string
    {
        $problems = [];
        foreach ($expected as $table => $rows) {
            if (! array_key_exists($table, $actual)) {
                $problems[] = "table {$table} is missing";
            } elseif ($rows !== null && $actual[$table] !== $rows) {
                $problems[] = "table {$table} has {$actual[$table]} rows instead of {$rows}";
            }
        }

        return $problems === [] ? null : implode('; ', array_slice($problems, 0, 5));
    }

    /**
     * Reads a gzipped pg_dump or mysqldump file line by line: was it complete, which tables does it create and
     * (pg_dump) how many rows each COPY block holds. MySQL row counts are not counted (null).
     *
     * @return array{header: bool, complete: bool, tables: array<string, int|null>}
     */
    public static function inspectSqlDump(string $gzPath, string $driver): array
    {
        $gz = gzopen($gzPath, 'rb') ?: throw new RuntimeException('Could not open the database dump.');
        $header = false;
        $complete = false;
        $tables = [];
        $copying = null;
        $lineNumber = 0;

        try {
            while (($line = gzgets($gz, 1048576)) !== false) {
                $lineNumber++;
                $trimmed = rtrim($line, "\r\n");

                if ($copying !== null) {
                    if ($trimmed === '\\.') {
                        $copying = null;
                    } elseif (str_ends_with($line, "\n")) {
                        $tables[$copying]++;
                    }

                    continue;
                }

                if ($lineNumber <= 5 && ($trimmed === '-- PostgreSQL database dump' || str_starts_with($trimmed, '-- MySQL dump') || str_starts_with($trimmed, '-- MariaDB dump'))) {
                    $header = true;
                }
                if ($trimmed === '-- PostgreSQL database dump complete' || str_starts_with($trimmed, '-- Dump completed')) {
                    $complete = true;
                }
                if (preg_match('/^CREATE TABLE (?:public\.)?[`"]?([^`"\s(]+)[`"]? \(/', $trimmed, $match) === 1) {
                    $tables[$match[1]] ??= $driver === 'pgsql' ? 0 : null;
                }
                if (preg_match('/^COPY (?:public\.)?"?([^"\s(]+)"? .*FROM stdin;$/', $trimmed, $match) === 1) {
                    $copying = $match[1];
                    $tables[$copying] = 0;
                }
            }
        } finally {
            gzclose($gz);
        }

        return ['header' => $header, 'complete' => $complete && $copying === null, 'tables' => $tables];
    }

    /**
     * @return array<string, int>
     */
    private static function sqliteRowCounts(string $path): array
    {
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
        $counts = [];
        foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")?->fetchAll(PDO::FETCH_COLUMN) ?? [] as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $table).'"')?->fetchColumn();
        }

        return $counts;
    }

    /**
     * A table the live database has but the dump lacks means the dump is not the whole database.
     *
     * @param  array<string, int|null>  $tables
     */
    private function checkEveryTableIsInTheDump(array $tables, string $driver): void
    {
        $live = collect(Schema::getTables())
            ->filter(fn (array $table): bool => $driver !== 'pgsql' || ($table['schema'] ?? 'public') === 'public')
            ->pluck('name')
            ->reject(fn (string $name): bool => str_starts_with($name, 'sqlite_'))
            ->all();
        $missing = array_diff($live, array_keys($tables));

        if ($missing !== []) {
            throw new RuntimeException('The database dump is missing tables: '.implode(', ', $missing).'.');
        }
    }

    /**
     * @return array{0: array<string, string>, 1: list<string>} Folders to back up by label, and the labels skipped.
     */
    private function fileSources(): array
    {
        $sources = [];
        $skipped = [];
        $disks = ['attachments' => (string) config('helpdesk.attachments_disk', 'local'), 'department-logos' => 'department-logos'];

        foreach (self::FILE_SOURCES as $label => $folder) {
            $disk = $disks[$label];
            if (config("filesystems.disks.{$disk}.driver") !== 'local') {
                $skipped[] = $label;

                continue;
            }
            $root = Storage::disk($disk)->path($folder);
            if (File::isDirectory($root)) {
                $sources[$label] = $root;
            }
        }

        return [$sources, $skipped];
    }

    /**
     * @param  array<string, mixed>  $connection
     * @param  array<string, string>  $sources
     */
    private function checkFreeSpace(string $directory, string $driver, array $connection, array $sources): void
    {
        $database = match ($driver) {
            'sqlite' => (int) @filesize((string) ($connection['database'] ?? '')),
            'pgsql' => (int) rescue(fn () => DB::scalar('SELECT pg_database_size(current_database())'), 0, false),
            default => 0,
        };
        $files = 0;
        foreach ($sources as $root) {
            foreach (File::allFiles($root, true) as $file) {
                $files += $file->getSize();
            }
        }
        $needed = 2 * ($database + $files) + 50 * 1024 * 1024;
        $free = @disk_free_space($directory);

        if ($free !== false && $free < $needed) {
            throw new RuntimeException(sprintf('Not enough free disk space for a backup: about %d MB is needed in %s and %d MB is free.', $needed / 1048576, $directory, $free / 1048576));
        }
    }

    private static function gzip(string $from, string $to): void
    {
        $in = fopen($from, 'rb');
        $out = gzopen($to, 'wb6');
        if ($in === false || $out === false) {
            throw new RuntimeException('Could not compress the database dump.');
        }
        while (! feof($in) && ($piece = fread($in, 1048576)) !== false && $piece !== '') {
            if (gzwrite($out, $piece) !== strlen($piece)) {
                throw new RuntimeException('Could not compress the database dump (is the disk full?).');
            }
        }
        fclose($in);
        gzclose($out);
    }

    private static function gunzip(string $from, string $to): void
    {
        $in = gzopen($from, 'rb');
        $out = fopen($to, 'wb');
        if ($in === false || $out === false) {
            throw new RuntimeException('Could not unpack the database dump.');
        }
        while (! gzeof($in) && ($piece = gzread($in, 1048576)) !== false && $piece !== '') {
            fwrite($out, $piece);
        }
        gzclose($in);
        fclose($out);
    }

    /**
     * @param  array<string, string|null>  $checks
     * @return array<string, string|null>
     */
    private static function skipRest(array $checks): array
    {
        $failed = false;
        foreach ($checks as $name => $problem) {
            if ($failed) {
                $checks[$name] = 'not checked';
            }
            $failed = $failed || $problem !== null;
        }

        return $checks;
    }
}
