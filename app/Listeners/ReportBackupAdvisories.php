<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;
use App\Models\SystemLog;

/**
 * Plain-English notes about backups for the production status check. They never count as warnings.
 */
class ReportBackupAdvisories
{
    private const STALE_AFTER_HOURS = 26;

    private const VERIFY_STALE_AFTER_DAYS = 10;

    public function handle(ProductionAdvisoriesRequested $request): void
    {
        $latest = fn (string $event): ?SystemLog => rescue(fn () => SystemLog::query()->where('event_type', $event)->latest('occurred_at')->latest('id')->first(), null, false);
        $completed = $latest('backup.completed');

        if ($completed === null) {
            $request->add('No backup has been made yet. Run `php artisan helpdesk:backup` and see docs/backup-and-restore.md.');
        } elseif ($completed->occurred_at->lt(now()->subHours(self::STALE_AFTER_HOURS))) {
            $request->add('The last backup was made '.$completed->occurred_at->diffForHumans().'. Nightly backups are not running: check the scheduler, and System Logs for backup.failed.');
        }

        if ($completed !== null && ! ($completed->metadata['encrypted'] ?? false)) {
            $request->add('The latest backup is NOT encrypted, so it is not copied away either. Set HELPDESK_BACKUP_ENCRYPTION_KEY (php artisan helpdesk:backup-key).');
        }

        $verified = $latest('backup.verified');
        $verifyFailed = $latest('backup.verify_failed');
        if ($verifyFailed !== null && ($verified === null || $verifyFailed->occurred_at->gt($verified->occurred_at))) {
            $request->add('The last backup check FAILED. Run `php artisan helpdesk:backup-verify` and read docs/backup-and-restore.md.');
        } elseif ($completed !== null && ($verified === null || $verified->occurred_at->lt(now()->subDays(self::VERIFY_STALE_AFTER_DAYS)))) {
            $request->add('No backup has been checked in the last '.self::VERIFY_STALE_AFTER_DAYS.' days. Run `php artisan helpdesk:backup-verify`; it also runs every Sunday.');
        }

        if (trim((string) config('helpdesk.backup.copy_path')) === '' && ! config('helpdesk.backup.offsite_confirmed')) {
            $request->add('Backups exist only on this server: a failed disk or ransomware would take them with it. Set HELPDESK_BACKUP_COPY_PATH, or copy them away with your own job and set HELPDESK_BACKUP_OFFSITE_CONFIRMED=true.');
        }
    }
}
