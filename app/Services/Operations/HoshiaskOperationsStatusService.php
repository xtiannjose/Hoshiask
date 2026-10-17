<?php

namespace App\Services\Operations;

use App\Events\ProductionAdvisoriesRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

class HoshiaskOperationsStatusService
{
    /** Written by the five-minute scheduled command, so the status check can tell whether cron is running. */
    public const SCHEDULER_HEARTBEAT_CACHE_KEY = 'hoshiask:scheduler:heartbeat';

    /** Written by a queue worker when it finishes a job, whatever the queue driver. */
    public const QUEUE_HEARTBEAT_CACHE_KEY = 'hoshiask:queue:last-job-finished-at';

    /** Three missed runs of the five-minute command. */
    private const SCHEDULER_STALE_AFTER_MINUTES = 15;

    /** A queued job that has waited this long means the worker is not taking jobs. */
    private const QUEUE_STUCK_AFTER_MINUTES = 15;

    public function buildStatus(bool $includeProcessCheck = true): array
    {
        $warnings = [];
        $phpRequirement = $this->composerPhpRequirement();
        $minimumPhpVersion = $this->minimumPhpVersion($phpRequirement);
        $phpSupported = $minimumPhpVersion === null || version_compare(PHP_VERSION, $minimumPhpVersion, '>=');
        $queueConnection = (string) config('queue.default', 'sync');
        $queueWorkerRequired = $queueConnection !== 'sync';
        $queueWorkerRunning = $this->queueWorkerRunningStatus($queueConnection, $includeProcessCheck);
        $databaseReachable = $this->databaseReachable();
        $pendingJobs = $databaseReachable ? $this->pendingJobCount($queueConnection) : null;
        $failedJobs = $databaseReachable ? $this->countTableRows('failed_jobs') : null;
        $environment = app()->environment();
        $appUrl = (string) config('app.url');

        if (! $phpSupported && $phpRequirement !== null) {
            $warnings[] = sprintf(
                'PHP %s does not satisfy composer.json require.php (%s).',
                PHP_VERSION,
                $phpRequirement
            );
        }

        if (! $databaseReachable) {
            $warnings[] = 'Database connectivity check failed.';
        }

        // A caller that did not look at the process list (the public /health) cannot know; "unknown" is not a
        // warning there, or every monitor without the token would see "degraded" for ever.
        $workerProblem = $queueWorkerRunning === false || ($queueWorkerRunning === null && $includeProcessCheck);
        if ($queueWorkerRequired && $workerProblem) {
            $warnings[] = sprintf(
                'Queue connection "%s" requires a running queue worker for queued mail and jobs.',
                $queueConnection
            );
        }

        if ($environment === 'production') {
            if ((bool) config('app.debug')) {
                $warnings[] = 'APP_DEBUG must be false in production.';
            }

            if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
                $warnings[] = 'APP_URL should point to the public HTTPS production URL.';
            }

            if (! (bool) config('session.secure')) {
                $warnings[] = 'SESSION_SECURE_COOKIE must be true in production.';
            }

            $healthDetailToken = config('hoshiask.health.detail_token');
            if (! is_string($healthDetailToken) || trim($healthDetailToken) === '') {
                $warnings[] = 'Set HOSHIASK_HEALTH_DETAIL_TOKEN before exposing detailed health checks.';
            }
        }

        $advisories = $environment === 'production' ? $this->productionAdvisories($pendingJobs, $failedJobs) : [];

        $status = $databaseReachable && $phpSupported
            ? ($warnings === [] ? 'ok' : 'degraded')
            : 'failed';

        return [
            'status' => $status,
            'checked_at' => Carbon::now()->toIso8601String(),
            'app_version' => (string) config('app.version'),
            'environment' => $environment,
            'app_url' => $appUrl,
            'php_version' => PHP_VERSION,
            'php_requirement' => $phpRequirement,
            'php_supported' => $phpSupported,
            'database_reachable' => $databaseReachable,
            'cache_store' => (string) config('cache.default', 'unknown'),
            'queue_connection' => $queueConnection,
            'queue_worker_required' => $queueWorkerRequired,
            'queue_worker_running' => $queueWorkerRunning,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'queue_last_job_finished_at' => ($lastJobFinishedAt = $this->lastJobFinishedAt()) !== null
                ? Carbon::now()->setTimestamp($lastJobFinishedAt)->toIso8601String()
                : null,
            'mail_mailer' => (string) config('mail.default', 'log'),
            'scheduled_alert_command' => 'tickets:send-alert-emails (technical SLA reminders every 5 minutes)',
            'warnings' => $warnings,
            'advisories' => $advisories,
        ];
    }

    /**
     * Settings that leave the site running but quietly switch something important off. They are
     * shown, never counted as warnings: a warning fails the deploy's health check and rolls it back.
     *
     * @return list<string>
     */
    private function productionAdvisories(?int $pendingJobs, ?int $failedJobs): array
    {
        $advisories = [];

        // Queued mail is produced all day, so a job waiting is normal, and one failed email (a mailbox that bounced)
        // stays in failed_jobs until someone clears it. Neither may fail a deploy or roll it back.
        if (is_int($failedJobs) && $failedJobs > 0) {
            $advisories[] = sprintf(
                '%d queued %s failed (an email or alert that could not be sent). Look at %s with `php artisan queue:failed`; `php artisan queue:retry all` tries again, `php artisan queue:flush` forgets %s.',
                $failedJobs,
                $failedJobs === 1 ? 'job' : 'jobs',
                $failedJobs === 1 ? 'it' : 'them',
                $failedJobs === 1 ? 'it' : 'them'
            );
        }

        $waitedMinutes = is_int($pendingJobs) && $pendingJobs > 0 ? $this->oldestQueuedJobWaitMinutes() : null;
        if ($waitedMinutes !== null && $waitedMinutes >= self::QUEUE_STUCK_AFTER_MINUTES) {
            $advisories[] = sprintf('The oldest of %d queued jobs has waited %d minutes: the queue worker may have stopped. Emails and alerts wait behind it.', $pendingJobs, $waitedMinutes);
        }

        // A queue that is not the jobs table (Redis) has no "waiting since": use the worker's own heartbeat instead.
        // No heartbeat yet means no job has finished since the queue was switched, which proves nothing.
        $lastFinished = $this->queueUsesJobsTable((string) config('queue.default', 'sync')) ? null : $this->lastJobFinishedAt();
        if ($lastFinished !== null && is_int($pendingJobs) && $pendingJobs > 0
            && $lastFinished <= now()->subMinutes(self::QUEUE_STUCK_AFTER_MINUTES)->timestamp) {
            $advisories[] = sprintf('%d queued jobs are waiting and no job has finished for %d minutes: the queue worker may have stopped. Emails and alerts wait behind it.', $pendingJobs, intdiv(now()->timestamp - $lastFinished, 60));
        }

        $mailer = (string) config('mail.default', 'log');
        if (in_array($mailer, ['log', 'array'], true)) {
            $advisories[] = sprintf('MAIL_MAILER is "%s": no email leaves the server (client receipts, resolved notices and staff alerts are only written to the log). Set MAIL_MAILER=smtp and the MAIL_* values.', $mailer);
        }

        if (str_ends_with(strtolower((string) config('mail.from.address')), '@yourdomain.com')) {
            $advisories[] = 'MAIL_FROM_ADDRESS is still the placeholder from .env.example.';
        }

        $logChannels = config('logging.default') === 'stack'
            ? (array) config('logging.channels.stack.channels', [])
            : [config('logging.default')];
        if (in_array('single', $logChannels, true)) {
            $advisories[] = 'Logs go to one file that is never rotated (LOG_STACK=single). Set LOG_STACK=daily to keep the last 14 days.';
        }

        if (config('app.timezone') === 'UTC') {
            $advisories[] = 'APP_TIMEZONE is UTC: ticket times, emails and the daily jobs (backup 02:40, auto-close 03:40) follow UTC. Set APP_TIMEZONE=Asia/Manila (the value in .env.example) unless UTC is intended.';
        }

        $lastBeat = rescue(fn () => Cache::get(self::SCHEDULER_HEARTBEAT_CACHE_KEY), null, false);
        if (! is_numeric($lastBeat)) {
            $advisories[] = 'The scheduler has not reported in yet. Reminder emails, auto-close and nightly backups need `php artisan schedule:run` every minute (cron); if this stays after a few minutes, check the cron entry.';
        } elseif (now()->timestamp - (int) $lastBeat > self::SCHEDULER_STALE_AFTER_MINUTES * 60) {
            $advisories[] = sprintf('The scheduler last ran %d minutes ago. Reminder emails, auto-close and nightly backups are not running: check the cron entry for `php artisan schedule:run`.', intdiv(now()->timestamp - (int) $lastBeat, 60));
        }

        $requested = new ProductionAdvisoriesRequested;
        event($requested);

        return [...$advisories, ...$requested->advisories()];
    }

    private function composerPhpRequirement(): ?string
    {
        $composerJsonPath = base_path('composer.json');
        if (! is_file($composerJsonPath)) {
            return null;
        }

        $composerJson = json_decode((string) file_get_contents($composerJsonPath), true);
        if (! is_array($composerJson)) {
            return null;
        }

        $requirement = $composerJson['require']['php'] ?? null;

        return is_string($requirement) && trim($requirement) !== ''
            ? $requirement
            : null;
    }

    private function minimumPhpVersion(?string $requirement): ?string
    {
        if (! is_string($requirement) || trim($requirement) === '') {
            return null;
        }

        return preg_match('/(\d+\.\d+(?:\.\d+)?)/', $requirement, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Whether waiting jobs are rows of the `jobs` table (the database queue) rather than in another service. */
    private function queueUsesJobsTable(string $queueConnection): bool
    {
        $driver = (string) config('queue.connections.'.$queueConnection.'.driver', $queueConnection);

        return in_array($driver, ['database', 'sync', 'null'], true);
    }

    private function pendingJobCount(string $queueConnection): ?int
    {
        if ($this->queueUsesJobsTable($queueConnection)) {
            return $this->countTableRows('jobs');
        }

        $size = rescue(static fn () => Queue::connection($queueConnection)->size(), null, false);

        return is_int($size) ? $size : null;
    }

    private function lastJobFinishedAt(): ?int
    {
        $stamp = rescue(static fn () => Cache::get(self::QUEUE_HEARTBEAT_CACHE_KEY), null, false);

        return is_numeric($stamp) ? (int) $stamp : null;
    }

    private function countTableRows(string $table): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->count();
    }

    /**
     * Minutes the longest-waiting job in the database queue has been ready to run, or null when there is no such queue.
     */
    private function oldestQueuedJobWaitMinutes(): ?int
    {
        if (! Schema::hasTable('jobs')) {
            return null;
        }

        $availableAt = DB::table('jobs')->whereNull('reserved_at')->min('available_at');

        return is_numeric($availableAt) ? max(0, intdiv(now()->timestamp - (int) $availableAt, 60)) : null;
    }

    private function queueWorkerRunningStatus(string $queueConnection, bool $includeProcessCheck): ?bool
    {
        if ($queueConnection === 'sync') {
            return true;
        }

        $configuredStatus = config('hoshiask.ops.queue_worker_running');
        if (is_bool($configuredStatus)) {
            return $configuredStatus;
        }

        if (! $includeProcessCheck) {
            return null;
        }

        if (! function_exists('shell_exec')) {
            return null;
        }

        $disabledFunctions = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if (in_array('shell_exec', $disabledFunctions, true)) {
            return null;
        }

        $processList = DIRECTORY_SEPARATOR === '\\'
            ? $this->windowsProcessList()
            : shell_exec('ps -eo command= 2>/dev/null');

        if (! is_string($processList) || trim($processList) === '') {
            return null;
        }

        foreach (preg_split("/\r\n|\n|\r/", $processList) as $command) {
            $normalizedCommand = trim((string) $command);

            if (
                $normalizedCommand !== ''
                && (
                    str_contains($normalizedCommand, 'artisan queue:work')
                    || str_contains($normalizedCommand, 'artisan queue:listen')
                    || str_contains($normalizedCommand, 'artisan horizon')
                )
            ) {
                return true;
            }
        }

        return false;
    }

    private function windowsProcessList(): ?string
    {
        $command = 'powershell -NoProfile -NonInteractive -Command "Get-CimInstance Win32_Process | Select-Object -ExpandProperty CommandLine"';
        $processList = shell_exec($command);

        return is_string($processList) ? $processList : null;
    }
}
