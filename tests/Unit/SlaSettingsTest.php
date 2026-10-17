<?php

namespace Tests\Unit;

use App\Models\Ticket;
use App\Support\SlaSettings;
use Tests\TestCase;

class SlaSettingsTest extends TestCase
{
    public function test_a_zero_or_negative_setting_falls_back_to_the_default(): void
    {
        config([
            'hoshiask.sla.resolution_target_minutes' => 0,
            'hoshiask.sla.assignment_reminder_minutes' => -5,
            'hoshiask.sla.resolution_buckets.under_one_hour_minutes' => 0,
            'hoshiask.sla.resolution_buckets.under_four_hours_minutes' => -1,
            'hoshiask.sla.resolution_buckets.under_twenty_four_hours_minutes' => 0,
        ]);

        $this->assertSame(240, SlaSettings::resolutionTargetMinutes());
        $this->assertSame(240, Ticket::resolutionTargetMinutes());
        $this->assertSame(210, SlaSettings::assignmentReminderMinutes());
        $this->assertSame(
            ['one_hour' => 60, 'four_hours' => 240, 'one_day' => 1440],
            SlaSettings::resolutionBandMinutes(),
        );
    }

    public function test_the_super_user_thresholds_treat_zero_as_one_minute(): void
    {
        config([
            'hoshiask.sla.super_user_unchecked_minutes' => 0,
            'hoshiask.sla.super_user_unassigned_sla_minutes' => -3,
        ]);

        $this->assertSame(1, SlaSettings::superUserUncheckedMinutes());
        $this->assertSame(1, SlaSettings::superUserUnassignedMinutes());
    }

    public function test_a_valid_setting_is_used_as_given(): void
    {
        config(['hoshiask.sla.resolution_target_minutes' => 90]);

        $this->assertSame(90, SlaSettings::resolutionTargetMinutes());
    }
}
