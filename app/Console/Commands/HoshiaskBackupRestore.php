<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupBundle;
use Illuminate\Console\Command;
use Throwable;

class HoshiaskBackupRestore extends Command
{
    protected $signature = 'hoshiask:backup-restore
        {file : The backup file}
        {directory : An empty folder to unpack it into}
        {--key= : The backup key, if it is not in HOSHIASK_BACKUP_ENCRYPTION_KEY}';

    protected $description = 'Unpack a backup into an empty folder and print the commands that put it back. Changes nothing in the running system.';

    public function handle(BackupBundle $bundles): int
    {
        $directory = rtrim((string) $this->argument('directory'), '/\\');

        try {
            $manifest = $bundles->restore((string) $this->argument('file'), trim((string) $this->option('key')) ?: $bundles->key(), $directory);
        } catch (Throwable $exception) {
            $this->error('Restore failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $database = $manifest['database'];
        $this->info("Unpacked the backup of {$manifest['created_at']} into {$directory}: the database and {$manifest['files']['count']} files.");
        $this->line('Nothing in the running system was changed. To put it back (see docs/backup-and-restore.md):');
        $this->newLine();
        $this->line('1. Database ('.$database['driver'].'), into a NEW empty database:');
        $this->line(match ($database['driver']) {
            'pgsql' => "   gunzip -c {$directory}/{$database['dump_path']} | psql --set ON_ERROR_STOP=1 -h HOST -U USER -d NEW_DATABASE",
            'mysql', 'mariadb' => "   gunzip -c {$directory}/{$database['dump_path']} | mysql -h HOST -u USER -p NEW_DATABASE",
            default => "   gunzip -c {$directory}/{$database['dump_path']} > database/database.sqlite",
        });
        $this->line('2. Uploaded files:');
        $this->line("   copy {$directory}/files/attachments/*      into storage/app/private/attachments/");
        $this->line("   copy {$directory}/files/department-logos/* into storage/app/public/department-logos/");
        $this->line('3. Then: php artisan migrate --force && php artisan optimize');

        return self::SUCCESS;
    }
}
