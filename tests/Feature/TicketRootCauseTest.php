<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketRootCauseTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_admin_can_record_root_cause_when_resolving_a_ticket(): void
    {
        $admin = $this->createUser('Admin User', 'admin-rca@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'resolved',
                'root_cause_category' => 'network',
                'root_cause_notes' => 'Faulty switch at site B',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('network', $ticket->root_cause_category);
        $this->assertSame('Faulty switch at site B', $ticket->root_cause_notes);
        $this->assertSame('Network', $ticket->root_cause_category_label);
    }

    public function test_root_cause_is_optional_when_resolving(): void
    {
        $admin = $this->createUser('Admin User', 'admin-rca2@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->root_cause_category);
    }

    public function test_invalid_root_cause_category_is_rejected(): void
    {
        $admin = $this->createUser('Admin User', 'admin-rca3@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($admin)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'resolved',
                'root_cause_category' => 'not-a-real-category',
            ])
            ->assertSessionHasErrors('root_cause_category');
    }
}
