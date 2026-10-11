<?php

namespace Tests\Unit;

use App\Events\ProductionAdvisoriesRequested;
use App\Listeners\ReportLegalPlaceholderAdvisories;
use Tests\TestCase;

class ReportLegalPlaceholderAdvisoriesTest extends TestCase
{
    private function advisories(): array
    {
        $request = new ProductionAdvisoriesRequested;
        (new ReportLegalPlaceholderAdvisories)->handle($request);

        return $request->advisories();
    }

    public function test_it_names_each_contact_address_that_is_still_the_example(): void
    {
        config(['legal.dpo_email' => 'privacy@example.com', 'legal.support_email' => 'help@hoshishi.example.ph']);
        $this->assertCount(1, $this->advisories());
        $this->assertStringContainsString('LEGAL_DPO_EMAIL still holds', $this->advisories()[0]);

        config(['legal.support_email' => 'support@example.com']);
        $this->assertStringContainsString('LEGAL_DPO_EMAIL and LEGAL_SUPPORT_EMAIL still hold', $this->advisories()[0]);
    }

    public function test_real_addresses_raise_nothing(): void
    {
        config(['legal.dpo_email' => 'dpo@hoshishi.ph', 'legal.support_email' => 'support@hoshishi.ph']);

        $this->assertSame([], $this->advisories());
    }
}
