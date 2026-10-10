<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketLifecycleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_reopening_ticket_clears_resolution_and_closure_timestamps(): void
    {
        [$superUser, , $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subHour(),
            'closed_at' => Carbon::now()->subHour(),
            'satisfaction_rating' => 4,
            'satisfaction_comment' => 'Old feedback',
            'technical_user_notified_sla_at' => Carbon::now()->subMinutes(10),
        ]);
        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'last_seen_at' => Carbon::now()->subMinutes(10),
            'acknowledged_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'open',
            ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
        $this->assertNull($ticket->satisfaction_rating);
        $this->assertNull($ticket->satisfaction_comment);
        $this->assertNull($ticket->technical_user_notified_sla_at);
        $this->assertNull(
            TicketUserState::query()
                ->where('ticket_id', $ticket->id)
                ->where('user_id', $superUser->id)
                ->value('acknowledged_at')
        );
    }

    public function test_client_reply_reopens_a_resolved_ticket_and_clears_timestamps(): void
    {
        [$superUser, $client, $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now()->subHour(),
            'closed_at' => Carbon::now()->subMinutes(30),
            'closed_by' => $superUser->id,
            'satisfaction_rating' => 5,
            'satisfaction_comment' => 'Initial resolution feedback.',
            'technical_user_notified_sla_at' => Carbon::now()->subMinutes(15),
        ]);
        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'last_seen_at' => Carbon::now()->subMinutes(15),
            'acknowledged_at' => Carbon::now()->subMinutes(15),
        ]);

        $response = $this->actingAs($client)
            ->post(route('client.tickets.reply', $ticket), [
                'message' => 'Issue returned after initial fix.',
            ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
        $this->assertNull($ticket->closed_by);
        $this->assertNull($ticket->satisfaction_rating);
        $this->assertNull($ticket->satisfaction_comment);
        $this->assertNull($ticket->technical_user_notified_sla_at);
        $this->assertNull(
            TicketUserState::query()
                ->where('ticket_id', $ticket->id)
                ->where('user_id', $superUser->id)
                ->value('acknowledged_at')
        );
    }

    public function test_client_reply_is_rejected_on_a_closed_ticket(): void
    {
        [$superUser, $client, $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subHour(),
            'closed_at' => Carbon::now()->subMinutes(30),
            'closed_by' => $superUser->id,
        ]);

        $response = $this->actingAs($client)
            ->post(route('client.tickets.reply', $ticket), [
                'message' => 'Trying to reply after closure.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertSame($superUser->id, $ticket->closed_by);
        $this->assertDatabaseMissing('ticket_replies', [
            'ticket_id' => $ticket->id,
            'message' => 'Trying to reply after closure.',
        ]);
    }

    public function test_client_cannot_resolve_ticket_without_required_rating(): void
    {
        [, $client, $ticket] = $this->seedUsersAndTicket();

        $response = $this->from(route('client.tickets.show', $ticket))
            ->actingAs($client)
            ->post(route('client.tickets.resolve', $ticket), [
                'resolve_confirmation' => '1',
                'comment' => 'Everything worked well.',
            ]);

        $response->assertRedirect(route('client.tickets.show', $ticket));
        $response->assertSessionHasErrors(['rating']);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->satisfaction_rating);
        $this->assertSame(0, TicketReply::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_client_cannot_resolve_ticket_with_a_low_rating_and_no_comment(): void
    {
        [, $client, $ticket] = $this->seedUsersAndTicket();

        $response = $this->from(route('client.tickets.show', $ticket))
            ->actingAs($client)
            ->post(route('client.tickets.resolve', $ticket), [
                'resolve_confirmation' => '1',
                'rating' => '2',
            ]);

        $response->assertRedirect(route('client.tickets.show', $ticket));
        $response->assertSessionHasErrors(['comment']);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->satisfaction_comment);
        $this->assertSame(0, TicketReply::query()->where('ticket_id', $ticket->id)->count());
    }

    public function test_client_resolve_requires_confirmation_and_submits_rating(): void
    {
        [, $client, $ticket] = $this->seedUsersAndTicket();

        $response = $this->actingAs($client)
            ->post(route('client.tickets.resolve', $ticket), [
                'resolve_confirmation' => '1',
                'rating' => '5',
                'comment' => 'Fast and clear support.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame(5, $ticket->satisfaction_rating);
        $this->assertSame('Fast and clear support.', $ticket->satisfaction_comment);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client marked this ticket as resolved.',
            'is_internal' => false,
        ]);
    }

    public function test_client_resolve_clears_a_stale_closed_by_value(): void
    {
        [$superUser, $client, $ticket] = $this->seedUsersAndTicket();

        // Simulate a ticket that ended up with a stale closed_by despite no longer
        // being closed (defensive case — normal reopen paths already null this).
        $ticket->update([
            'status' => 'in_progress',
            'closed_by' => $superUser->id,
        ]);

        $response = $this->actingAs($client)
            ->post(route('client.tickets.resolve', $ticket), [
                'resolve_confirmation' => '1',
                'rating' => '5',
                'comment' => 'All good now.',
            ]);

        $response->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNull($ticket->closed_by);
    }

    public function test_client_cannot_submit_standalone_low_rating_without_a_comment(): void
    {
        [, $client, $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => Carbon::now()->subMinutes(15),
        ]);

        $response = $this->from(route('client.tickets.show', $ticket))
            ->actingAs($client)
            ->post(route('client.tickets.rate', $ticket), [
                'rating' => '1',
            ]);

        $response->assertRedirect(route('client.tickets.show', $ticket));
        $response->assertSessionHasErrors(['comment' => 'You gave 1 or 2 stars. Please tell us what went wrong so we can put it right.']);

        $ticket->refresh();
        $this->assertNull($ticket->satisfaction_rating);
        $this->assertNull($ticket->satisfaction_comment);
    }

    public function test_client_cannot_submit_standalone_rating_for_non_resolved_ticket(): void
    {
        [, $client, $ticket] = $this->seedUsersAndTicket();

        $response = $this->from(route('client.tickets.show', $ticket))
            ->actingAs($client)
            ->post(route('client.tickets.rate', $ticket), [
                'rating' => '4',
                'comment' => 'This should not be accepted while still open.',
            ]);

        $response->assertRedirect(route('client.tickets.show', $ticket));
        $response->assertSessionHas('error', 'Only resolved tickets awaiting feedback can be rated.');

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->satisfaction_rating);
        $this->assertNull($ticket->satisfaction_comment);
    }

    public function test_resolving_unassigned_ticket_auto_assigns_reviewing_super_user(): void
    {
        [$superUser, , $ticket] = $this->seedUsersAndTicket();

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'resolved',
            ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertSame($superUser->id, (int) $ticket->assigned_to);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_admin_can_revert_closed_ticket_to_in_progress_and_clear_timestamps(): void
    {
        [$superUser, , $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subHour(),
            'closed_at' => Carbon::now()->subMinutes(45),
            'assigned_to' => null,
        ]);

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'in_progress',
            ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
    }

    public function test_admin_cannot_revert_closed_ticket_after_seven_days(): void
    {
        [$superUser, , $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subDays(8),
            'closed_at' => Carbon::now()->subDays(8),
        ]);

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'in_progress',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_admin_quick_update_cannot_revert_closed_ticket_after_seven_days(): void
    {
        [$superUser, , $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subDays(8),
            'closed_at' => Carbon::now()->subDays(8),
            'priority' => 'medium',
        ]);

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.quick-update', $ticket), [
                'assigned_to' => '',
                'status' => 'in_progress',
                'priority' => 'medium',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_admin_bulk_status_update_cannot_revert_closed_ticket_after_seven_days(): void
    {
        [$superUser, , $ticket] = $this->seedUsersAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subDays(8),
            'closed_at' => Carbon::now()->subDays(8),
        ]);

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'status',
                'selected_ids' => [$ticket->id],
                'status' => 'in_progress',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_closed_ticket_recognition_includes_all_assigned_technicians(): void
    {
        [, $client, $ticket] = $this->seedUsersAndTicket();

        $primaryTechnical = User::create([
            'name' => 'Primary Closing Tech',
            'email' => 'primary-closing-tech@example.com',
            'phone' => '09119990004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $secondaryTechnical = User::create([
            'name' => 'Secondary Closing Tech',
            'email' => 'secondary-closing-tech@example.com',
            'phone' => '09119990005',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // One reading of the clock: three separate Carbon::now() calls differ by a few microseconds, and a database that
        // rounds timestamps (PostgreSQL) could turn the exact 25 hours below into 24 hours 59 minutes.
        $now = Carbon::now()->startOfSecond();
        $ticket->update([
            'status' => 'resolved',
            'assigned_to' => $primaryTechnical->id,
            'assigned_at' => $now->copy()->subHours(49)->subMinutes(30),
            'resolved_at' => $now->copy()->subHours(25),
            'closed_at' => null,
            'closed_by' => null,
        ]);
        Ticket::query()->whereKey($ticket->id)->update([
            'created_at' => $now->copy()->subHours(50),
        ]);
        $ticket->refresh();
        $ticket->assignedUsers()->sync([$primaryTechnical->id, $secondaryTechnical->id]);

        $response = $this->actingAs($secondaryTechnical)
            ->post(route('admin.tickets.status', $ticket), [
                'status' => 'closed',
                'close_reason' => 'Work completed and verified.',
            ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame('closed', $ticket->status);
        $this->assertSame($secondaryTechnical->id, (int) $ticket->closed_by);
        $this->assertNotNull($ticket->closed_at);

        $showResponse = $this->actingAs($secondaryTechnical)
            ->get(route('admin.tickets.show', $ticket));

        $showResponse->assertOk();
        $showResponse->assertDontSee('Recognized Technicians');
        $showResponse->assertSee($primaryTechnical->publicDisplayName());
        $showResponse->assertSee($secondaryTechnical->publicDisplayName());
        $showResponse->assertSee('Resolution Time');
        $showResponse->assertSee('1d 1h');
    }

    private function seedUsersAndTicket(): array
    {
        $superUser = User::create([
            'name' => 'Super User Lifecycle',
            'email' => 'super-user-lifecycle@example.com',
            'phone' => '09119990001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Lifecycle',
            'email' => 'client-lifecycle@example.com',
            'phone' => '09119990002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Lifecycle',
            'description' => 'Lifecycle checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Lifecycle Requester',
            'contact_number' => '09119990003',
            'email' => 'lifecycle-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Makati',
            'subject' => 'Lifecycle consistency',
            'description' => 'Ticket lifecycle behavior.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$superUser, $client, $ticket];
    }
}
