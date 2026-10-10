<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketAcknowledgmentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_can_acknowledge_ticket_without_changing_dismiss_state(): void
    {
        $superUser = $this->createUser('Ack Super', 'ack-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Ack Client', 'ack-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.acknowledge', $ticket), [
            'return_to' => route('admin.tickets.show', $ticket, absolute: false),
        ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket, absolute: false));

        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $superUser->id)
            ->first();

        $this->assertNotNull($state);
        $this->assertNotNull($state->last_seen_at);
        $this->assertNotNull($state->acknowledged_at);
        $this->assertNull($state->dismissed_at);
    }

    public function test_seen_ticket_without_acknowledgment_does_not_trigger_email_alerts(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Seen Super', 'seen-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Seen Client', 'seen-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $ticket->forceFill([
            'created_at' => now()->subMinutes(51),
            'updated_at' => now()->subMinutes(51),
        ])->save();

        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'last_seen_at' => now()->subMinutes(45),
            'dismissed_at' => now()->subMinutes(45),
        ]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    private function createUser(string $name, string $email, string $role, string $department = 'Hoshishi'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09170000000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Acknowledgment Category',
            'description' => 'Acknowledgment flow tests',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }

    private function createTicket(User $client, Category $category): Ticket
    {
        return Ticket::create([
            'name' => 'Ticket Requester',
            'contact_number' => '09185551234',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Ticket acknowledgment subject',
            'description' => 'Ticket acknowledgment body',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
    }
}
