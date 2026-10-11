<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;
use App\Support\BusinessHours;

/**
 * Plain-English note about the SLA clock for the production status check. It never counts as a warning.
 */
class ReportSlaClockAdvisories
{
    public function handle(ProductionAdvisoriesRequested $request): void
    {
        if (BusinessHours::misconfigured()) {
            $request->add('HELPDESK_SLA_BUSINESS_HOURS_ENABLED is on but the days or hours cannot be read, so SLA time still runs around the clock. Check HELPDESK_SLA_BUSINESS_HOURS_DAYS (1 = Monday to 7 = Sunday), _START and _END (HH:MM, end after start).');
        }
    }
}
