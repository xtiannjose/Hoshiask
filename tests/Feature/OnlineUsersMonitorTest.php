<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Http\Middleware\ThrottlePerLimit;
use App\Models\SystemLog;
use App\Models\User;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class OnlineUsersMonitorTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_ON_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private const SAFARI_ON_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private int $phoneSuffix = 0;

    public function test_non_shadow_users_cannot_open_the_online_users_monitor(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, 'Monitor Admin');

        $this->actingAs($admin)
            ->withSession($this->unlockedSession())
            ->get(route('admin.online-users.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession($this->unlockedSession())
            ->getJson(route('admin.online-users.live'))
            ->assertForbidden();
    }

    public function test_shadow_must_unlock_first_and_is_returned_to_the_monitor(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Monitor Shadow', 'ShadowPass123!');
        $returnPath = '/admin/online-users?segment=staff';

        $this->actingAs($shadow)
            ->get($returnPath)
            ->assertRedirect(route('admin.system-logs.unlock.show', ['return_to' => $returnPath]));

        $this->actingAs($shadow)
            ->get(route('admin.system-logs.unlock.show', ['return_to' => $returnPath]))
            ->assertOk()
            ->assertSee('name="return_to" value="'.e($returnPath).'"', false);

        $this->actingAs($shadow)
            ->post(route('admin.system-logs.unlock.store'), [
                'password' => 'ShadowPass123!',
                'return_to' => $returnPath,
            ])
            ->assertRedirect($returnPath);
    }

    public function test_unlock_ignores_return_paths_outside_the_admin_console(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Redirect Shadow', 'ShadowPass123!');
        // The unlock form allows three attempts a minute; this test submits more than that.
        $this->withoutMiddleware(ThrottlePerLimit::class);

        foreach (['https://evil.example/admin/', '//evil.example/admin/', '/client/dashboard', '/admin/system-logs/unlock'] as $returnTo) {
            $this->actingAs($shadow)
                ->post(route('admin.system-logs.unlock.store'), [
                    'password' => 'ShadowPass123!',
                    'return_to' => $returnTo,
                ])
                ->assertRedirect(route('admin.system-logs.index'));
        }
    }

    public function test_live_refresh_reports_an_expired_lock_as_json(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Expired Shadow');

        $this->actingAs($shadow)
            ->withSession([EnsureSystemLogsUnlocked::SESSION_KEY => now()->subMinute()->toDateTimeString()])
            ->getJson(route('admin.online-users.live'))
            ->assertStatus(423);
    }

    public function test_monitor_lists_only_recently_active_signed_in_sessions(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Watching Shadow');
        $technical = $this->makeUser(User::ROLE_TECHNICAL, 'Active Technician');
        $idleClient = $this->makeUser(User::ROLE_CLIENT, 'Idle Client');
        $staleClient = $this->makeUser(User::ROLE_CLIENT, 'Stale Client');

        $this->insertSession($technical, secondsAgo: 30, ipAddress: '203.0.113.10');
        $this->insertSession($idleClient, secondsAgo: 180, ipAddress: '203.0.113.20', userAgent: self::SAFARI_ON_IPHONE);
        $this->insertSession($staleClient, secondsAgo: 600);
        $this->insertSession(null, secondsAgo: 10);

        $response = $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->get(route('admin.online-users.index'));

        $response->assertOk()
            ->assertSee('Active Technician')
            ->assertSee('Chrome on Windows')
            ->assertSee('203.0.113.10')
            ->assertSee('Idle Client')
            ->assertSee('Safari on iPhone')
            ->assertSee('Idle')
            ->assertDontSee('Stale Client')
            ->assertViewHas('summary', fn (array $summary) => $summary['total'] === 2
                && $summary['staff'] === 1
                && $summary['clients'] === 1
                && $summary['active'] === 1
                && $summary['idle'] === 1);

        $this->assertDatabaseHas('system_logs', [
            'event_type' => 'online_users.viewed',
            'actor_user_id' => $shadow->id,
        ]);

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->getJson(route('admin.online-users.live', ['segment' => 'clients']))
            ->assertOk()
            ->assertJsonPath('html', fn (string $html) => str_contains($html, 'Idle Client')
                && ! str_contains($html, 'Active Technician'));
    }

    public function test_monitor_flags_multiple_ips_and_deactivated_accounts(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Flag Shadow');
        $sharedAccount = $this->makeUser(User::ROLE_TECHNICAL, 'Shared Technician');
        $deactivated = $this->makeUser(User::ROLE_CLIENT, 'Deactivated Client');
        $deactivated->update(['is_active' => false]);

        $this->insertSession($sharedAccount, secondsAgo: 20, ipAddress: '198.51.100.1');
        $this->insertSession($sharedAccount, secondsAgo: 40, ipAddress: '198.51.100.2');
        $this->insertSession($deactivated, secondsAgo: 20);

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->get(route('admin.online-users.index'))
            ->assertOk()
            ->assertSee('Multiple IPs')
            ->assertSee('Deactivated')
            ->assertViewHas('summary', fn (array $summary) => $summary['flagged'] === 2);
    }

    public function test_shadow_can_sign_a_user_out_of_every_device(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Revoking Shadow');
        $technical = $this->makeUser(User::ROLE_TECHNICAL, 'Revoked Technician');
        $technical->forceFill(['remember_token' => 'old-remember-token'])->save();
        $originalUpdatedAt = $technical->fresh()->updated_at;
        $this->insertSession($technical, secondsAgo: 20);
        $this->insertSession($technical, secondsAgo: 90);

        $this->travel(5)->minutes();

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->from(route('admin.online-users.index'))
            ->post(route('admin.online-users.sign-out', $technical))
            ->assertRedirect(route('admin.online-users.index'))
            ->assertSessionHas('success');

        $technical->refresh();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $technical->id)->count());
        $this->assertNotSame('old-remember-token', $technical->remember_token);
        $this->assertTrue($technical->updated_at->equalTo($originalUpdatedAt));

        $log = SystemLog::query()->where('event_type', 'user.sessions.revoked')->first();
        $this->assertNotNull($log);
        $this->assertSame($technical->id, $log->target_id);
        $this->assertSame(2, $log->metadata['ended_count']);
    }

    public function test_shadow_cannot_force_sign_out_their_own_account(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Self Shadow');
        $this->insertSession($shadow, secondsAgo: 20);

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->from(route('admin.online-users.index'))
            ->post(route('admin.online-users.sign-out', $shadow))
            ->assertRedirect(route('admin.online-users.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, DB::table('sessions')->where('user_id', $shadow->id)->count());
    }

    public function test_admin_cannot_force_sign_out_users(): void
    {
        $admin = $this->makeUser(User::ROLE_ADMIN, 'Blocked Admin');
        $technical = $this->makeUser(User::ROLE_TECHNICAL, 'Protected Technician');
        $this->insertSession($technical, secondsAgo: 20);

        $this->actingAs($admin)
            ->withSession($this->unlockedSession())
            ->post(route('admin.online-users.sign-out', $technical))
            ->assertForbidden();

        $this->assertSame(1, DB::table('sessions')->where('user_id', $technical->id)->count());
    }

    public function test_failed_sign_ins_show_lockouts_that_the_shadow_can_clear(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Lockout Shadow');
        $technical = $this->makeUser(User::ROLE_TECHNICAL, 'Locked Technician');
        $technical->update(['username' => 'locked.tech']);

        for ($attempt = 0; $attempt < LoginThrottle::MAX_ATTEMPTS; $attempt++) {
            $this->post(route('login'), ['login' => 'locked.tech', 'password' => 'wrong-password']);
        }
        $this->post(route('login'), ['login' => 'nobody.here', 'password' => 'wrong-password']);

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->get(route('admin.online-users.index'))
            ->assertOk()
            ->assertSee('locked.tech')
            ->assertSee('Locked Technician')
            ->assertSee('Wrong password')
            ->assertSee('Clear lockout')
            // Text that names no account is kept masked: it might have been a password typed in the wrong box.
            ->assertSee('no******** (11 chars)')
            ->assertDontSee('nobody.here')
            ->assertSee('Unknown account');

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->from(route('admin.online-users.index'))
            ->post(route('admin.online-users.lockouts.clear'), ['login' => 'locked.tech'])
            ->assertRedirect(route('admin.online-users.index'))
            ->assertSessionHas('success');

        $this->assertFalse(RateLimiter::tooManyAttempts(LoginThrottle::accountKey('locked.tech', '127.0.0.1'), LoginThrottle::MAX_ATTEMPTS));
        $this->assertDatabaseHas('system_logs', [
            'event_type' => 'auth.lockout_cleared',
            'target_type' => User::class,
            'target_id' => $technical->id,
        ]);

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->from(route('admin.online-users.index'))
            ->post(route('admin.online-users.lockouts.clear'), ['login' => 'locked.tech'])
            ->assertSessionHas('error');
    }

    public function test_profile_sign_in_activity_is_shadow_only_and_behind_the_lock(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Profile Shadow');
        $admin = $this->makeUser(User::ROLE_ADMIN, 'Profile Admin');
        $technical = $this->makeUser(User::ROLE_TECHNICAL, 'Profile Technician');

        SystemLog::create([
            'actor_user_id' => $technical->id,
            'category' => 'auth',
            'event_type' => 'auth.login',
            'target_type' => User::class,
            'target_id' => $technical->id,
            'description' => 'User signed in.',
            'ip_address' => '198.51.100.77',
            'user_agent' => self::CHROME_ON_WINDOWS,
            'occurred_at' => now()->subHour(),
        ]);

        $this->actingAs($shadow)
            ->get(route('admin.users.show', $technical))
            ->assertOk()
            ->assertSee('Sign-in Activity')
            ->assertSee('Unlock to view')
            ->assertDontSee('198.51.100.77');

        $this->actingAs($shadow)
            ->withSession($this->unlockedSession())
            ->get(route('admin.users.show', $technical))
            ->assertOk()
            ->assertSee('Signed in')
            ->assertSee('198.51.100.77')
            ->assertSee('Chrome on Windows');

        $this->actingAs($admin)
            ->withSession($this->unlockedSession())
            ->get(route('admin.users.show', $technical))
            ->assertOk()
            ->assertDontSee('Sign-in Activity')
            ->assertDontSee('198.51.100.77');
    }

    public function test_header_badge_and_user_list_presence_are_shadow_only(): void
    {
        $shadow = $this->makeUser(User::ROLE_SHADOW, 'Badge Shadow');
        $admin = $this->makeUser(User::ROLE_ADMIN, 'Badge Admin');
        $technical = $this->makeUser(User::ROLE_TECHNICAL, 'Badge Technician');
        $superUser = $this->makeUser(User::ROLE_SUPER_USER, 'Badge Super User');
        $client = $this->makeUser(User::ROLE_CLIENT, 'Badge Client');

        $this->insertSession($technical, secondsAgo: 30);
        $this->insertSession($superUser, secondsAgo: 60);
        $this->insertSession($client, secondsAgo: 30);

        $this->actingAs($shadow)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('2 staff online')
            ->assertSee('Last seen')
            ->assertSee('Online now')
            ->assertSee('Never');

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('staff online')
            ->assertDontSee('Last seen')
            ->assertDontSee('Online now');
    }

    private function makeUser(string $role, string $name, string $password = 'Password123!'): User
    {
        $this->phoneSuffix++;

        return User::create([
            'name' => $name,
            'email' => Str::slug($name).'@example.com',
            'phone' => '0912345'.str_pad((string) $this->phoneSuffix, 4, '0', STR_PAD_LEFT),
            'department' => 'Hoshishi',
            'role' => $role,
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
    }

    private function insertSession(
        ?User $user,
        int $secondsAgo,
        string $ipAddress = '203.0.113.99',
        string $userAgent = self::CHROME_ON_WINDOWS,
    ): void {
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->subSeconds($secondsAgo)->getTimestamp(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function unlockedSession(): array
    {
        return [EnsureSystemLogsUnlocked::SESSION_KEY => now()->addMinutes(20)->toDateTimeString()];
    }
}
