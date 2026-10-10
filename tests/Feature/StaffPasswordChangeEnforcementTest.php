<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffPasswordChangeEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_temporary_password_is_redirected_to_account_settings(): void
    {
        $staff = User::create([
            'name' => 'Temp Staff',
            'email' => 'temp-staff@example.com',
            'phone' => '09120000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('temp-password-123'),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($staff)->get(route('admin.dashboard'));

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionHas('error', 'Please change your temporary password before continuing.');
    }

    public function test_staff_can_access_admin_after_changing_temporary_password(): void
    {
        $staff = User::create([
            'name' => 'Temp Staff Change',
            'email' => 'temp-staff-change@example.com',
            'phone' => '09120000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('temp-password-123'),
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $this->actingAs($staff)->put(route('account.settings.update'), [
            'name' => 'Temp Staff Change',
            'phone' => '09120000002',
            'password' => 'New-secure-password-456',
            'password_confirmation' => 'New-secure-password-456',
            'current_password' => 'temp-password-123',
        ])->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Password changed. You can now use the system.');

        $staff->refresh();
        $this->assertFalse($staff->mustChangePassword());

        $dashboardResponse = $this->actingAs($staff)->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
    }
}
