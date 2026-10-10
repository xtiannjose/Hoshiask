<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AdminUserPasswordChangeSessionsTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_setting_a_password_in_the_edit_form_ends_the_users_sessions_and_logs_it(): void
    {
        $admin = $this->createUser('Edit Admin', 'edit-admin@example.com', User::ROLE_ADMIN);
        $technical = $this->createUser('Edit Technical', 'edit-technical@example.com', User::ROLE_TECHNICAL);
        $this->insertSession($technical);
        $this->insertSession($technical);

        $this->actingAs($admin)->put(route('admin.users.update', $technical), $this->sameProfile($technical) + [
            'password' => 'a-brand-new-Passw0rd!',
            'password_confirmation' => 'a-brand-new-Passw0rd!',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $technical->id)->count());

        $log = SystemLog::query()->where('event_type', 'user.updated')->latest('id')->firstOrFail();
        $this->assertTrue($log->metadata['credentials_changed']);
        $this->assertSame(2, $log->metadata['sign_ins_ended']);
        $this->assertStringNotContainsString('a-brand-new-Passw0rd!', json_encode($log->metadata));
    }

    public function test_changing_a_role_ends_the_users_sessions(): void
    {
        $admin = $this->createUser('Role Admin', 'role-admin@example.com', User::ROLE_ADMIN);
        $technical = $this->createUser('Role Technical', 'role-technical@example.com', User::ROLE_TECHNICAL);
        $this->insertSession($technical);

        $this->actingAs($admin)->put(route('admin.users.update', $technical), array_merge($this->sameProfile($technical), [
            'role' => User::ROLE_SUPER_USER,
        ]))->assertSessionHasNoErrors();

        $this->assertSame(User::ROLE_SUPER_USER, $technical->fresh()->role);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $technical->id)->count());
    }

    public function test_an_edit_that_changes_neither_password_nor_role_keeps_the_sessions(): void
    {
        $admin = $this->createUser('Name Admin', 'name-admin@example.com', User::ROLE_ADMIN);
        $technical = $this->createUser('Name Technical', 'name-technical@example.com', User::ROLE_TECHNICAL);
        $this->insertSession($technical);

        $this->actingAs($admin)->put(route('admin.users.update', $technical), array_merge($this->sameProfile($technical), [
            'name' => 'Renamed Technical',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Renamed Technical', $technical->fresh()->name);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $technical->id)->count());

        $log = SystemLog::query()->where('event_type', 'user.updated')->latest('id')->firstOrFail();
        $this->assertFalse($log->metadata['credentials_changed']);
        $this->assertSame(0, $log->metadata['sign_ins_ended']);
    }

    /**
     * @return array<string, mixed>
     */
    private function sameProfile(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'department' => $user->department,
            'role' => $user->role,
            'is_active' => true,
        ];
    }

    private function insertSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '203.0.113.7',
            'user_agent' => 'Test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
