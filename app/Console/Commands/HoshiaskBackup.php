<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupBundle;
use App\Services\SystemLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class HoshiaskBackup extends Command
{
    protected $signature = 'hoshiask:backup';

    protected $description = 'Back up the database and every uploaded file into one checked (and, with a key, encrypted) file, and copy it to the second location if one is set.';

    public function handle(BackupBundle $bundles, SystemLogService $systemLogs): int
    {
        $lock = Cache::lock('hoshiask:backup', 3600);
        if (! $lock->get()) {
            $this->error('Another backup is still running. Nothing was done.');

            return self::FAILURE;
        }

        $started = microtime(true);

        try {
            if ($bundles->key() === null) {
                $this->warn('WARNING: HOSHIASK_BACKUP_ENCRYPTION_KEY is not set, so this backup is NOT encrypted and will not be copied away. Run `php artisan hoshiask:backup-key` (see docs/backup-and-restore.md).');
            }

            $backup = $bundles->create();
            $name = basename($backup['path']);
            $this->line("Created {$name} (".self::megabytes($backup['bytes']).", {$backup['file_count']} uploaded files).");
            foreach ($backup['skipped_disks'] as $skipped) {
                $this->line("Skipped {$skipped}: those files are not stored on this server, so they are not in this backup.");
            }

            $problems = array_filter($bundles->verify($backup['path'], $bundles->key())['checks']);
            if ($problems !== []) {
                File::delete($backup['path']);

                throw new RuntimeException('The new backup failed its check and was deleted: '.implode('; ', $problems));
            }
            $this->line('Checked: the backup opens and every part matches.');

            $copied = $this->copyAway($backup['path'], $backup['encrypted']);

            $systemLogs->record('backup.completed', 'Made a backup of the database and uploaded files.', [
                'category' => 'system',
                'metadata' => [
                    'file' => $name,
                    'bytes' => $backup['bytes'],
                    'encrypted' => $backup['encrypted'],
                    'verified' => true,
                    'copied' => $copied,
                    'seconds' => round(microtime(true) - $started, 1),
                    'database_driver' => $backup['driver'],
                    'file_count' => $backup['file_count'],
                ],
            ]);
            $this->info('Backup complete.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $systemLogs->record('backup.failed', 'A backup failed.', [
                'category' => 'system',
                'metadata' => ['message' => $exception->getMessage()],
            ]);
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * Copies an encrypted backup to HOSHIASK_BACKUP_COPY_PATH (a second disk, a mounted network drive), checks the
     * copy, and removes copies there older than the retention period.
     */
    private function copyAway(string $path, bool $encrypted): bool
    {
        $copyPath = rtrim(trim((string) config('hoshiask.backup.copy_path')), '/\\');
        if ($copyPath === '') {
            return false;
        }

        if (! $encrypted) {
            $this->warn('Not copied to '.$copyPath.': an unencrypted backup is never copied away. Set HOSHIASK_BACKUP_ENCRYPTION_KEY first.');

            return false;
        }

        if (! File::isDirectory($copyPath)) {
            throw new RuntimeException("The copy folder {$copyPath} does not exist (is the drive mounted?). The backup itself was kept here.");
        }

        $target = $copyPath.DIRECTORY_SEPARATOR.basename($path);
        $partial = $target.'.partial';
        if (! @copy($path, $partial) || filesize($partial) !== filesize($path) || hash_file('sha256', $partial) !== hash_file('sha256', $path)) {
            @unlink($partial);

            throw new RuntimeException("Copying the backup to {$copyPath} failed or the copy does not match. The backup itself was kept here.");
        }
        rename($partial, $target);

        $cutoff = now()->subDays((int) config('hoshiask.cleanup.backup_retention_days', 14))->getTimestamp();
        $pruned = 0;
        foreach (BackupBundle::bundlesIn($copyPath) as $old) {
            if (filemtime($old) < $cutoff && @unlink($old)) {
                $pruned++;
            }
        }

        $this->line("Copied to {$copyPath} and checked".($pruned > 0 ? "; removed {$pruned} old copies there." : '.'));

        return true;
    }

    private static function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
