<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;

/**
 * Plain-English notes about the Pending ticket reminder and automatic cancel for the production status check.
 * They never count as warnings.
 */
class ReportPendingTicketAdvisories
{
    public function handle(ProductionAdvisoriesRequested $request): void
    {
        $cancelAfter = max(0, (int) config('hoshiask.pending_auto_cancel_days', 14));
        $followUpAfter = max(0, (int) config('hoshiask.pending_follow_up_days', 3));

        if ($cancelAfter < 1) {
            return;
        }

        if ($followUpAfter < 1) {
            $request->add("Pending tickets are cancelled automatically after {$cancelAfter} days with no activity, and staff are not reminded first (HOSHIASK_PENDING_FOLLOW_UP_DAYS=0). Set a reminder period, or HOSHIASK_PENDING_AUTO_CANCEL_DAYS=0 to stop the automatic cancel.");
        } elseif ($cancelAfter <= $followUpAfter) {
            $request->add("Pending tickets are cancelled after {$cancelAfter} days, which is not later than the first staff reminder at {$followUpAfter} days, so nobody is reminded in time. Raise HOSHIASK_PENDING_AUTO_CANCEL_DAYS or lower HOSHIASK_PENDING_FOLLOW_UP_DAYS.");
        }
    }
}
