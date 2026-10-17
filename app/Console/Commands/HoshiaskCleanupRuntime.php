<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\CredentialHandoff;
use App\Models\SystemLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\SplFileInfo;

class HoshiaskCleanupRuntime extends Command
{
    protected $signature = 'hoshiask:cleanup-runtime';

    protected $description = 'Clean transient runtime artifacts and prune stale Hoshiask handoff data.';

    private const HANDOFF_CACHE_KEY_PREFIX = 'managed-user-password-handoff:';

    private const BACKUPS_ALWAYS_KEPT = 3;

    public function handle(): int
    {
        $deletedRuntimeFiles = 0;
        $deletedRuntimeDirectories = 0;
        $prunedHandoffs = $this->pruneStaleCredentialHandoffs();
        $prunedSystemLogs = $this->pruneOldSystemLogs();
        $prunedCacheEntries = $this->pruneExpiredCacheEntries();
        $deletedOrphanAttachments = $this->deleteZeroByteOrphanAttachments();
        $deletedBackupExports = $this->pruneStorageFilesOlderThan(
            (string) config('hoshiask.backup.disk', config('filesystems.default', 'local')),
            (string) config('hoshiask.backup.path', 'backups'),
            (int) config('hoshiask.cleanup.backup_retention_days', 14),
            keepNewest: self::BACKUPS_ALWAYS_KEPT
        );
        $deletedSeededCredentialExports = $this->pruneStorageFilesOlderThan(
            (string) config('hoshiask.seed_client_credentials_disk', config('filesystems.default', 'local')),
            (string) config('hoshiask.seed_client_credentials_path', 'seeded-client-credentials'),
            (int) config('hoshiask.cleanup.seeded_client_credentials_retention_days', 14)
        );
        $deletedImportExports = $this->pruneStorageFilesOlderThan(
            (string) config('hoshiask.ticket_import_disk', config('filesystems.default', 'local')),
            (string) config('hoshiask.ticket_import_path', 'imports'),
            (int) config('hoshiask.cleanup.import_retention_days', 7)
        );

        if (File::delete(base_path('.phpunit.result.cache'))) {
            $deletedRuntimeFiles++;
        }

        [$phpstanFiles, $phpstanDirectories] = $this->purgeDirectoryContents(storage_path('phpstan'));
        $deletedRuntimeFiles += $phpstanFiles;
        $deletedRuntimeDirectories += $phpstanDirectories;

        [$testingFiles, $testingDirectories] = $this->purgeDirectoryContents(storage_path('framework/testing'));
        $deletedRuntimeFiles += $testingFiles;
        $deletedRuntimeDirectories += $testingDirectories;

        $this->info('Hoshiask runtime cleanup complete.');
        $this->line('Runtime files deleted: '.$deletedRuntimeFiles);
        $this->line('Runtime directories deleted: '.$deletedRuntimeDirectories);
        $this->line('Stale credential handoffs pruned: '.$prunedHandoffs);
        $this->line('Old system logs pruned: '.$prunedSystemLogs);
        $this->line('Expired cache entries pruned: '.$prunedCacheEntries);
        $this->line('Zero-byte orphan attachments deleted: '.$deletedOrphanAttachments);
        $this->line('Old backup exports deleted: '.$deletedBackupExports);
        $this->line('Old seeded credential exports deleted: '.$deletedSeededCredentialExports);
        $this->line('Old import files deleted: '.$deletedImportExports);

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function purgeDirectoryContents(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [0, 0];
        }

        $deletedFiles = 0;
        $deletedDirectories = 0;
        $entries = File::glob($directory.DIRECTORY_SEPARATOR.'*') ?: [];

        foreach ($entries as $entry) {
            if (basename($entry) === '.gitignore') {
                continue;
            }

            if (File::isDirectory($entry)) {
                File::deleteDirectory($entry);
                $deletedDirectories++;

                continue;
            }

            if (File::delete($entry)) {
                $deletedFiles++;
            }
        }

        return [$deletedFiles, $deletedDirectories];
    }

    private function pruneStaleCredentialHandoffs(): int
    {
        $staleHandoffs = CredentialHandoff::query()
            ->where(function ($query) {
                $query->whereNotNull('consumed_at')
                    ->orWhere('expires_at', '<=', now());
            })
            ->get();

        foreach ($staleHandoffs as $handoff) {
            Cache::forget($this->handoffSecretCacheKey((string) $handoff->temporary_password));
            $handoff->delete();
        }

        return $staleHandoffs->count();
    }

    private function pruneOldSystemLogs(): int
    {
        $retentionDays = (int) config('observability.system_logs.retention_days', 365);
        if ($retentionDays <= 0) {
            return 0;
        }

        return SystemLog::query()
            ->where('occurred_at', '<', now()->subDays($retentionDays))
            ->delete();
    }

    /**
     * The database cache removes an expired entry only when that exact key is read again. Keys built from a search
     * or a filter combination are rarely read twice, so their expired rows would stay forever.
     */
    private function pruneExpiredCacheEntries(): int
    {
        $store = (string) config('cache.default');
        if (config("cache.stores.{$store}.driver") !== 'database') {
            return 0;
        }

        return DB::connection(config("cache.stores.{$store}.connection"))
            ->table((string) config("cache.stores.{$store}.table", 'cache'))
            ->where('expiration', '<=', now()->getTimestamp())
            ->delete();
    }

    private function deleteZeroByteOrphanAttachments(): int
    {
        $disk = (string) config('hoshiask.attachments_disk', 'local');
        $attachmentRoot = Storage::disk($disk)->path('attachments');

        if (! File::isDirectory($attachmentRoot)) {
            return 0;
        }

        $deletedFiles = 0;

        /** @var SplFileInfo $file */
        foreach (File::allFiles($attachmentRoot) as $file) {
            if ($file->getFilename() === '.gitignore' || $file->getSize() !== 0) {
                continue;
            }

            $relativePath = str_replace('\\', '/', ltrim($file->getRelativePathname(), '\\/'));
            $databasePath = 'attachments/'.$relativePath;

            $hasAttachmentRecord = Attachment::query()
                ->where('file_path', $databasePath)
                ->exists();

            if ($hasAttachmentRecord) {
                continue;
            }

            if (File::delete($file->getPathname())) {
                $deletedFiles++;
            }
        }

        return $deletedFiles;
    }

    /**
     * Deletes files older than the retention period, except the newest $keepNewest, so a run of failed backups
     * can never age out the last good ones.
     */
    private function pruneStorageFilesOlderThan(string $disk, string $relativePath, int $retentionDays, int $keepNewest = 0): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $trimmedPath = trim($relativePath, '/');
        if ($trimmedPath === '') {
            return 0;
        }

        $directory = Storage::disk($disk)->path($trimmedPath);
        if (! File::isDirectory($directory)) {
            return 0;
        }

        $cutoffTimestamp = now()->subDays($retentionDays)->getTimestamp();
        $deletedFiles = 0;

        $files = array_values(array_filter(
            File::allFiles($directory),
            static fn (SplFileInfo $file): bool => $file->getFilename() !== '.gitignore'
        ));
        usort($files, static fn (SplFileInfo $a, SplFileInfo $b): int => $b->getMTime() <=> $a->getMTime());
        $protected = array_slice($files, 0, max(0, $keepNewest));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getMTime() >= $cutoffTimestamp || in_array($file, $protected, true)) {
                continue;
            }

            if (File::delete($file->getPathname())) {
                $deletedFiles++;
            }
        }

        return $deletedFiles;
    }

    private function handoffSecretCacheKey(string $cacheToken): string
    {
        return self::HANDOFF_CACHE_KEY_PREFIX.$cacheToken;
    }
}
