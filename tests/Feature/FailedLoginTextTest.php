<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Models\SystemLog;
use App\Models\User;
use App\Support\LoggedLoginText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * A password typed into the username box by mistake must not be kept in plain text in the audit log,
 * while a real account name or an email address still is (it tells the shadow who was being tried).
 */
class FailedLoginTextTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private const PASSWORD_LIKE = 'purple-giraffe-lamp-77';

    public function test_the_username_of_a_real_account_is_kept(): void
    {
        $this->createClient('Maria Santos', 'maria@example.com', ['username' => 'maria.santos']);

        $this->post(route('login'), ['login' => 'Maria.Santos', 'password' => 'wrong-password']);

        $this->assertSame('Maria.Santos', $this->onlyFailedAttempt()->metadata['login']);
    }

    public function test_the_email_of_a_real_account_is_kept(): void
    {
        $this->createClient('Ana Cruz', 'ana.cruz@example.com');

        $this->post(route('login'), ['login' => 'ana.cruz@example.com', 'password' => 'wrong-password']);

        $this->assertSame('ana.cruz@example.com', $this->onlyFailedAttempt()->metadata['login']);
    }

    public function test_an_email_address_that_matches_nobody_is_kept(): void
    {
        $this->post(route('login'), ['login' => 'typo.address@example.com', 'password' => 'wrong-password']);

        $this->assertSame('typo.address@example.com', $this->onlyFailedAttempt()->metadata['login']);
    }

    public function test_plain_text_that_matches_no_account_is_masked(): void
    {
        $this->post(route('login'), ['login' => 'nobody.here', 'password' => 'wrong-password']);

        $this->assertSame('no******** (11 chars)', $this->onlyFailedAttempt()->metadata['login']);
    }

    public function test_a_password_typed_into_the_username_box_is_found_nowhere_in_the_log(): void
    {
        $this->post(route('login'), ['login' => self::PASSWORD_LIKE, 'password' => 'whatever']);

        $attempt = $this->onlyFailedAttempt();
        $this->assertSame('pu******** (22 chars)', $attempt->metadata['login']);
        $this->assertSame('invalid_credentials', $attempt->metadata['reason']);
        $this->assertSame('username', $attempt->metadata['method']);
        $this->assertStringNotContainsString(self::PASSWORD_LIKE, json_encode(DB::table('system_logs')->get()->all()));
    }

    public function test_an_inactive_account_keeps_its_name_because_the_account_exists(): void
    {
        $this->createClient('Old Account', 'old@example.com', ['username' => 'old.account', 'is_active' => false]);

        $this->post(route('login'), ['login' => 'old.account', 'password' => 'password123']);

        $attempt = $this->onlyFailedAttempt();
        $this->assertSame('old.account', $attempt->metadata['login']);
        $this->assertSame('inactive_account', $attempt->metadata['reason']);
    }

    public function test_the_monitor_shows_masked_attempts_without_a_clear_lockout_button(): void
    {
        $shadow = $this->createUser('Mask Shadow', 'mask-shadow@example.com', User::ROLE_SHADOW);
        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->post(route('login'), ['login' => self::PASSWORD_LIKE, 'password' => 'whatever']);
        }

        $page = $this->actingAs($shadow)
            ->withSession([EnsureSystemLogsUnlocked::SESSION_KEY => now()->addMinutes(20)->toDateTimeString()])
            ->get(route('admin.online-users.index'));

        $page->assertOk()
            ->assertSee('pu******** (22 chars)')
            ->assertSee('Unknown account')
            ->assertDontSee(self::PASSWORD_LIKE)
            ->assertDontSee('Clear lockout');
    }

    public function test_the_scrub_command_masks_old_entries_but_keeps_real_account_names(): void
    {
        $this->createClient('Real Person', 'real@example.com', ['username' => 'real.person']);
        $oldPassword = $this->failedEntry(self::PASSWORD_LIKE, ['method' => 'username', 'reason' => 'invalid_credentials']);
        $realName = $this->failedEntry('Real.Person', ['method' => 'username']);
        $email = $this->failedEntry('someone@example.org', ['method' => 'email']);
        $other = SystemLog::query()->create([
            'category' => 'auth', 'event_type' => 'auth.login', 'description' => 'User signed in.',
            'metadata' => ['login' => self::PASSWORD_LIKE], 'occurred_at' => now()->subDay(),
        ]);
        $before = DB::table('system_logs')->where('id', $oldPassword->id)->first();

        $this->artisan('helpdesk:scrub-failed-login-text', ['--dry-run' => true])
            ->expectsOutputToContain('1 failed sign-in entry would be masked')
            ->assertSuccessful();
        $this->assertSame(self::PASSWORD_LIKE, $oldPassword->fresh()->metadata['login'], 'a dry run changes nothing');

        $this->artisan('helpdesk:scrub-failed-login-text')
            ->expectsOutputToContain('Masked the typed text in 1 failed sign-in entry')
            ->assertSuccessful();

        $this->assertSame('pu******** (22 chars)', $oldPassword->fresh()->metadata['login']);
        $this->assertSame(['login' => 'pu******** (22 chars)', 'method' => 'username', 'reason' => 'invalid_credentials'], $oldPassword->fresh()->metadata);
        $this->assertSame('Real.Person', $realName->fresh()->metadata['login']);
        $this->assertSame('someone@example.org', $email->fresh()->metadata['login']);
        $this->assertSame(self::PASSWORD_LIKE, $other->fresh()->metadata['login'], 'only failed sign-in entries are touched');
        $after = DB::table('system_logs')->where('id', $oldPassword->id)->first();
        $this->assertSame($before->occurred_at, $after->occurred_at);
        $this->assertSame($before->updated_at, $after->updated_at);

        $summary = SystemLog::query()->where('event_type', 'system.scrub_failed_login_text')->get();
        $this->assertCount(1, $summary);
        $this->assertSame(['entries_changed' => 1], $summary->first()->metadata);
        $this->assertStringNotContainsString(self::PASSWORD_LIKE, json_encode(DB::table('system_logs')->where('event_type', 'like', 'auth.login_failed')->get()->all()));
    }

    public function test_the_scrub_command_is_safe_to_run_twice(): void
    {
        $this->failedEntry(self::PASSWORD_LIKE, ['method' => 'username']);

        $this->artisan('helpdesk:scrub-failed-login-text')->assertSuccessful();
        $this->artisan('helpdesk:scrub-failed-login-text')
            ->expectsOutputToContain('Masked the typed text in 0 failed sign-in entries')
            ->assertSuccessful();

        $this->assertSame(1, SystemLog::query()->where('event_type', 'system.scrub_failed_login_text')->count(), 'a run that changes nothing writes no entry');
        $this->assertSame('pu******** (22 chars)', SystemLog::query()->where('event_type', 'auth.login_failed')->first()->metadata['login']);
    }

    public function test_the_mask_format(): void
    {
        $this->assertSame('ab******** (30 chars)', LoggedLoginText::mask(str_repeat('ab', 15)));
        $this->assertSame('fa**** (6 chars)', LoggedLoginText::mask('farmer'));
        $this->assertSame('ab* (3 chars)', LoggedLoginText::mask('abc'));
        $this->assertSame('** (2 chars)', LoggedLoginText::mask('ab'));
        $this->assertSame('* (1 char)', LoggedLoginText::mask('a'));
        $this->assertSame('ña*** (5 chars)', LoggedLoginText::mask('ñasdf'));
        $this->assertSame('', LoggedLoginText::forLog('   ', false));
    }

    public function test_a_masked_text_is_recognised_and_ordinary_text_is_not(): void
    {
        foreach (['fa**** (6 chars)', '** (2 chars)', '* (1 char)', 'pu******** (22 chars)'] as $masked) {
            $this->assertTrue(LoggedLoginText::isMasked($masked), $masked);
        }

        foreach (['farmer', 'maria.santos', 'fa* (6 chars) extra', 'name@example.com', '***** (7 chars)', 'fa*** (6 chars)', 'xyz** (5 chars)'] as $plain) {
            $this->assertFalse(LoggedLoginText::isMasked($plain), $plain);
        }
    }

    private function onlyFailedAttempt(): SystemLog
    {
        $attempts = SystemLog::query()->where('event_type', 'auth.login_failed')->get();
        $this->assertCount(1, $attempts, 'the failed sign-in was not logged, so the checks below would mean nothing');

        return $attempts->first();
    }

    /**
     * @param  array<string, string>  $metadata
     */
    private function failedEntry(string $typed, array $metadata): SystemLog
    {
        return SystemLog::query()->create([
            'category' => 'security',
            'event_type' => 'auth.login_failed',
            'description' => 'Failed login attempt: invalid credentials.',
            'metadata' => ['login' => $typed] + $metadata,
            'occurred_at' => now()->subHours(3),
        ]);
    }
}
