<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupBundle;
use App\Services\SystemLogService;
use Illuminate\Console\Command;

class HoshiaskBackupVerify extends Command
{
    protected $signature = 'hoshiask:backup-verify
        {file? : A backup file (path, or a name in the backup folder). Default: the newest backup}
        {--key= : The backup key, if it is not in HOSHIASK_BACKUP_ENCRYPTION_KEY}
        {--copy : Check the newest backup in HOSHIASK_BACKUP_COPY_PATH instead}';

    protected $description = 'Open a backup and check that everything in it is complete and usable. Needs no database server.';

    public function handle(BackupBundle $bundles, SystemLogService $systemLogs): int
    {
        $path = $this->resolvePath($bundles);
        if ($path === null) {
            return self::FAILURE;
        }

        $key = trim((string) $this->option('key')) ?: $bundles->key();
        $result = $bundles->verify($path, $key);
        $failed = array_filter($result['checks']);

        $this->line('Backup: '.$path);
        $this->table(['Check', 'Result'], collect($result['checks'])
            ->map(fn (?string $problem, string $check): array => [$check, match ($problem) {
                null => 'OK', 'not checked' => 'not checked', default => 'FAILED: '.$problem
            }])
            ->values()
            ->all());

        $systemLogs->record($failed === [] ? 'backup.verified' : 'backup.verify_failed', $failed === [] ? 'Checked a backup: it is complete and usable.' : 'Checked a backup: it is NOT usable.', [
            'category' => 'system',
            'metadata' => ['file' => basename($path), 'failed_checks' => array_keys($failed)],
        ]);

        if ($failed !== []) {
            $this->error('This backup is NOT usable.');

            return self::FAILURE;
        }

        $this->info('This backup is complete and usable.');

        return self::SUCCESS;
    }

    private function resolvePath(BackupBundle $bundles): ?string
    {
        $file = (string) $this->argument('file');

        if ($file !== '') {
            foreach ([$file, $bundles->directory().DIRECTORY_SEPARATOR.$file] as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
            $this->error("No backup file named {$file}.");

            return null;
        }

        $directory = $this->option('copy') ? trim((string) config('hoshiask.backup.copy_path')) : $bundles->directory();
        if ($directory === '') {
            $this->error('HOSHIASK_BACKUP_COPY_PATH is not set.');

            return null;
        }

        $newest = BackupBundle::newestIn($directory);
        if ($newest === null) {
            $this->error("There is no backup in {$directory}.");
        }

        return $newest;
    }
}
