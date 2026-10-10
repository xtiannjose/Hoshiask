<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NotificationDismissRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_dismiss_unviewed_notification(): void
    {
        config(['legal.require_acceptance' => false]);

        [$client, $admin, $ticket] = $this->seedTicketContext();
        $activityAt = Carbon::now()->subMinute()->startOfSecond();

        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'last_seen_at' => $activityAt->copy()->subSecond(),
        ]);

        $response = $this->actingAs($client)
            ->from(route('client.dashboard'))
            ->post(route('client.notifications.dismiss'), [
                'ticket_id' => $ticket->id,
                'activity_at' => $activityAt->toIso8601String(),
            ]);

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionHas('error');

        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $client->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertNull($state->dismissed_at);
    }

    public function test_client_can_dismiss_viewed_notification(): void
    {
        config(['legal.require_acceptance' => false]);

        [$client, $admin, $ticket] = $this->seedTicketContext();
        $activityAt = Carbon::now()->subMinute()->startOfSecond();

        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'last_seen_at' => $activityAt->copy()->addSecond(),
        ]);

        $response = $this->actingAs($client)
            ->from(route('client.dashboard'))
            ->post(route('client.notifications.dismiss'), [
                'ticket_id' => $ticket->id,
                'activity_at' => $activityAt->toIso8601String(),
            ]);

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionMissing('error');

        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $client->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertNotNull($state->dismissed_at);
        $this->assertTrue($state->dismissed_at->equalTo($activityAt));
    }

    public function test_admin_cannot_dismiss_unviewed_notification(): void
    {
        config(['legal.require_acceptance' => false]);

        [$client, $admin, $ticket] = $this->seedTicketContext();
        $activityAt = Carbon::now()->subMinute()->startOfSecond();

        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'last_seen_at' => $activityAt->copy()->subSecond(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.notifications.dismiss'), [
                'ticket_id' => $ticket->id,
                'activity_at' => $activityAt->toIso8601String(),
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('error');

        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertNull($state->dismissed_at);
    }

    public function test_admin_can_dismiss_viewed_notification(): void
    {
        config(['legal.require_acceptance' => false]);

        [$client, $admin, $ticket] = $this->seedTicketContext();
        $activityAt = Carbon::now()->subMinute()->startOfSecond();

        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'last_seen_at' => $activityAt->copy()->addSecond(),
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.notifications.dismiss'), [
                'ticket_id' => $ticket->id,
                'activity_at' => $activityAt->toIso8601String(),
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionMissing('error');

        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $admin->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertNotNull($state->dismissed_at);
        $this->assertTrue($state->dismissed_at->equalTo($activityAt));
    }

    public function test_client_seen_endpoint_marks_notification_as_dismissed_for_viewed_activity(): void
    {
        config(['legal.require_acceptance' => false]);

        [$client, $admin, $ticket] = $this->seedTicketContext();
        $activityAt = Carbon::now()->subMinute()->startOfSecond();

        $response = $this->actingAs($client)
            ->postJson(route('client.notifications.seen', $ticket), [
                'activity_at' => $activityAt->toIso8601String(),
            ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
        ]);

        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $client->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertNotNull($state->last_seen_at);
        $this->assertNotNull($state->dismissed_at);
        $this->assertTrue($state->dismissed_at->equalTo($state->last_seen_at));
    }

    public function test_client_can_clear_notifications(): void
    {
        config(['legal.require_acceptance' => false]);

        [$client, $admin, $ticket] = $this->seedTicketContext();

        $response = $this->actingAs($client)
            ->from(route('client.dashboard'))
            ->post(route('client.notifications.clear'));

        $response->assertRedirect(route('client.dashboard'));

        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $client->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertNotNull($state->last_seen_at);
        $this->assertNotNull($state->dismissed_at);
    }

    public function test_technician_clear_notifications_only_affects_assigned_open_tickets(): void
    {
        config(['legal.require_acceptance' => false]);

        [$client, $admin, $assignedTicket] = $this->seedTicketContext();
        $assignedTechnical = User::query()->where('email', 'notify-technical@example.test')->firstOrFail();
        $otherTechnical = User::create([
            'name' => 'Other Technical',
            'email' => 'notify-technical-other@example.test',
            'phone' => '09170000103',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $unassignedTicket = Ticket::create([
            'name' => 'Requester Two',
            'contact_number' => '09170000099',
            'email' => 'requester-two@example.test',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Another notification behavior test',
            'description' => 'Used for technician clear scope test.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $otherTechnical->id,
            'category_id' => $assignedTicket->category_id,
        ]);

        $response = $this->actingAs($assignedTechnical)
            ->from(route('admin.dashboard'))
            ->post(route('admin.notifications.clear'));

        $response->assertRedirect(route('admin.dashboard'));

        $assignedState = TicketUserState::query()
            ->where('ticket_id', $assignedTicket->id)
            ->where('user_id', $assignedTechnical->id)
            ->first();
        $this->assertNotNull($assignedState);
        $this->assertNotNull($assignedState->dismissed_at);

        $this->assertDatabaseMissing('ticket_user_states', [
            'ticket_id' => $unassignedTicket->id,
            'user_id' => $assignedTechnical->id,
        ]);
    }

    private function seedTicketContext(): array
    {
        $client = User::create([
            'name' => 'Client User',
            'email' => 'notify-client@example.test',
            'phone' => '09170000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'notify-admin@example.test',
            'phone' => '09170000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Technical User',
            'email' => 'notify-technical@example.test',
            'phone' => '09170000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Notification Tests',
            'description' => 'Notification rule tests',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09170000004',
            'email' => 'requester@example.test',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Notification behavior test',
            'description' => 'Ensures dismiss rules are enforced.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
        ]);

        return [$client, $admin, $ticket];
    }
}
