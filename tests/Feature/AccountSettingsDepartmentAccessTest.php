<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountSettingsDepartmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_access_account_settings_route(): void
    {
        $client = User::create([
            'name' => 'Client A',
            'email' => 'client-a@example.com',
            'phone' => '09123456789',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($client)->get(route('account.settings'));
        $response->assertForbidden();
    }

    public function test_super_user_cannot_change_username_department_or_email_but_can_change_display_name(): void
    {
        $superUser = User::create([
            'name' => 'Super User A',
            'username' => 'super.user.a',
            'email' => 'super-user-a@example.com',
            'phone' => '09111111111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->put(route('account.settings.update'), [
            'username' => 'super.user.updated',
            'name' => 'Super User Updated',
            'email' => 'super-user-updated@example.com',
            'phone' => '09111112222',
            'department' => 'Orbit',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superUser->refresh();
        $this->assertSame('super.user.a', $superUser->username);
        $this->assertSame('Super User Updated', $superUser->name);
        $this->assertSame('super-user-a@example.com', $superUser->email);
        $this->assertSame('Hoshishi', $superUser->department);
        $this->assertSame('09111112222', $superUser->phone);
    }

    public function test_technical_can_access_account_settings_and_change_display_name_phone_and_password_only(): void
    {
        $technical = User::create([
            'name' => 'Technical A',
            'username' => 'technical.a',
            'email' => 'technical-a@example.com',
            'phone' => '09122223333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $settingsResponse = $this->actingAs($technical)->get(route('account.settings'));
        $settingsResponse->assertOk();

        $response = $this->actingAs($technical)->put(route('account.settings.update'), [
            'username' => 'technical.updated',
            'name' => 'Technical Updated',
            'email' => 'technical-updated@example.com',
            'phone' => '09122224444',
            'department' => 'Hoshishi',
            'current_password' => 'password123',
            'password' => 'Newpassword123',
            'password_confirmation' => 'Newpassword123',
        ]);

        $response->assertRedirect(route('account.settings'));

        $technical->refresh();
        $this->assertSame('technical.a', $technical->username);
        $this->assertSame('Technical Updated', $technical->name);
        $this->assertSame('technical-a@example.com', $technical->email);
        $this->assertSame('Hoshishi', $technical->department);
        $this->assertSame('09122224444', $technical->phone);
        $this->assertTrue(Hash::check('Newpassword123', $technical->password));
    }

    public function test_super_admin_can_change_department_from_account_settings(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin A',
            'username' => 'super.admin.a',
            'email' => 'super-admin-a@example.com',
            'phone' => '09100001111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('account.settings.update'), [
            'username' => 'super.admin.a',
            'name' => 'Super Admin A',
            'email' => 'super-admin-a@example.com',
            'phone' => '09100001111',
            'department' => 'Orbit',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superAdmin->refresh();
        $this->assertSame('Orbit', $superAdmin->department);
    }

    public function test_super_admin_and_non_super_admin_have_expected_department_input_rendering(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin View',
            'username' => 'super.admin.view',
            'email' => 'super-admin-view@example.com',
            'phone' => '09100002222',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superUser = User::create([
            'name' => 'Super User View',
            'username' => 'super.user.view',
            'email' => 'super-user-view@example.com',
            'phone' => '09100003333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superAdminResponse = $this->actingAs($superAdmin)->get(route('account.settings'));
        $superAdminResponse->assertOk();
        $this->assertStringContainsString('<select', $superAdminResponse->getContent());
        $this->assertStringContainsString('name="department"', $superAdminResponse->getContent());

        auth()->logout();

        $superUserResponse = $this->actingAs($superUser)->get(route('account.settings'));
        $superUserResponse->assertOk();
        $this->assertStringContainsString('name="department"', $superUserResponse->getContent());
        $this->assertStringContainsString('readonly aria-readonly=true', $superUserResponse->getContent());
        $this->assertStringContainsString('Only admins can change account email addresses.', $superUserResponse->getContent());
        $this->assertStringContainsString('Only admins can change sign-in usernames for this account role.', $superUserResponse->getContent());
        $this->assertStringContainsString('Display Name', $superUserResponse->getContent());
        $technical = User::create([
            'name' => 'Technical View',
            'username' => 'technical.view',
            'email' => 'technical-view@example.com',
            'phone' => '09100004444',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        auth()->logout();

        $technicalResponse = $this->actingAs($technical)->get(route('account.settings'));
        $technicalResponse->assertOk();
        $this->assertStringContainsString('Only admins can change sign-in usernames for this account role.', $technicalResponse->getContent());
    }

    public function test_changing_email_requires_current_password_for_super_admin(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Email',
            'username' => 'super.admin.email',
            'email' => 'super-admin-email@example.com',
            'phone' => '09111112222',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)
            ->from(route('account.settings'))
            ->put(route('account.settings.update'), [
                'username' => 'super.admin.email',
                'name' => 'Super Admin Email',
                'email' => 'super-admin-email-new@example.com',
                'phone' => '09111112222',
                'department' => 'Hoshishi',
            ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionHasErrors('current_password');
    }

    public function test_changing_email_with_current_password_succeeds_for_super_admin(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Email 2',
            'username' => 'super.admin.email2',
            'email' => 'super-admin-email2@example.com',
            'phone' => '09111113333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('account.settings.update'), [
            'username' => 'super.admin.email2',
            'name' => 'Super Admin Email 2',
            'email' => 'super-admin-email2-new@example.com',
            'phone' => '09111113333',
            'department' => 'Hoshishi',
            'current_password' => 'password123',
        ]);

        $response->assertRedirect(route('account.settings'));

        $superAdmin->refresh();
        $this->assertSame('super-admin-email2-new@example.com', $superAdmin->email);
    }

    public function test_super_admin_can_clear_their_email_from_account_settings(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin No Email',
            'username' => 'super.admin.noemail',
            'email' => 'super-admin-noemail@example.com',
            'phone' => '09111114444',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('account.settings.update'), [
            'username' => 'super.admin.noemail',
            'name' => 'Super Admin No Email',
            'email' => '',
            'phone' => '09111114444',
            'department' => 'Hoshishi',
            'current_password' => 'password123',
        ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionDoesntHaveErrors('email');

        $this->assertNull($superAdmin->fresh()->email);
    }

    public function test_shadow_account_can_clear_their_email_from_account_settings(): void
    {
        $shadow = User::create([
            'name' => 'Shadow No Email',
            'username' => 'shadow.noemail',
            'email' => 'shadow-noemail@example.com',
            'phone' => '09111115555',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->put(route('account.settings.update'), [
            'username' => 'shadow.noemail',
            'name' => 'Shadow No Email',
            'email' => '',
            'phone' => '09111115555',
            'department' => 'Hoshishi',
            'current_password' => 'password123',
        ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionDoesntHaveErrors('email');

        $this->assertNull($shadow->fresh()->email);
    }

    public function test_two_super_admins_can_both_have_a_blank_email_without_a_unique_conflict(): void
    {
        $firstSuperAdmin = User::create([
            'name' => 'Blank Email One',
            'username' => 'blank.email.one',
            'email' => null,
            'phone' => '09111116666',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $secondSuperAdmin = User::create([
            'name' => 'Blank Email Two',
            'username' => 'blank.email.two',
            'email' => 'blank-email-two@example.com',
            'phone' => '09111117777',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($secondSuperAdmin)->put(route('account.settings.update'), [
            'username' => 'blank.email.two',
            'name' => 'Blank Email Two',
            'email' => '',
            'phone' => '09111117777',
            'department' => 'Hoshishi',
            'current_password' => 'password123',
        ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionDoesntHaveErrors('email');
        $this->assertNull($secondSuperAdmin->fresh()->email);
        $this->assertNull($firstSuperAdmin->fresh()->email);
    }
}
