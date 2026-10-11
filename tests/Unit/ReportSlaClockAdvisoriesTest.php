<?php

namespace Tests\Unit;

use App\Events\ProductionAdvisoriesRequested;
use App\Listeners\ReportSlaClockAdvisories;
use Tests\TestCase;

class ReportSlaClockAdvisoriesTest extends TestCase
{
    public function test_it_says_nothing_while_the_setting_is_off_or_valid(): void
    {
        foreach ([false, true] as $enabled) {
            config(['helpdesk.sla.business_hours' => ['enabled' => $enabled, 'days' => '1,2,3,4,5', 'start' => '08:00', 'end' => '17:00']]);

            $request = new ProductionAdvisoriesRequested;
            (new ReportSlaClockAdvisories)->handle($request);

            $this->assertSame([], $request->advisories());
        }
    }

    public function test_it_names_closed_dates_that_are_not_dates(): void
    {
        config(['helpdesk.sla.business_hours' => ['enabled' => true, 'days' => '1,2,3,4,5', 'start' => '08:00', 'end' => '17:00', 'closed_dates' => '2026-12-25, Christmas']]);

        $request = new ProductionAdvisoriesRequested;
        (new ReportSlaClockAdvisories)->handle($request);

        $this->assertCount(1, $request->advisories());
        $this->assertStringContainsString('Christmas', $request->advisories()[0]);
    }

    public function test_it_names_a_setting_that_is_on_but_cannot_be_read(): void
    {
        config(['helpdesk.sla.business_hours' => ['enabled' => true, 'days' => '1,2,3,4,5', 'start' => '17:00', 'end' => '08:00']]);

        $request = new ProductionAdvisoriesRequested;
        (new ReportSlaClockAdvisories)->handle($request);

        $this->assertCount(1, $request->advisories());
        $this->assertStringContainsString('HELPDESK_SLA_BUSINESS_HOURS_ENABLED', $request->advisories()[0]);
    }
}
