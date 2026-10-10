<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NoOpUpdateGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_settings_does_not_save_when_nothing_changed(): void
    {
        $superUser = User::create([
            'name' => 'NoOp Super User',
            'email' => 'noop-super-user@example.com',
            'phone' => '09130000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->put(route('account.settings.update'), [
            'name' => 'NoOp Super User',
            'email' => 'noop-super-user@example.com',
            'phone' => '09130000001',
            'department' => 'Hoshishi',
        ]);

        $response->assertRedirect(route('account.settings'));
        $response->assertSessionHas('success', 'No changes were detected.');

        $superUser->refresh();
        $this->assertSame('NoOp Super User', $superUser->name);
    }

    public function test_user_management_update_does_not_save_when_nothing_changed(): void
    {
        $superAdmin = User::create([
            'name' => 'NoOp Super Admin',
            'email' => 'noop-super-admin@example.com',
            'phone' => '09130000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'NoOp Client',
            'email' => 'noop-client@example.com',
            'phone' => '09130000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $client), [
            'name' => 'NoOp Client',
            'email' => 'noop-client@example.com',
            'phone' => '09130000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success', 'No changes were detected.');

        $client->refresh();
        $this->assertSame('NoOp Client', $client->name);
    }

    public function test_user_management_update_with_stay_on_edit_keeps_edit_page_open(): void
    {
        $superAdmin = User::create([
            'name' => 'NoOp Stay Super Admin',
            'email' => 'noop-stay-super-admin@example.com',
            'phone' => '09130000012',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'NoOp Stay Client',
            'email' => 'noop-stay-client@example.com',
            'phone' => '09130000013',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->put(route('admin.users.update', $client), [
            'name' => 'NoOp Stay Client',
            'email' => 'noop-stay-client@example.com',
            'phone' => '09130000013',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'stay_on_edit' => 1,
        ]);

        $response->assertRedirect(route('admin.users.edit', $client));
        $response->assertSessionHas('success', 'No changes were detected.');
    }

    public function test_admin_ticket_status_update_does_not_save_when_status_is_unchanged(): void
    {
        [$staff, $ticket] = $this->seedStaffAndTicket();

        $response = $this->actingAs($staff)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'open',
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket));
        $response->assertSessionHas('success', 'No changes were detected.');

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
    }

    public function test_admin_ticket_quick_update_does_not_save_when_values_are_unchanged(): void
    {
        [$staff, $ticket] = $this->seedStaffAndTicket();

        $response = $this->actingAs($staff)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.quick-update', $ticket), [
                'assigned_to' => '',
                'status' => 'open',
                'priority' => 'medium',
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket));
        $response->assertSessionHas('success', 'No changes were detected.');

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertSame('severity_2', $ticket->priority);
        $this->assertNull($ticket->assigned_to);
    }

    private function seedStaffAndTicket(): array
    {
        $client = User::create([
            'name' => 'NoOp Ticket Client',
            'email' => 'noop-ticket-client@example.com',
            'phone' => '09130000004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $staff = User::create([
            'name' => 'NoOp Ticket Staff',
            'email' => 'noop-ticket-staff@example.com',
            'phone' => '09130000005',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'NoOp Category',
            'description' => 'No-op guard category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'NoOp Requester',
            'contact_number' => '09130000006',
            'email' => 'noop-requester@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'No-op ticket',
            'description' => 'No-op workflow check',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$staff, $ticket];
    }
}
