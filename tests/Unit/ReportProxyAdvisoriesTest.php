<?php

namespace Tests\Unit;

use App\Events\ProductionAdvisoriesRequested;
use App\Listeners\ReportProxyAdvisories;
use Tests\TestCase;

class ReportProxyAdvisoriesTest extends TestCase
{
    public function test_it_names_a_trust_everything_proxy_setting(): void
    {
        config(['security.trusted_proxies' => '*']);

        $request = new ProductionAdvisoriesRequested;
        (new ReportProxyAdvisories)->handle($request);

        $this->assertCount(1, $request->advisories());
        $this->assertStringContainsString('TRUSTED_PROXIES is "*"', $request->advisories()[0]);
    }

    public function test_it_says_nothing_about_a_list_of_proxy_addresses(): void
    {
        config(['security.trusted_proxies' => '10.0.0.5,10.0.0.6']);

        $request = new ProductionAdvisoriesRequested;
        (new ReportProxyAdvisories)->handle($request);

        $this->assertSame([], $request->advisories());
    }
}
