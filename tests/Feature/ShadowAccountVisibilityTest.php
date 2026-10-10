<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ShadowAccountVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_assign_ticket_to_shadow_account(): void
    {
        config(['legal.require_acceptance' => false]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'assign-admin@example.com',
            'phone' => '09180001100',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $shadow = User::create([
            'name' => 'Shadow User',
            'email' => 'assign-shadow@example.com',
            'phone' => '09180001101',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client User',
            'email' => 'assign-client@example.com',
            'phone' => '09180001102',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Visibility Category',
            'description' => 'Visibility test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09180001103',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assignment target validation',
            'description' => 'Cannot assign this to shadow.',
            'priority' => 'high',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $shadow->id,
        ]);

        $response->assertSessionHasErrors('assigned_to');
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'assigned_to' => null,
        ]);
    }

    public function test_non_shadow_staff_ticket_view_hides_shadow_replies(): void
    {
        config(['legal.require_acceptance' => false]);

        $admin = $this->createUser('Admin Viewer', 'shadow-view-admin@example.com', User::ROLE_ADMIN, 'Hoshishi');
        $shadow = $this->createUser('Shadow Viewer', 'shadow-view-shadow@example.com', User::ROLE_SHADOW, 'Hoshishi');
        $client = $this->createUser('Client Viewer', 'shadow-view-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory('Shadow Reply Category');

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09180001120',
            'email' => 'requester-shadow-view@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Shadow ticket trace visibility',
            'description' => 'Original description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow-only action trace',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client-visible reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Client-visible reply');
        $response->assertDontSee('Shadow-only action trace');
    }

    public function test_shadow_ticket_view_still_sees_shadow_replies(): void
    {
        config(['legal.require_acceptance' => false]);

        $shadow = $this->createUser('Shadow Viewer', 'shadow-viewer@example.com', User::ROLE_SHADOW, 'Hoshishi');
        $client = $this->createUser('Client Viewer', 'shadow-viewer-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory('Shadow Visible Category');

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09180001121',
            'email' => 'requester-shadow-visible@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Shadow ticket visibility',
            'description' => 'Original description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow-only action trace',
            'is_internal' => true,
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Shadow-only action trace');
    }

    public function test_shadow_assignments_do_not_render_in_assignee_label(): void
    {
        config(['legal.require_acceptance' => false]);

        $shadow = $this->createUser('Shadow Assignee', 'shadow-assignee@example.com', User::ROLE_SHADOW, 'Hoshishi');
        $client = $this->createUser('Client Assignee', 'client-assignee@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory('Shadow Assignment Category');

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09180001122',
            'email' => 'requester-shadow-assignment@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Shadow assignment trace',
            'description' => 'Original description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
            'assigned_to' => $shadow->id,
        ]);

        $ticket->assignedUsers()->sync([$shadow->id]);
        $ticket->load(['assignedUser', 'assignedUsers']);

        $this->assertSame('Unassigned', $ticket->assigned_users_label);
    }

    private function createUser(string $name, string $email, string $role, string $department): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09180001100',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name): Category
    {
        return Category::create([
            'name' => $name,
            'description' => $name.' description',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }
}
