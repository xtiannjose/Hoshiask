<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketPerUserStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_badge_is_tracked_per_user(): void
    {
        [$superUser, $technical, $ticket] = $this->seedUsersAndTicket();

        $initialSuperUserIndex = $this->actingAs($superUser)->get(route('admin.tickets.index'));
        $initialSuperUserIndex->assertOk();
        $initialSuperUserIndex->assertSee('data-ticket-new-badge="1"', false);

        $this->actingAs($superUser)->get(route('admin.tickets.show', $ticket))->assertOk();

        $seenSuperUserIndex = $this->actingAs($superUser)->get(route('admin.tickets.index'));
        $seenSuperUserIndex->assertOk();
        $seenSuperUserIndex->assertDontSee('data-ticket-new-badge="1"', false);

        $technicalIndex = $this->actingAs($technical)->get(route('admin.tickets.index'));
        $technicalIndex->assertOk();
        $technicalIndex->assertSee('data-ticket-new-badge="1"', false);
    }

    private function seedUsersAndTicket(): array
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'new-badge-super-user@example.com',
            'phone' => '09170000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Technical User',
            'email' => 'new-badge-technical@example.com',
            'phone' => '09170000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client User',
            'email' => 'new-badge-client@example.com',
            'phone' => '09170000003',
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
            'contact_number' => '09170000004',
            'email' => 'requester@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Per-user badge test',
            'description' => 'Testing per-user new badge state.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
        ]);

        return [$superUser, $technical, $ticket];
    }
}
