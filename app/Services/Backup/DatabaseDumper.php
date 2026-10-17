<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Writes a database to one file: pg_dump (plain SQL) for PostgreSQL, mysqldump for MySQL/MariaDB, and the
 * database file itself for SQLite. Used by hoshiask:backup-database and hoshiask:backup.
 */
class DatabaseDumper
{
    /**
     * @param  array<string, mixed>  $connection  A connection from config/database.php.
     * @param  bool  $sqliteSnapshot  For SQLite, write a consistent snapshot with VACUUM INTO instead of copying the
     *                                file (a copy taken while someone writes can be torn). Falls back to a copy.
     */
    public function dump(array $connection, string $targetPath, bool $sqliteSnapshot = false): void
    {
        $driver = (string) ($connection['driver'] ?? '');

        match ($driver) {
            'sqlite' => $this->exportSqlite($connection, $targetPath, $sqliteSnapshot),
            'pgsql' => $this->runProcess($this->pgsqlCommand($connection, $targetPath), [
                'PGPASSWORD' => (string) ($connection['password'] ?? ''),
            ]),
            'mysql', 'mariadb' => $this->runProcess($this->mysqlCommand($connection, $targetPath), [
                'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
            ]),
            default => throw new RuntimeException("Database driver [{$driver}] is not supported for automated exports."),
        };
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function exportSqlite(array $connection, string $targetPath, bool $snapshot): void
    {
        $database = (string) ($connection['database'] ?? '');

        if ($database === '' || $database === ':memory:' || ! File::isFile($database)) {
            throw new RuntimeException('SQLite backup requires a file-backed database.');
        }

        if ($snapshot) {
            try {
                (new PDO('sqlite:'.$database))->exec('VACUUM INTO '.$this->quoteSqlite($targetPath));

                if (File::isFile($targetPath)) {
                    return;
                }
            } catch (Throwable) {
                File::delete($targetPath);
            }
        }

        if (! File::copy($database, $targetPath)) {
            throw new RuntimeException('SQLite database backup failed.');
        }
    }

    private function quoteSqlite(string $value): string
    {
        return "'".str_replace("'", "''", $value)."'";
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    private function pgsqlCommand(array $connection, string $targetPath): array
    {
        $command = [
            'pg_dump',
            '--no-owner',
            '--no-acl',
            '--format=plain',
            '--file='.$targetPath,
        ];

        $this->appendOption($command, '--host', $connection['host'] ?? null);
        $this->appendOption($command, '--port', $connection['port'] ?? null);
        $this->appendOption($command, '--username', $connection['username'] ?? null);

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('PostgreSQL database name is missing.');
        }

        $command[] = $database;

        return $command;
    }

    /**
     * @param  array<string, mixed>  $connection
     * @return list<string>
     */
    private function mysqlCommand(array $connection, string $targetPath): array
    {
        $command = [
            'mysqldump',
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--result-file='.$targetPath,
        ];

        $this->appendOption($command, '--host', $connection['host'] ?? null);
        $this->appendOption($command, '--port', $connection['port'] ?? null);
        $this->appendOption($command, '--user', $connection['username'] ?? null);

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('MySQL database name is missing.');
        }

        $command[] = $database;

        return $command;
    }

    /**
     * @param  list<string>  $command
     */
    private function appendOption(array &$command, string $name, mixed $value): void
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === null || $value === '') {
            return;
        }

        $command[] = $name.'='.$value;
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function runProcess(array $command, array $environment): void
    {
        $process = new Process($command, base_path(), array_filter($environment, static fn (string $value): bool => $value !== ''));
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Database backup command failed.';

            throw new RuntimeException($message);
        }
    }
}
