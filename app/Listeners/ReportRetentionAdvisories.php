<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;
use App\Models\SystemLog;
use App\Services\Retention\TicketRetentionService;
use Illuminate\Support\Carbon;

/**
 * Plain-English notes about the retention clean-up for the production status check. They never count as warnings.
 */
class ReportRetentionAdvisories
{
    /** An enabled clean-up that has work waiting and has not cleaned anything for this many days is stuck. */
    private const STALE_AFTER_DAYS = 3;

    public function __construct(private readonly TicketRetentionService $retention) {}

    public function handle(ProductionAdvisoriesRequested $request): void
    {
        $waiting = rescue(fn () => $this->retention->countEligible(), null, false);

        if (! is_int($waiting) || $waiting === 0) {
            return;
        }

        $years = $this->retention->years();
        $tickets = $waiting === 1 ? '1 ticket is' : "{$waiting} tickets are";

        if (! $this->retention->isEnabled()) {
            $request->add("{$tickets} older than the retention period ({$years} years) and still hold personal details. The clean-up is switched off (HELPDESK_RETENTION_ENABLED=false). Run `php artisan helpdesk:enforce-retention --dry-run` to review them.");

            return;
        }

        $lastRun = rescue(fn () => SystemLog::query()->where('event_type', 'retention.enforced')->max('occurred_at'), null, false);

        if ($lastRun === null || ! Carbon::parse($lastRun)->gt(now()->subDays(self::STALE_AFTER_DAYS))) {
            $request->add("{$tickets} older than the retention period ({$years} years) and the clean-up has not removed anything in the last ".self::STALE_AFTER_DAYS.' days. Check that the scheduler runs, and look in System Logs for failures of helpdesk:enforce-retention.');
        }
    }
}
