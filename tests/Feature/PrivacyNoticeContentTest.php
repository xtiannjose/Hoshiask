<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyNoticeContentTest extends TestCase
{
    public function test_the_notice_states_how_long_backups_and_logs_are_kept_and_how_to_complain(): void
    {
        config([
            'helpdesk.cleanup.backup_retention_days' => 21,
            'observability.system_logs.retention_days' => 400,
        ]);

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Encrypted backups of the Platform are kept for 21 days', false)
            ->assertSee('are kept for 400 days', false)
            ->assertSee('National Privacy Commission', false);
    }
}
