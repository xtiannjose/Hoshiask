<?php

namespace Tests\Feature;

use App\Models\CredentialHandoff;
use App\Models\User;
use App\Support\DefaultPasswordResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserManagementNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_cannot_create_technical_account(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user@example.com',
            'phone' => '09100000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->post(route('admin.users.store'), [
            'name' => 'Technical User',
            'email' => 'technical-user@example.com',
            'phone' => '09222222222',
            'department' => 'Orbit',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', [
            'email' => 'technical-user@example.com',
        ]);
    }

    public function test_super_admin_can_still_create_technical_account(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin@example.com',
            'phone' => '09100000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'Technical User',
            'email' => 'technical-user@example.com',
            'phone' => '09222222222',
            'department' => 'Orbit',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertRedirect(route('admin.users.index', [
            'search' => 'technical.user',
        ]));
        $this->assertDatabaseHas('users', [
            'email' => 'technical-user@example.com',
            'role' => User::ROLE_TECHNICAL,
            'department' => 'Orbit',
        ]);
    }

    public function test_super_admin_can_set_username_and_display_name_separately_when_creating_user(): void
    {
        $superAdmin = User::create([
            'name' => 'Shadow Admin',
            'username' => 'shadow.admin',
            'email' => 'shadow-admin@example.com',
            'phone' => '09100000030',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'username' => 'technical.agent',
            'name' => 'Technical Agent',
            'email' => 'technical-agent@example.com',
            'phone' => '09223334444',
            'department' => 'Orbit',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertRedirect(route('admin.users.index', [
            'search' => 'technical.agent',
        ]));
        $this->assertDatabaseHas('users', [
            'username' => 'technical.agent',
            'name' => 'Technical Agent',
            'email' => 'technical-agent@example.com',
            'role' => User::ROLE_TECHNICAL,
            'department' => 'Orbit',
        ]);
    }

    public function test_super_admin_can_create_user_without_email_and_phone(): void
    {
        $superAdmin = User::create([
            'name' => 'Optional Fields Admin',
            'email' => 'optional-fields-admin@example.com',
            'phone' => '09100000031',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'username' => 'no.contact.user',
            'name' => 'No Contact User',
            'email' => '',
            'phone' => '',
            'department' => 'Orbit',
            'role' => User::ROLE_CLIENT,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertRedirect(route('admin.users.clients', [
            'search' => 'no.contact.user',
        ]));

        $createdUser = User::query()->where('username', 'no.contact.user')->firstOrFail();
        $this->assertNull($createdUser->email);
        $this->assertNull($createdUser->phone);

        $directoryResponse = $this->actingAs($superAdmin)->get(route('admin.users.clients', [
            'search' => 'no.contact.user',
        ]));

        $directoryResponse->assertOk();
        $directoryResponse->assertSee('@no.contact.user');
        $directoryResponse->assertSee('No Contact User');
    }

    public function test_super_admin_create_user_requires_password_fields(): void
    {
        $superAdmin = User::create([
            'name' => 'Password Required Admin',
            'email' => 'password-required-admin@example.com',
            'phone' => '09100000032',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'username' => 'missing.password.user',
            'name' => 'Missing Password User',
            'email' => '',
            'phone' => '',
            'department' => 'Orbit',
            'role' => User::ROLE_CLIENT,
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', [
            'username' => 'missing.password.user',
        ]);
    }

    public function test_create_user_form_shows_optional_contact_fields_and_required_password_fields(): void
    {
        $superAdmin = User::create([
            'name' => 'Create Form Admin',
            'email' => 'create-form-admin@example.com',
            'phone' => '09100000033',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertDontSee('Email Address <span class="text-red-500">*</span>', false);
        $response->assertSee('Email Address (Optional)');
        $response->assertDontSee('Phone Number <span class="text-red-500">*</span>', false);
        $response->assertSee('Password <span class="text-red-500">*</span>', false);
        $response->assertSee('Confirm Password <span class="text-red-500">*</span>', false);
        $response->assertDontSee('Leave blank to use the default account password.');
    }

    public function test_super_admin_cannot_create_user_with_reserved_system_email(): void
    {
        $superAdmin = User::create([
            'name' => 'Reserved Email Admin',
            'email' => 'reserved-email-admin@example.com',
            'phone' => '09100000034',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->post(route('admin.users.store'), [
            'name' => 'Reserved Email User',
            'email' => 'reserved-user@system.local',
            'phone' => '09229990000',
            'department' => 'Orbit',
            'role' => User::ROLE_CLIENT,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', [
            'email' => 'reserved-user@system.local',
        ]);
    }

    public function test_super_user_cannot_edit_technical_account(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user-edit@example.com',
            'phone' => '09100000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Technical User',
            'email' => 'technical-edit@example.com',
            'phone' => '09100000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $editResponse = $this->actingAs($superUser)->get(route('admin.users.edit', $technical));
        $editResponse->assertRedirect(route('admin.users.index'));
        $editResponse->assertSessionHas('error', 'You do not have permission to edit this user.');

        $updateResponse = $this->actingAs($superUser)->put(route('admin.users.update', $technical), [
            'name' => 'Technical Updated',
            'email' => 'technical-edit@example.com',
            'phone' => '09100000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect(route('admin.users.index'));
        $updateResponse->assertSessionHas('error', 'You do not have permission to edit this user.');

        $technical->refresh();
        $this->assertSame('Technical User', $technical->name);
    }

    public function test_super_user_cannot_promote_client_to_technical_role(): void
    {
        $superUser = User::create([
            'name' => 'Super User Promotion Guard',
            'email' => 'super-user-promotion-guard@example.com',
            'phone' => '09100000035',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Promotion Guard',
            'email' => 'client-promotion-guard@example.com',
            'phone' => '09100000036',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)
            ->from(route('admin.users.edit', $client))
            ->put(route('admin.users.update', $client), [
                'name' => 'Client Promotion Guard',
                'email' => 'client-promotion-guard@example.com',
                'phone' => '09100000036',
                'department' => 'Hoshishi',
                'role' => User::ROLE_TECHNICAL,
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.users.edit', $client));
        $response->assertSessionHasErrors('role');

        $client->refresh();
        $this->assertSame(User::ROLE_CLIENT, $client->role);
    }

    public function test_super_user_user_index_shows_clients_segment(): void
    {
        $superUser = User::create([
            'name' => 'Super User Segment',
            'email' => 'super-user-segment@example.com',
            'phone' => '09100000004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Client Segment',
            'email' => 'client-segment@example.com',
            'phone' => '09100000005',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('Client Accounts');
        $response->assertDontSee('Staff Accounts');
    }

    public function test_super_user_cannot_change_client_password(): void
    {
        $superUser = User::create([
            'name' => 'Super User Password Guard',
            'email' => 'super-user-password-guard@example.com',
            'phone' => '09100000006',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Password Guard',
            'email' => 'client-password-guard@example.com',
            'phone' => '09100000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('oldpassword123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)
            ->from(route('admin.users.edit', $client))
            ->put(route('admin.users.update', $client), [
                'name' => 'Client Password Guard',
                'email' => 'client-password-guard@example.com',
                'phone' => '09100000007',
                'department' => 'Hoshishi',
                'role' => User::ROLE_CLIENT,
                'is_active' => true,
                'password' => 'Newpassword123',
                'password_confirmation' => 'Newpassword123',
            ]);

        $response->assertRedirect(route('admin.users.edit', $client));
        $response->assertSessionHasErrors(['password', 'password_confirmation']);

        $client->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $client->password));
        $this->assertFalse(Hash::check('Newpassword123', $client->password));
    }

    public function test_super_admin_can_still_change_client_password(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Password',
            'email' => 'super-admin-password@example.com',
            'phone' => '09100000008',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Password Update',
            'email' => 'client-password-update@example.com',
            'phone' => '09100000009',
            'department' => 'Orbit',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('oldpassword123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $client), [
            'name' => 'Client Password Update',
            'email' => 'client-password-update@example.com',
            'phone' => '09100000009',
            'department' => 'Orbit',
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'password' => 'Newpassword123',
            'password_confirmation' => 'Newpassword123',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $client->refresh();
        $this->assertTrue(Hash::check('Newpassword123', $client->password));
    }

    public function test_super_user_edit_client_form_hides_password_fields(): void
    {
        $superUser = User::create([
            'name' => 'Super User Form Check',
            'email' => 'super-user-form-check@example.com',
            'phone' => '09100000010',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Form Check',
            'email' => 'client-form-check@example.com',
            'phone' => '09100000011',
            'department' => 'Cedar',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.users.edit', $client));

        $response->assertOk();
        $response->assertDontSee('Email Address <span class="text-red-500">*</span>', false);
        $response->assertSee('Email Address (Optional)');
        $response->assertDontSee('name="password"', false);
        $response->assertDontSee('name="password_confirmation"', false);
        $response->assertSee('Password changes for client accounts are restricted to admins.', false);
    }

    public function test_super_admin_can_update_client_without_email(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Optional Edit',
            'email' => 'super-admin-optional-edit@example.com',
            'phone' => '091000000115',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Optional Edit',
            'email' => 'client-optional-edit@example.com',
            'phone' => '091000000116',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $client), [
            'name' => 'Client Optional Edit Updated',
            'email' => '',
            'phone' => '091000000116',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $client->refresh();
        $this->assertNull($client->email);
        $this->assertSame('Client Optional Edit Updated', $client->name);
    }

    public function test_edit_client_page_defaults_back_and_cancel_to_clients_segment(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Client Back',
            'email' => 'super-admin-client-back@example.com',
            'phone' => '091000000111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Segment Back',
            'email' => 'client-segment-back@example.com',
            'phone' => '091000000112',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.users.edit', $client));

        $response->assertOk();
        $response->assertSee('name="return_to" value="/admin/users/clients"', false);
        $response->assertSee('href="/admin/users/clients"', false);
    }

    public function test_update_client_from_clients_segment_redirects_back_to_clients_segment(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Client Return',
            'email' => 'super-admin-client-return@example.com',
            'phone' => '091000000113',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Return',
            'email' => 'client-return@example.com',
            'phone' => '091000000114',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $client), [
            'name' => 'Client Return Updated',
            'email' => 'client-return@example.com',
            'phone' => '091000000114',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'return_to' => '/admin/users/clients',
        ]);

        $response->assertRedirect('/admin/users/clients');
    }

    public function test_staff_index_keeps_role_hierarchy_and_sorts_names_alphabetically_within_role_group(): void
    {
        $superAdmin = User::create([
            'name' => 'Zulu Super Admin',
            'email' => 'zulu-super-admin@example.com',
            'phone' => '09100000012',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Bravo Super Admin',
            'email' => 'bravo-super-admin@example.com',
            'phone' => '09100000013',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Alpha Super User',
            'email' => 'alpha-super-user@example.com',
            'phone' => '09100000014',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Beta Legacy Admin',
            'email' => 'beta-legacy-admin@example.com',
            'phone' => '09100000015',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Aaron Technical',
            'email' => 'aaron-technical@example.com',
            'phone' => '09100000016',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.users.index'));
        $response->assertOk();

        $content = $response->getContent();
        $tbodyStart = strpos($content, '<tbody');
        $tbodyEnd = strpos($content, '</tbody>');
        $this->assertNotFalse($tbodyStart);
        $this->assertNotFalse($tbodyEnd);

        $tbodyContent = substr($content, (int) $tbodyStart, ((int) $tbodyEnd - (int) $tbodyStart) + 8);

        $expectedOrder = [
            'Bravo Super Admin',
            'Zulu Super Admin',
            'Alpha Super User',
            'Beta Legacy Admin',
            'Aaron Technical',
        ];

        $previousPosition = -1;
        foreach ($expectedOrder as $expectedName) {
            $position = strpos($tbodyContent, $expectedName);
            $this->assertNotFalse($position, "Missing expected name in users table: {$expectedName}");
            $this->assertGreaterThan($previousPosition, $position, "Unexpected order in users table around: {$expectedName}");
            $previousPosition = $position;
        }
    }

    public function test_admin_user_search_can_find_account_by_username_case_insensitively(): void
    {
        $superAdmin = User::create([
            'name' => 'Search Super Admin',
            'username' => 'search.super.admin',
            'email' => 'search-super-admin@example.com',
            'phone' => '09100000031',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Visible Search Target',
            'username' => 'target.visible.user',
            'email' => 'visible-search-target@example.com',
            'phone' => '09100000032',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Other Search User',
            'username' => 'other.search.user',
            'email' => 'other-search-user@example.com',
            'phone' => '09100000033',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.users.clients', [
            'search' => 'TARGET.VISIBLE.USER',
        ]));

        $response->assertOk();
        $response->assertSee('@target.visible.user');
        $response->assertDontSee('@other.search.user');
    }

    public function test_shadow_user_can_view_managed_password_access_for_non_shadow_user(): void
    {
        $shadow = User::create([
            'name' => 'Shadow Access',
            'email' => 'shadow-password-access@example.com',
            'phone' => '09100000017',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('shadowpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Password Access',
            'email' => 'client-password-access@example.com',
            'phone' => '09100000018',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make(DefaultPasswordResolver::clientFixed()),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.users.show', $client));

        $response->assertOk();
        $response->assertSee('Password Access');
        $response->assertSee('No active temporary password is available for this account.');
        $response->assertSee('Issue Temporary Password');
    }

    public function test_shadow_user_can_issue_temporary_password_handoff(): void
    {
        $shadow = User::create([
            'name' => 'Shadow Reset',
            'email' => 'shadow-password-reset@example.com',
            'phone' => '09100000019',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('shadowpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Reset Target',
            'email' => 'client-reset-target@example.com',
            'phone' => '09100000020',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('custompass123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->post(route('admin.users.password.reset-default', $client));

        $response->assertRedirect(route('admin.users.show', $client));
        $response->assertSessionHas('success', 'Temporary password issued. Reveal it once before it expires.');

        $client->refresh();
        $this->assertFalse(Hash::check('custompass123', $client->password));
        $this->assertDatabaseHas('credential_handoffs', [
            'target_user_id' => $client->id,
            'issued_by_user_id' => $shadow->id,
            'consumed_at' => null,
        ]);
    }

    public function test_shadow_user_can_reveal_temporary_password_once(): void
    {
        $shadow = User::create([
            'name' => 'Shadow Custom Reveal',
            'email' => 'shadow-custom-reveal@example.com',
            'phone' => '09100000023',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('shadowpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Custom Reveal',
            'email' => 'client-custom-reveal@example.com',
            'phone' => '09100000024',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('custompass123'),
            'is_active' => true,
        ]);

        $this->actingAs($shadow)->post(route('admin.users.password.reset-default', $client))
            ->assertRedirect(route('admin.users.show', $client));

        $revealResponse = $this->actingAs($shadow)->post(route('admin.users.password.reveal-temporary', $client));
        $revealResponse->assertRedirect(route('admin.users.show', $client));
        $revealResponse->assertSessionHas('managed_password_reveal');
        $revealedPassword = $revealResponse->getSession()->get('managed_password_reveal');

        $handoff = CredentialHandoff::query()
            ->where('target_user_id', $client->id)
            ->first();

        $this->assertNotNull($handoff);
        $this->assertIsString($revealedPassword);
        $this->assertNotSame($revealedPassword, $handoff->temporary_password);
        $this->assertNotNull($handoff->consumed_at);

        $secondReveal = $this->actingAs($shadow)->post(route('admin.users.password.reveal-temporary', $client));
        $secondReveal->assertRedirect(route('admin.users.show', $client));
        $secondReveal->assertSessionHas('error', 'No active temporary password is available for this account.');
    }

    public function test_non_shadow_user_cannot_reset_managed_user_password(): void
    {
        $admin = User::create([
            'name' => 'Admin Cannot Reset',
            'email' => 'admin-cannot-reset@example.com',
            'phone' => '09100000021',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('adminpass123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Protected',
            'email' => 'client-protected@example.com',
            'phone' => '09100000022',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('custompass123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.password.reset-default', $client));
        $response->assertForbidden();

        $client->refresh();
        $this->assertTrue(Hash::check('custompass123', $client->password));
    }
}
