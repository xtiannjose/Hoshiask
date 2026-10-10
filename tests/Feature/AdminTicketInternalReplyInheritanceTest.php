<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AdminTicketInternalReplyInheritanceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_replying_to_internal_message_is_forced_internal(): void
    {
        [$client, $staff, $ticket] = $this->seedTicketWithUsers();

        $internalParent = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'message' => 'Internal parent message',
            'is_internal' => true,
        ]);

        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), [
            'message' => 'Reply to internal parent',
            'reply_to_id' => $internalParent->id,
            'is_internal' => false,
        ])->assertStatus(302);

        $reply = TicketReply::where('ticket_id', $ticket->id)
            ->where('message', 'Reply to internal parent')
            ->firstOrFail();

        $this->assertTrue((bool) $reply->is_internal);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
    }

    public function test_replying_to_public_message_remains_public_by_default(): void
    {
        [$client, $staff, $ticket] = $this->seedTicketWithUsers();

        $publicParent = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Public parent message',
            'is_internal' => false,
        ]);

        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), [
            'message' => 'Reply to public parent',
            'reply_to_id' => $publicParent->id,
            'is_internal' => false,
        ])->assertStatus(302);

        $reply = TicketReply::where('ticket_id', $ticket->id)
            ->where('message', 'Reply to public parent')
            ->firstOrFail();

        $this->assertFalse((bool) $reply->is_internal);

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
    }

    private function seedTicketWithUsers(): array
    {
        $client = User::create([
            'name' => 'Client User',
            'email' => 'client-internal-reply@example.com',
            'phone' => '09123450000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $staff = User::create([
            'name' => 'Super User',
            'email' => 'super-user-internal-reply@example.com',
            'phone' => '09123451111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'description' => 'General concerns',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester Name',
            'contact_number' => '09120000000',
            'email' => 'requester-internal-reply@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'subject' => 'Reply inheritance ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$client, $staff, $ticket];
    }

    public function test_a_public_staff_reply_moves_an_open_ticket_to_in_progress_through_the_status_workflow(): void
    {
        $staff = $this->createSupportUser('Super Reply', 'super-reply-starts-work@example.com');
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'open']);

        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), [
            'message' => 'Checking this now',
            'is_internal' => false,
        ])->assertStatus(302);

        $this->assertSame('in_progress', $ticket->fresh()->status);
        $entry = SystemLog::where('event_type', 'ticket.status.updated')->where('target_id', $ticket->id)->firstOrFail();
        $this->assertSame('open', $entry->metadata['previous_status']);
        $this->assertSame('in_progress', $entry->metadata['new_status']);
    }

    public function test_a_staff_reply_does_not_undo_a_cancel_saved_a_moment_earlier(): void
    {
        $staff = $this->createSupportUser('Super Reply Stale', 'super-reply-stale@example.com');
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'open']);
        $staleCopy = Ticket::findOrFail($ticket->id);
        $this->app['router']->bind('ticket', fn () => clone $staleCopy);
        $ticket->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Withdrawn']);

        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), [
            'message' => 'Checking this now',
            'is_internal' => true,
        ]);
        $this->actingAs($staff)->post(route('admin.tickets.reply', $ticket), [
            'message' => 'Public follow-up',
            'is_internal' => false,
        ]);

        $this->assertSame('cancelled', $ticket->fresh()->status);
        $this->assertSame(2, TicketReply::where('ticket_id', $ticket->id)->count());
    }
}
