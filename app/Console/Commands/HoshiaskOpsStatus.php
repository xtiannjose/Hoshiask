<?php

namespace App\Console\Commands;

use App\Services\Operations\HoshiaskOperationsStatusService;
use Illuminate\Console\Command;

class HoshiaskOpsStatus extends Command
{
    protected $signature = 'hoshiask:ops-status
        {--fail-on-warning : Exit with a failure code when warnings are present}
        {--json : Output the status payload as JSON}';

    protected $description = 'Show production-facing runtime and queue checks for the Hoshiask app.';

    public function __construct(
        private readonly HoshiaskOperationsStatusService $opsStatus,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $status = $this->opsStatus->buildStatus();
        $warnings = $status['warnings'];
        $advisories = $status['advisories'];

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $this->option('fail-on-warning') && $warnings !== []
                ? self::FAILURE
                : ($status['status'] === 'failed' ? self::FAILURE : self::SUCCESS);
        }

        $this->info('Hoshiask operations status');
        $this->line('App version: '.$status['app_version']);
        $this->line('Environment: '.$status['environment']);
        $this->line('App URL: '.$status['app_url']);
        $this->line('PHP version: '.$status['php_version']);
        $this->line('Composer PHP requirement: '.($status['php_requirement'] ?? 'not declared'));
        $this->line('PHP platform status: '.($status['php_supported'] ? 'supported' : 'unsupported'));
        $this->line('Database connectivity: '.($status['database_reachable'] ? 'reachable' : 'failed'));
        $this->line('Cache store: '.$status['cache_store']);
        $this->line('Queue connection: '.$status['queue_connection']);
        $this->line('Queue worker required: '.($status['queue_worker_required'] ? 'yes' : 'no'));
        $this->line('Queue worker status: '.$this->formatQueueWorkerStatus(
            $status['queue_worker_required'],
            $status['queue_worker_running']
        ));
        $this->line('Pending jobs: '.$this->formatCount($status['pending_jobs']));
        $this->line('Failed jobs: '.$this->formatCount($status['failed_jobs']));
        $this->line('Last queued job finished: '.($status['queue_last_job_finished_at'] ?? 'not seen yet'));
        $this->line('Mail mailer: '.$status['mail_mailer']);
        $this->line('Scheduled alert command: '.$status['scheduled_alert_command']);

        if ($advisories !== []) {
            $this->warn('Advisories (the deploy does not stop for these, but something is switched off):');
            foreach ($advisories as $advisory) {
                $this->line('- '.$advisory);
            }
        }

        if ($warnings === []) {
            $this->info('Warnings: none');

            return $status['status'] === 'failed'
                ? self::FAILURE
                : self::SUCCESS;
        }

        $this->warn('Warnings:');
        foreach ($warnings as $warning) {
            $this->line('- '.$warning);
        }

        return $this->option('fail-on-warning')
            ? self::FAILURE
            : ($status['status'] === 'failed' ? self::FAILURE : self::SUCCESS);
    }

    private function formatCount(?int $count): string
    {
        return $count === null ? 'unavailable' : (string) $count;
    }

    private function formatQueueWorkerStatus(bool $queueWorkerRequired, ?bool $queueWorkerRunning): string
    {
        if (! $queueWorkerRequired) {
            return 'not required';
        }

        return match ($queueWorkerRunning) {
            true => 'running',
            false => 'not detected',
            default => 'unavailable',
        };
    }
}
