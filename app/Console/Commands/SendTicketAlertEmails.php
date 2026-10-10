<?php

namespace App\Console\Commands;

use App\Services\Operations\HelpdeskOperationsStatusService;
use App\Services\TicketEmailAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendTicketAlertEmails extends Command
{
    protected $signature = 'tickets:send-alert-emails';

    protected $description = 'Send scheduled technical ticket email alerts and SLA reminders.';

    public function handle(TicketEmailAlertService $alerts): int
    {
        // Tells helpdesk:ops-status that cron is running the scheduler.
        Cache::forever(HelpdeskOperationsStatusService::SCHEDULER_HEARTBEAT_CACHE_KEY, now()->timestamp);

        $summary = $alerts->sendScheduledReminders();

        $this->info(sprintf(
            'Ticket alerts sent. technical_sla=%d unchecked_super=%d unassigned_sla_super=%d client_reply_digests=%d',
            $summary['assigned_sla_for_technical_users'] ?? 0,
            $summary['unchecked_for_super_users'] ?? 0,
            $summary['unassigned_sla_for_super_users'] ?? 0,
            $summary['client_reply_digests'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
