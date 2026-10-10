<?php

namespace Tests\Feature;

use Tests\TestCase;

class MailTestCommandTest extends TestCase
{
    public function test_mail_test_command_is_disabled_in_production(): void
    {
        app()->detectEnvironment(fn () => 'production');

        $this->artisan('mail:test valid@example.com')
            ->expectsOutput('The mail:test command is disabled in production.')
            ->assertFailed();
    }

    public function test_mail_test_command_rejects_invalid_email(): void
    {
        $this->artisan('mail:test not-an-email')
            ->expectsOutput('Invalid email address provided.')
            ->assertFailed();
    }

    public function test_mail_test_command_succeeds_with_array_mailer(): void
    {
        config()->set('mail.default', 'array');

        $this->artisan('mail:test valid@example.com')
            ->expectsOutput('Test email sent successfully to valid@example.com.')
            ->assertSuccessful();
    }
}
