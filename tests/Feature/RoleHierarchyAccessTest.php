<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleHierarchyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_with_existing_session_is_logged_out_on_protected_route(): void
    {
        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive-user@example.com',
            'phone' => '09120000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get(route('account.settings'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_super_admin_cannot_open_own_edit_page_from_user_management(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super-admin-self-edit@example.com',
            'phone' => '09120000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('admin.users.edit', $superAdmin));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error', 'Use Account Settings to edit your own account.');
    }

    public function test_super_admin_cannot_update_own_account_from_user_management(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin Update',
            'email' => 'super-admin-self-update@example.com',
            'phone' => '09120000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $superAdmin), [
            'name' => 'Attempted Rename',
            'email' => 'super-admin-self-update@example.com',
            'phone' => '09120000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error', 'Use Account Settings to edit your own account.');

        $superAdmin->refresh();
        $this->assertSame(User::ROLE_ADMIN, $superAdmin->role);
        $this->assertSame('Super Admin Update', $superAdmin->name);
    }

    public function test_admin_cannot_view_or_edit_peer_admin_account(): void
    {
        $admin = User::create([
            'name' => 'Primary Admin',
            'email' => 'primary-admin-peer@example.com',
            'phone' => '09120000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $peerAdmin = User::create([
            'name' => 'Peer Admin',
            'email' => 'peer-admin-peer@example.com',
            'phone' => '09120000008',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $showResponse = $this->actingAs($admin)->get(route('admin.users.show', $peerAdmin));
        $showResponse->assertRedirect(route('admin.users.index'));
        $showResponse->assertSessionHas('error', 'You do not have permission to view this user.');

        $editResponse = $this->actingAs($admin)->get(route('admin.users.edit', $peerAdmin));
        $editResponse->assertRedirect(route('admin.users.index'));
        $editResponse->assertSessionHas('error', 'You do not have permission to edit this user.');

        $updateResponse = $this->actingAs($admin)->put(route('admin.users.update', $peerAdmin), [
            'name' => 'Peer Admin Updated',
            'email' => 'peer-admin-peer@example.com',
            'phone' => '09120000008',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $updateResponse->assertRedirect(route('admin.users.index'));
        $updateResponse->assertSessionHas('error', 'You do not have permission to edit this user.');

        $peerAdmin->refresh();
        $this->assertSame('Peer Admin', $peerAdmin->name);
        $this->assertSame(User::ROLE_ADMIN, $peerAdmin->role);
    }

    public function test_admin_index_hides_peer_admin_action_links(): void
    {
        $admin = User::create([
            'name' => 'List Admin',
            'email' => 'list-admin@example.com',
            'phone' => '09120000009',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $peerAdmin = User::create([
            'name' => 'List Peer Admin',
            'email' => 'list-peer-admin@example.com',
            'phone' => '09120000010',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('List Peer Admin');
        $response->assertDontSee(route('admin.users.show', $peerAdmin), false);
        $response->assertDontSee(route('admin.users.edit', $peerAdmin), false);
    }

    public function test_technical_user_keeps_non_destructive_ticket_management_rights(): void
    {
        $technical = User::create([
            'name' => 'Technical One',
            'email' => 'technical-one@example.com',
            'phone' => '09120000004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $secondTechnical = User::create([
            'name' => 'Technical Two',
            'email' => 'technical-two@example.com',
            'phone' => '09120000005',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Ticket Owner',
            'email' => 'client-owner@example.com',
            'phone' => '09120000006',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'description' => 'General category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09125555555',
            'email' => 'requester-role-test@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Role hierarchy test ticket',
            'description' => 'Testing technical rights.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
        ]);

        $statusResponse = $this->actingAs($technical)->post(route('admin.tickets.status', $ticket), [
            'status' => 'in_progress',
        ]);
        $statusResponse->assertRedirect();

        $severityResponse = $this->actingAs($technical)->post(route('admin.tickets.severity', $ticket), [
            'severity' => 'high',
        ]);
        $severityResponse->assertRedirect();

        $assignResponse = $this->actingAs($technical)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $secondTechnical->id,
        ]);
        $assignResponse->assertRedirect();

        $forbiddenAfterHandoff = $this->actingAs($technical)->post(route('admin.tickets.status', $ticket), [
            'status' => 'pending',
        ]);
        $forbiddenAfterHandoff->assertForbidden();

        $ticket->refresh();
        $this->assertSame($secondTechnical->id, $ticket->assigned_to);
        $this->assertSame('in_progress', $ticket->status);
        $this->assertSame('severity_1', $ticket->priority);
    }
}
