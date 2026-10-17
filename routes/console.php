<?php

use App\Services\SystemLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// The scheduler discards command output, so without this a failing job (e.g. a
// backup that cannot write its file) leaves no trace anywhere. Record failures
// in the app log and on the admin System Logs page.
$reportFailure = static fn (string $command): Closure => static function () use ($command): void {
    $message = "Scheduled command [{$command}] failed.";

    app(SystemLogService::class)->record('scheduler.command.failed', $message, [
        'category' => 'system',
        'metadata' => ['command' => $command],
    ]);

    rescue(static fn () => Log::error($message), report: false);
};

// A killed run (deploy restart, out of memory) leaves its lock behind; the default lock lasts 24 hours,
// which would silence the alerts for a day. Ten minutes is two cycles.
Schedule::command('tickets:send-alert-emails')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onFailure($reportFailure('tickets:send-alert-emails'));

Schedule::command('hoshiask:backup')
    ->dailyAt('02:40')
    ->withoutOverlapping()
    ->onFailure($reportFailure('hoshiask:backup'));

Schedule::command('hoshiask:backup-verify')
    ->weeklyOn(0, '04:20')
    ->withoutOverlapping()
    ->onFailure($reportFailure('hoshiask:backup-verify'));

Schedule::command('hoshiask:cleanup-runtime')
    ->dailyAt('03:10')
    ->withoutOverlapping()
    ->onFailure($reportFailure('hoshiask:cleanup-runtime'));

Schedule::command('tickets:auto-close-resolved')
    ->dailyAt('03:40')
    ->withoutOverlapping()
    ->onFailure($reportFailure('tickets:auto-close-resolved'));

Schedule::command('tickets:auto-cancel-pending')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->onFailure($reportFailure('tickets:auto-cancel-pending'));

Schedule::command('hoshiask:enforce-retention')
    ->dailyAt('03:55')
    ->withoutOverlapping()
    ->onFailure($reportFailure('hoshiask:enforce-retention'));
