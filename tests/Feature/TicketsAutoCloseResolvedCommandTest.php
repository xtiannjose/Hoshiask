<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketsAutoCloseResolvedCommandTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_it_closes_resolved_tickets_inactive_for_seven_or_more_days(): void
    {
        $technician = $this->createAssignedSupportUser('Auto Close Technician', 'auto-close-technician@example.com');
        $category = $this->createCategory();
        $client = $this->createClient('Auto Close Client', 'auto-close-client@example.com');

        $staleResolvedTicket = Ticket::create([
            'name' => 'Stale Resolved Requester',
            'contact_number' => '09110000201',
            'email' => 'stale-resolved-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Resolved a week ago',
            'description' => 'Client never confirmed or reopened it.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now()->subDays(8),
            // Already warned: this test is about the close itself (warnings have their own tests).
            'client_notified_auto_close_warning_at' => now()->subDays(2),
            'assigned_to' => $technician->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        $staleResolvedTicket->assignedUsers()->sync([$technician->id]);

        $recentlyResolvedTicket = Ticket::create([
            'name' => 'Recently Resolved Requester',
            'contact_number' => '09110000202',
            'email' => 'recently-resolved-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Resolved yesterday',
            'description' => 'Still inside the grace period.',
            'priority' => 'medium',
            'status' => 'resolved',
            'resolved_at' => now()->subDay(),
            'assigned_to' => $technician->id,
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        $recentlyResolvedTicket->assignedUsers()->sync([$technician->id]);

        $openTicket = Ticket::create([
            'name' => 'Still Open Requester',
            'contact_number' => '09110000203',
            'email' => 'still-open-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Never resolved',
            'description' => 'Should be untouched by the auto-close job.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
        Ticket::query()->whereKey($openTicket->id)->update(['created_at' => now()->subDays(30)]);

        $this->artisan('tickets:auto-close-resolved')
            ->expectsOutputToContain('Auto-closed 1 resolved ticket(s)')
            ->assertSuccessful();

        $this->assertSame('closed', $staleResolvedTicket->fresh()->status);
        $this->assertNotNull($staleResolvedTicket->fresh()->closed_at);
        $this->assertSame('resolved', $recentlyResolvedTicket->fresh()->status);
        $this->assertSame('open', $openTicket->fresh()->status);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $staleResolvedTicket->id,
            'is_internal' => true,
        ]);
    }
}
