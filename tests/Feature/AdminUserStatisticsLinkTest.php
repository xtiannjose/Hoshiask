<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserStatisticsLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_client_profile_statistics_have_clickable_ticket_links(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser(
            'Stats Super User',
            'stats-super-user@example.com',
            User::ROLE_SUPER_USER,
            'Hoshishi'
        );
        $client = $this->createUser(
            'Stats Client',
            'stats-client@example.com',
            User::ROLE_CLIENT,
            'Hoshishi'
        );

        $response = $this->actingAs($superUser)->get(route('admin.users.show', $client));

        $response->assertOk();
        $response->assertSee('User Statistics');
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=all"', false);
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=tickets"', false);
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?account_id='.$client->id.'&amp;tab=history"', false);
        $response->assertDontSee('Assigned Tickets');
    }

    public function test_super_admin_technical_profile_shows_assigned_tickets_link(): void
    {
        config(['legal.require_acceptance' => false]);

        $superAdmin = $this->createUser(
            'Stats Super Admin',
            'stats-super-admin@example.com',
            User::ROLE_ADMIN,
            'Hoshishi'
        );
        $technical = $this->createUser(
            'Stats Technical',
            'stats-technical@example.com',
            User::ROLE_TECHNICAL,
            'Hoshishi'
        );

        $response = $this->actingAs($superAdmin)->get(route('admin.users.show', $technical));

        $response->assertOk();
        $response->assertSee('Assigned Tickets');
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?related_user_id='.$technical->id.'&amp;tab=all"',
            false
        );
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?related_user_id='.$technical->id.'&amp;tab=tickets"',
            false
        );
        $response->assertSee(
            'href="'.e(route('admin.tickets.index')).'?related_user_id='.$technical->id.'&amp;tab=history"',
            false
        );
        $response->assertSee('href="'.e(route('admin.tickets.index')).'?tab=tickets&amp;assigned_to='.$technical->id.'"', false);
    }

    public function test_admin_dashboard_total_ticket_actions_link_to_all_tab(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser(
            'Dashboard Stats User',
            'dashboard-stats-user@example.com',
            User::ROLE_SUPER_USER,
            'Hoshishi'
        );

        $response = $this->actingAs($superUser)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.e(route('admin.tickets.index', ['tab' => 'all'])).'"', false);
    }

    public function test_admin_dashboard_resolved_status_summary_includes_closed_tickets(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser(
            'Dashboard History User',
            'dashboard-history-user@example.com',
            User::ROLE_SUPER_USER,
            'Hoshishi'
        );
        $client = $this->createUser(
            'Dashboard History Client',
            'dashboard-history-client@example.com',
            User::ROLE_CLIENT,
            'Hoshishi'
        );
        $category = Category::create([
            'name' => 'Dashboard History Category',
            'description' => 'Dashboard history category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        Ticket::create([
            'name' => 'Resolved Requester',
            'contact_number' => '09170000010',
            'email' => 'resolved-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Resolved dashboard ticket',
            'description' => 'Resolved dashboard ticket.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now()->subDay(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Closed Requester',
            'contact_number' => '09170000011',
            'email' => 'closed-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed dashboard ticket',
            'description' => 'Closed dashboard ticket.',
            'priority' => 'medium',
            'status' => 'closed',
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('href="'.e(route('admin.tickets.index', ['tab' => 'history'])).'"', false);
        $response->assertSee('href="'.e(route('admin.tickets.index', ['tab' => 'history', 'status' => 'closed'])).'"', false);
        $response->assertSeeInOrder(['Resolved', '2']);
        $response->assertSeeInOrder(['Closed', '1']);
    }

    public function test_user_statistics_total_reconciles_with_open_closed_and_cancelled(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Cancelled Stats Super User', 'cancelled-stats-super@example.com', User::ROLE_SUPER_USER, 'Hoshishi');
        $client = $this->createUser('Cancelled Stats Client', 'cancelled-stats-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = Category::create([
            'name' => 'Cancelled Stats Category',
            'description' => 'Cancelled stats category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        Ticket::create([
            'name' => 'Open Requester',
            'contact_number' => '09170000020',
            'email' => 'open-stats-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Open ticket',
            'description' => 'Open ticket.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Cancelled Requester',
            'contact_number' => '09170000021',
            'email' => 'cancelled-stats-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Cancelled ticket',
            'description' => 'Cancelled ticket.',
            'priority' => 'medium',
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.users.show', $client));

        $response->assertOk();
        $response->assertSee('Cancelled Tickets');
        $response->assertSeeInOrder(['Total Tickets', '2']);
        $response->assertSeeInOrder(['Open Tickets', '1']);
        $response->assertSeeInOrder(['Cancelled Tickets', '1']);
        $response->assertSee(
            'href="'.e(route('admin.tickets.index', ['account_id' => $client->id, 'tab' => 'all'])).'"',
            false
        );
    }

    public function test_admin_dashboard_tickets_by_status_widget_includes_cancelled(): void
    {
        config(['legal.require_acceptance' => false]);

        $superUser = $this->createUser('Dashboard Cancelled User', 'dashboard-cancelled-user@example.com', User::ROLE_SUPER_USER, 'Hoshishi');
        $client = $this->createUser('Dashboard Cancelled Client', 'dashboard-cancelled-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = Category::create([
            'name' => 'Dashboard Cancelled Category',
            'description' => 'Dashboard cancelled category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        Ticket::create([
            'name' => 'Cancelled Dashboard Requester',
            'contact_number' => '09170000030',
            'email' => 'cancelled-dashboard-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Cancelled dashboard ticket',
            'description' => 'Cancelled dashboard ticket.',
            'priority' => 'medium',
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(
            'href="'.e(route('admin.tickets.index', ['tab' => 'history', 'status' => 'cancelled'])).'"',
            false
        );
        $response->assertSeeInOrder(['Cancelled', '1']);
    }

    private function createUser(string $name, string $email, string $role, string $department): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09179990000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}
