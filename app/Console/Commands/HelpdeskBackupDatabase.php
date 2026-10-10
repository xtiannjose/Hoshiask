<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseDumper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class HelpdeskBackupDatabase extends Command
{
    protected $signature = 'helpdesk:backup-database
        {--connection= : Database connection to export}
        {--path= : Storage path for backup exports}';

    protected $description = 'Export the configured helpdesk database to private storage.';

    public function handle(DatabaseDumper $dumper): int
    {
        $connectionName = (string) ($this->option('connection') ?: config('database.default'));
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            $this->error("Database connection [{$connectionName}] is not configured.");

            return self::FAILURE;
        }

        $driver = (string) ($connection['driver'] ?? '');
        $disk = (string) config('helpdesk.backup.disk', config('filesystems.default', 'local'));
        $backupPath = trim((string) ($this->option('path') ?: config('helpdesk.backup.path', 'backups')), '/');

        if ($backupPath === '') {
            $this->error('Backup path cannot be empty.');

            return self::FAILURE;
        }

        Storage::disk($disk)->makeDirectory($backupPath);

        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $filename = sprintf('%s-%s.%s', $connectionName, now()->format('Ymd-His'), $extension);
        $relativePath = $backupPath.'/'.$filename;
        $targetPath = Storage::disk($disk)->path($relativePath);

        try {
            $dumper->dump($connection, $targetPath);
        } catch (\Throwable $exception) {
            if (File::exists($targetPath)) {
                File::delete($targetPath);
            }

            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Database backup created.');
        $this->line('Path: '.$relativePath);

        return self::SUCCESS;
    }
}
