<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardRolePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_dashboard_hides_needs_attention_quick_action(): void
    {
        $technician = User::create([
            'name' => 'Dashboard Technician',
            'email' => 'dashboard-technician@example.com',
            'phone' => '09117778888',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($technician)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('class="btn-danger">Currently Open Tickets</a>', false);
        $response->assertDontSee('class="btn-warning">Needs Attention</a>', false);
    }

    public function test_technician_dashboard_stats_include_own_internal_staff_requests(): void
    {
        $technician = User::create([
            'name' => 'Dashboard Technician Stats',
            'email' => 'dashboard-technician-stats@example.com',
            'phone' => '09117778889',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $assignedTechnical = User::create([
            'name' => 'Assigned Dashboard Technical',
            'email' => 'assigned-dashboard-technical@example.com',
            'phone' => '09117778890',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $client = User::create([
            'name' => 'Dashboard Client',
            'email' => 'dashboard-client@example.com',
            'phone' => '09117778891',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Dashboard Category',
            'description' => 'Dashboard test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        Ticket::create([
            'name' => 'Assigned Requester',
            'contact_number' => '09117770001',
            'email' => 'assigned-dashboard-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assigned client ticket',
            'description' => 'Assigned client ticket for dashboard stats.',
            'priority' => 'severity_1',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technician->id,
            'category_id' => $category->id,
        ]);

        Ticket::create([
            'name' => 'Internal Requester',
            'contact_number' => '09117770002',
            'email' => 'internal-dashboard-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Open internal requester ticket',
            'description' => 'Should count in dashboard stats.',
            'priority' => 'severity_1',
            'status' => 'open',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'user_id' => $technician->id,
            'assigned_to' => $assignedTechnical->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($technician)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats): bool {
            return (int) ($stats['total_tickets'] ?? 0) === 2
                && (int) ($stats['open_tickets'] ?? 0) === 2
                && (int) ($stats['severity_one_tickets'] ?? 0) === 2;
        });
    }

    public function test_dashboard_shows_top_issues_and_a_zero_filled_trend(): void
    {
        $admin = User::create([
            'name' => 'Dashboard Admin',
            'email' => 'dashboard-admin-top-issues@example.com',
            'phone' => '09117778892',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $client = User::create([
            'name' => 'Top Issues Client',
            'email' => 'top-issues-client@example.com',
            'phone' => '09117778893',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Printer Issues',
            'description' => 'Printer category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        foreach (range(1, 3) as $i) {
            Ticket::create([
                'name' => 'Requester '.$i,
                'contact_number' => '0911777889'.$i,
                'email' => "top-issue-{$i}@example.com",
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Printer not working '.$i,
                'description' => 'Recurring printer issue.',
                'priority' => 'severity_2',
                'status' => 'open',
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('topIssues', function ($topIssues) use ($category) {
            $first = $topIssues->first();

            return $first !== null
                && $first->category_name === $category->name
                && (int) $first->ticket_count === 3;
        });
        $response->assertViewHas('ticketsTrend', function (array $trend) {
            return count($trend) === 30
                && (int) collect($trend)->sum('count') === 3;
        });
        $response->assertSee('Printer Issues');
        $response->assertSee('Top Issues');
        $response->assertSee('Ticket Volume (Last 30 Days)');
    }
}
