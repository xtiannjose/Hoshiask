<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementSystemArchiveGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_access_system_archive_user_pages(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $archiveUser = $this->createArchiveUser();

        $showResponse = $this->actingAs($superAdmin)->get(route('admin.users.show', $archiveUser));
        $showResponse->assertRedirect(route('admin.users.index'));
        $showResponse->assertSessionHas('error', 'System archive users cannot be accessed from user management.');

        $editResponse = $this->actingAs($superAdmin)->get(route('admin.users.edit', $archiveUser));
        $editResponse->assertRedirect(route('admin.users.index'));
        $editResponse->assertSessionHas('error', 'System archive users cannot be accessed from user management.');
    }

    public function test_super_admin_cannot_update_or_toggle_system_archive_user(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $archiveUser = $this->createArchiveUser();

        $updateResponse = $this->actingAs($superAdmin)->put(route('admin.users.update', $archiveUser), [
            'name' => 'Updated Archive User',
            'email' => 'deleted.client@system.local',
            'phone' => null,
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'is_active' => false,
        ]);

        $updateResponse->assertRedirect(route('admin.users.index'));
        $updateResponse->assertSessionHas('error', 'System archive users cannot be modified.');

        $toggleResponse = $this->actingAs($superAdmin)->post(route('admin.users.toggle-status', $archiveUser));
        $toggleResponse->assertStatus(403);
        $toggleResponse->assertJson([
            'error' => 'System archive users cannot be modified.',
        ]);
    }

    private function createSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin Guard',
            'email' => 'super-admin-guard@example.com',
            'phone' => '09110000999',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createArchiveUser(): User
    {
        return User::create([
            'name' => 'Deleted Client Account',
            'email' => 'deleted.client@system.local',
            'phone' => null,
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);
    }
}
