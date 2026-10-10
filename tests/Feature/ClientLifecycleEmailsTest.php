<?php

namespace Tests\Feature;

use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\TicketEmailAlertService;
use App\Support\TicketEmailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ClientLifecycleEmailsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 3, 10, 9, 0, 0));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function queuedTo(string $email): Collection
    {
        return Mail::queued(TicketAlertMail::class, fn (TicketAlertMail $mail) => $mail->hasTo($email));
    }

    private ?User $admin = null;

    private function admin(): User
    {
        return $this->admin ??= $this->createUser('Admin User', 'admin-client-mail@example.com', User::ROLE_ADMIN);
    }

    // ---- ticket received -------------------------------------------------

    public function test_client_gets_one_received_email_with_the_ticket_number_when_they_file_a_ticket(): void
    {
        $client = $this->createClient('Receipt Client', 'receipt-client@example.com');

        $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Receipt Client',
            'contact_number' => '09181234567',
            'email' => 'receipt-client@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Printer is jammed',
            'description' => 'Paper is stuck.',
            'category_id' => $this->createCategory()->id,
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Printer is jammed')->firstOrFail();
        $mails = $this->queuedTo('receipt-client@example.com');

        $this->assertCount(1, $mails);
        $mail = $mails->first();
        $this->assertSame('We received your ticket.', $mail->headline);
        $this->assertSame('['.$ticket->ticket_number.'] Printer is jammed', $mail->subjectLine);
        $this->assertTrue($mail->clientThread);
        $this->assertTrue($mail->clientThreadRoot);
        $this->assertSame(route('client.tickets.show', $ticket), $mail->actionUrl);
        $this->assertNotNull($ticket->fresh()->client_notified_received_at);
    }

    public function test_received_email_is_sent_only_once_per_ticket(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());
        $alerts = app(TicketEmailAlertService::class);

        $this->assertTrue($alerts->notifyClientOfTicketReceived($ticket));
        $this->assertFalse($alerts->notifyClientOfTicketReceived($ticket->fresh()));

        $this->assertCount(1, $this->queuedTo($client->email));
    }

    public function test_received_email_can_be_switched_off(): void
    {
        config(['helpdesk.client_emails.received' => false]);
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->assertFalse(app(TicketEmailAlertService::class)->notifyClientOfTicketReceived($ticket));
        Mail::assertNothingQueued();
    }

    public function test_no_received_email_for_a_past_ticket_logged_after_the_fact(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $alerts = app(TicketEmailAlertService::class);

        foreach (['resolved', 'closed', 'cancelled'] as $status) {
            $this->assertFalse($alerts->notifyClientOfTicketReceived($this->createTicket($client, $category, ['status' => $status])));
        }

        $backdated = $this->createTicket($client, $category);
        Ticket::query()->whereKey($backdated->id)->update(['created_at' => now()->subHours(25)]);
        $this->assertFalse($alerts->notifyClientOfTicketReceived($backdated->fresh()));

        Mail::assertNothingQueued();
    }

    public function test_a_ticket_logged_earlier_today_still_gets_a_received_email(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory());
        Ticket::query()->whereKey($ticket->id)->update(['created_at' => now()->subHours(3)]);

        $this->assertTrue(app(TicketEmailAlertService::class)->notifyClientOfTicketReceived($ticket->fresh()));
        $this->assertCount(1, $this->queuedTo($client->email));
    }

    public function test_staff_entering_an_old_closed_ticket_for_a_client_does_not_email_a_receipt(): void
    {
        $client = $this->createClient('Backlog Client', 'backlog-client@example.com');
        $payload = [
            'user_id' => $client->id,
            'name' => $client->name,
            'contact_number' => '09170000000',
            'email' => $client->email,
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Handled on paper in January',
            'description' => 'Entered after being handled on paper.',
            'category_id' => $this->createCategory()->id,
            'ticket_type' => 'external',
        ];

        $this->actingAs($this->admin())->post(route('admin.tickets.store'), $payload + [
            'created_at' => now()->subMonths(2)->format('Y-m-d\TH:i'),
            'closed_at' => now()->subMonths(2)->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Handled on paper in January')->firstOrFail();
        $this->assertSame('closed', $ticket->status);
        $this->assertNull($ticket->client_notified_received_at);
        $this->assertCount(0, $this->queuedTo('backlog-client@example.com'));

        // The same form used for a ticket happening now still sends the receipt.
        $this->actingAs($this->admin())->post(route('admin.tickets.store'), ['subject' => 'Logged by phone just now'] + $payload)
            ->assertRedirect();

        $this->assertCount(1, $this->queuedTo('backlog-client@example.com'));
    }

    public function test_no_received_email_for_a_client_without_an_email_address_or_who_is_inactive(): void
    {
        $alerts = app(TicketEmailAlertService::class);

        $inactive = $this->createClient('Inactive', 'inactive-client@example.com', ['is_active' => false]);
        $this->assertFalse($alerts->notifyClientOfTicketReceived($this->createTicket($inactive, $this->createCategory())));

        $noEmail = $this->createClient('No Email', 'no-email-client@example.com');
        User::query()->whereKey($noEmail->id)->update(['email' => '']);
        $this->assertFalse($alerts->notifyClientOfTicketReceived($this->createTicket($noEmail->fresh(), $this->createCategory())));

        Mail::assertNothingQueued();
    }

    // ---- resolved / closed -----------------------------------------------

    public function test_client_is_emailed_once_when_staff_resolve_the_ticket(): void
    {
        $client = $this->createClient('Resolve Client', 'resolve-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($this->admin())
            ->post(route('admin.tickets.status', $ticket), ['status' => 'resolved'])
            ->assertRedirect();

        $mails = $this->queuedTo('resolve-client@example.com');
        $this->assertCount(1, $mails);
        $this->assertSame('Your ticket has been resolved.', $mails->first()->headline);
        $this->assertStringContainsString('Mar 17, 2026', $mails->first()->messageLine);
        $this->assertSame('Re: ['.$ticket->ticket_number.'] Test ticket subject', $mails->first()->subjectLine);
        $this->assertFalse($mails->first()->clientThreadRoot);

        // Staff flip it back and resolve again in the same cycle: no second email.
        $this->actingAs($this->admin())->post(route('admin.tickets.status', $ticket), ['status' => 'closed', 'close_reason' => 'Done']);
        $this->actingAs($this->admin())->post(route('admin.tickets.status', $ticket), ['status' => 'resolved']);

        $this->assertCount(1, $this->queuedTo('resolve-client@example.com'));
    }

    public function test_a_reopened_ticket_emails_the_client_again_when_it_is_resolved_a_second_time(): void
    {
        $client = $this->createClient('Again Client', 'again-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tickets.status', $ticket), ['status' => 'resolved']);
        $this->actingAs($admin)->post(route('admin.tickets.status', $ticket), ['status' => 'in_progress']);
        $this->actingAs($admin)->post(route('admin.tickets.status', $ticket), ['status' => 'resolved']);

        $this->assertCount(2, $this->queuedTo('again-client@example.com'));
    }

    public function test_bulk_and_quick_updates_also_email_the_client_on_resolution(): void
    {
        $client = $this->createClient('Bulk Client', 'bulk-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($this->admin())->post(route('admin.tickets.bulk-action'), [
            'action' => 'status',
            'selected_ids' => [$ticket->id],
            'status' => 'resolved',
        ]);

        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertCount(1, $this->queuedTo('bulk-client@example.com'));
    }

    public function test_closing_or_cancelling_sends_nothing_unless_the_office_turns_it_on(): void
    {
        $client = $this->createClient('Quiet Client', 'quiet-client@example.com');
        $category = $this->createCategory();
        $closeTicket = $this->createTicket($client, $category, ['status' => 'resolved', 'resolved_at' => now()]);
        $cancelTicket = $this->createTicket($client, $category, ['status' => 'pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tickets.status', $closeTicket), ['status' => 'closed', 'close_reason' => 'Done']);
        $this->actingAs($admin)->post(route('admin.tickets.status', $cancelTicket), ['status' => 'cancelled', 'close_reason' => 'Duplicate']);

        Mail::assertNothingQueued();
    }

    public function test_closed_and_cancelled_emails_are_sent_when_enabled_and_carry_the_cancellation_reason(): void
    {
        config(['helpdesk.client_emails.closed' => true]);
        $client = $this->createClient('Loud Client', 'loud-client@example.com');
        $category = $this->createCategory();
        $closeTicket = $this->createTicket($client, $category, ['status' => 'resolved', 'resolved_at' => now()]);
        $cancelTicket = $this->createTicket($client, $category, ['status' => 'pending']);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.tickets.status', $closeTicket), ['status' => 'closed', 'close_reason' => 'Done']);
        $this->actingAs($admin)->post(route('admin.tickets.status', $cancelTicket), ['status' => 'cancelled', 'close_reason' => 'Duplicate of TK-1']);

        $mails = $this->queuedTo('loud-client@example.com');
        $this->assertCount(2, $mails);
        $this->assertTrue($mails->contains(fn ($mail) => $mail->headline === 'Your ticket has been closed.'));
        $cancelMail = $mails->first(fn ($mail) => $mail->headline === 'Your ticket was cancelled.');
        $this->assertSame('Duplicate of TK-1', $cancelMail->details['Reason']);
    }

    public function test_resolving_or_closing_a_ticket_whose_dates_were_entered_in_the_past_emails_nobody(): void
    {
        config(['helpdesk.client_emails.closed' => true]);
        $client = $this->createClient('Backlog Status Client', 'backlog-status@example.com');
        $category = $this->createCategory();
        $resolveTicket = $this->createTicket($client, $category, ['status' => 'in_progress']);
        $closeTicket = $this->createTicket($client, $category, ['status' => 'in_progress']);
        $admin = $this->admin();

        // Staff record when the work really happened, then set the status.
        $this->actingAs($admin)->post(route('admin.tickets.dates', $resolveTicket), [
            'created_at' => now()->subMonths(2)->format('Y-m-d\TH:i'),
            'resolved_at' => now()->subMonths(2)->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.tickets.dates', $closeTicket), [
            'created_at' => now()->subMonths(2)->format('Y-m-d\TH:i'),
            'resolved_at' => now()->subMonths(2)->addDay()->format('Y-m-d\TH:i'),
            'closed_at' => now()->subMonths(2)->addDays(2)->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.tickets.status', $resolveTicket), ['status' => 'resolved'])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.tickets.status', $closeTicket), ['status' => 'closed', 'close_reason' => 'Done on paper'])->assertRedirect();

        $this->assertSame('resolved', $resolveTicket->fresh()->status);
        $this->assertSame('closed', $closeTicket->fresh()->status);
        $this->assertTrue($closeTicket->fresh()->closed_at->lt(now()->subMonth()));
        Mail::assertNothingQueued();
    }

    public function test_a_resolution_dated_earlier_today_still_emails_the_client(): void
    {
        $client = $this->createClient('Today Client', 'today-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'in_progress',
            'resolved_at' => now()->subHours(3),
        ]);

        $this->actingAs($this->admin())->post(route('admin.tickets.status', $ticket), ['status' => 'resolved'])->assertRedirect();

        $this->assertCount(1, $this->queuedTo('today-client@example.com'));
    }

    public function test_clients_are_never_emailed_about_their_own_actions(): void
    {
        $client = $this->createClient('Self Client', 'self-client@example.com');
        $category = $this->createCategory();
        $cancelTicket = $this->createTicket($client, $category, ['status' => 'open']);
        $resolveTicket = $this->createTicket($client, $category, ['status' => 'open']);
        config(['helpdesk.client_emails.closed' => true]);

        $this->actingAs($client)->post(route('client.tickets.cancel', $cancelTicket), ['cancel_reason' => 'Filed by mistake']);
        $this->actingAs($client)->post(route('client.tickets.resolve', $resolveTicket), [
            'resolve_confirmation' => '1',
            'rating' => '5',
            'comment' => 'Fixed, thanks',
        ]);

        Mail::assertNothingQueued();
    }

    // ---- agent replies: batching ------------------------------------------

    private function publicAgentReply(Ticket $ticket, User $agent, string $message): TicketReply
    {
        return TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'message' => $message,
            'is_internal' => false,
        ]);
    }

    public function test_agent_replies_within_the_window_are_merged_into_one_email(): void
    {
        $client = $this->createClient('Digest Client', 'digest-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());
        $agent = $this->admin();

        $this->publicAgentReply($ticket, $agent, 'First');
        Carbon::setTestNow(now()->addMinutes(2));
        $this->publicAgentReply($ticket, $agent, 'Second');

        // Still inside the five-minute window: nothing yet.
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        Mail::assertNothingQueued();

        Carbon::setTestNow(now()->addMinutes(4));
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $mails = $this->queuedTo('digest-client@example.com');
        $this->assertCount(1, $mails);
        $this->assertSame('There are new replies on your ticket.', $mails->first()->headline);
        $this->assertStringContainsString('2 new responses', $mails->first()->messageLine);
        $this->assertSame(2, TicketReply::query()->whereNotNull('client_notified_reply_at')->count());

        // Nothing new: the next sweep stays silent.
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        $this->assertCount(1, $this->queuedTo('digest-client@example.com'));
    }

    public function test_a_single_agent_reply_is_emailed_by_name_once_the_window_passes(): void
    {
        $client = $this->createClient('Single Client', 'single-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());
        $agent = $this->admin();

        $this->publicAgentReply($ticket, $agent, 'Hello');
        Carbon::setTestNow(now()->addMinutes(6));
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $mail = $this->queuedTo('single-client@example.com')->first();
        $this->assertSame('A support agent has replied to your ticket.', $mail->headline);
        $this->assertStringContainsString('Admin User has posted a new response', $mail->messageLine);
    }

    public function test_internal_notes_client_messages_and_deleted_replies_never_email_the_client(): void
    {
        $client = $this->createClient('Notes Client', 'notes-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());
        $agent = $this->admin();

        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $agent->id, 'message' => 'Internal', 'is_internal' => true]);
        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $client->id, 'message' => 'From client', 'is_internal' => false]);
        $deleted = $this->publicAgentReply($ticket, $agent, 'Retracted');
        $deleted->forceFill(['deleted_at' => now()])->save();

        Carbon::setTestNow(now()->addMinutes(10));
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $this->assertCount(0, $this->queuedTo('notes-client@example.com'));
    }

    public function test_replies_older_than_a_day_are_ignored_by_the_sweep(): void
    {
        $client = $this->createClient('Old Client', 'old-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->publicAgentReply($ticket, $this->admin(), 'Ancient');
        Carbon::setTestNow(now()->addDays(3));
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $this->assertCount(0, $this->queuedTo('old-client@example.com'));
    }

    public function test_setting_the_window_to_zero_emails_each_reply_immediately(): void
    {
        config(['helpdesk.client_emails.reply_batch_minutes' => 0]);
        $client = $this->createClient('Now Client', 'now-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($this->admin())->post(route('admin.tickets.reply', $ticket), ['message' => 'One', 'is_internal' => '0']);
        $this->actingAs($this->admin())->post(route('admin.tickets.reply', $ticket), ['message' => 'Two', 'is_internal' => '0']);

        $this->assertCount(2, $this->queuedTo('now-client@example.com'));
    }

    public function test_with_batching_on_the_reply_request_itself_sends_no_email(): void
    {
        $client = $this->createClient('Wait Client', 'wait-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'in_progress']);

        $this->actingAs($this->admin())->post(route('admin.tickets.reply', $ticket), ['message' => 'Hold on', 'is_internal' => '0']);

        Mail::assertNothingQueued();
    }

    // ---- auto-close warning -----------------------------------------------

    public function test_a_resolved_ticket_is_warned_before_it_is_auto_closed(): void
    {
        $client = $this->createClient('Warn Client', 'warn-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(5)->subHour(),
            'client_notified_resolved_at' => now(),
        ]);
        $young = $this->createTicket($client, $this->createCategory('Other'), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(2),
            'client_notified_resolved_at' => now(),
        ]);

        $this->artisan('tickets:auto-close-resolved')
            ->expectsOutputToContain('Auto-closed 0 resolved ticket(s) inactive for 7+ days. Warned 1 client(s)')
            ->assertSuccessful();

        $mails = $this->queuedTo('warn-client@example.com');
        $this->assertCount(1, $mails);
        $this->assertSame('Your resolved ticket will close soon.', $mails->first()->headline);
        $this->assertSame('Mar 12, 2026', $mails->first()->details['Closes automatically on']);
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertNull($young->fresh()->client_notified_auto_close_warning_at);

        // Running again the same day sends no second warning.
        $this->artisan('tickets:auto-close-resolved')->assertSuccessful();
        $this->assertCount(1, $this->queuedTo('warn-client@example.com'));
    }

    public function test_a_warned_ticket_closes_once_the_notice_period_has_passed(): void
    {
        $client = $this->createClient('Close Client', 'close-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(5)->subHour(),
            'client_notified_resolved_at' => now(),
        ]);

        $this->artisan('tickets:auto-close-resolved')->assertSuccessful();
        $this->assertSame('resolved', $ticket->fresh()->status);

        Carbon::setTestNow(now()->addDays(2)->addHour());
        $this->artisan('tickets:auto-close-resolved')
            ->expectsOutputToContain('Auto-closed 1 resolved ticket(s)')
            ->assertSuccessful();

        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_an_overdue_ticket_that_was_never_warned_gets_a_full_notice_day_first(): void
    {
        $client = $this->createClient('Late Client', 'late-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(8),
            'client_notified_resolved_at' => now(),
        ]);

        $this->artisan('tickets:auto-close-resolved')->assertSuccessful();

        $this->assertSame('resolved', $ticket->fresh()->status);
        $mail = $this->queuedTo('late-client@example.com')->first();
        $this->assertSame('Mar 11, 2026', $mail->details['Closes automatically on']);

        Carbon::setTestNow(now()->addHours(25));
        $this->artisan('tickets:auto-close-resolved')->assertSuccessful();
        $this->assertSame('closed', $ticket->fresh()->status);
    }

    public function test_tickets_whose_client_cannot_be_emailed_still_close_on_schedule(): void
    {
        $inactive = $this->createClient('Gone Client', 'gone-client@example.com', ['is_active' => false]);
        $ticket = $this->createTicket($inactive, $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(8),
        ]);

        $this->artisan('tickets:auto-close-resolved')->assertSuccessful();

        $this->assertSame('closed', $ticket->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_switching_the_warning_off_restores_the_immediate_close(): void
    {
        config(['helpdesk.client_emails.auto_close_warning' => false]);
        $client = $this->createClient('NoWarn Client', 'nowarn-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(8),
        ]);

        $this->artisan('tickets:auto-close-resolved')->assertSuccessful();

        $this->assertSame('closed', $ticket->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_a_past_ticket_entered_as_resolved_is_auto_closed_without_emailing_the_client(): void
    {
        config(['helpdesk.client_emails.closed' => true]);
        $client = $this->createClient('Paper Client', 'paper-client@example.com');

        $this->actingAs($this->admin())->post(route('admin.tickets.store'), [
            'user_id' => $client->id,
            'name' => $client->name,
            'contact_number' => '09170000000',
            'email' => $client->email,
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Resolved on paper last month',
            'description' => 'Entered after being handled on paper.',
            'category_id' => $this->createCategory()->id,
            'ticket_type' => 'external',
            'created_at' => now()->subMonth()->format('Y-m-d\TH:i'),
            'resolved_at' => now()->subMonth()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Resolved on paper last month')->firstOrFail();
        $this->assertSame('resolved', $ticket->status);
        auth()->logout();

        $this->artisan('tickets:auto-close-resolved')
            ->expectsOutputToContain('Auto-closed 1 resolved ticket(s) inactive for 7+ days. Warned 0 client(s)')
            ->assertSuccessful();

        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertCount(0, $this->queuedTo('paper-client@example.com'));
    }

    public function test_a_failing_warning_cannot_keep_a_ticket_open_forever(): void
    {
        $client = $this->createClient('Stuck Client', 'stuck-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subDays(11),
        ]);

        // Simulate the warning never going out: the grace period expires the ticket anyway.
        Ticket::query()->whereKey($ticket->id)->update(['client_notified_auto_close_warning_at' => null]);
        $this->mock(TicketEmailAlertService::class, function ($mock) {
            $mock->shouldReceive('notifyClientOfAutoCloseWarning')->andReturn(false);
            $mock->shouldReceive('shouldWarnClientBeforeAutoClose')->andReturn(true);
        });

        $this->artisan('tickets:auto-close-resolved')->assertSuccessful();

        $this->assertSame('closed', $ticket->fresh()->status);
    }

    // ---- threading ----------------------------------------------------------

    public function test_follow_up_emails_reference_the_received_email_so_clients_see_one_conversation(): void
    {
        config(['mail.default' => 'array', 'mail.from.address' => 'helpdesk@example.org']);
        $client = $this->createClient('Thread Client', 'thread-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());
        $rootId = TicketEmailThread::rootMessageId($ticket);
        $this->assertSame('ticket-'.$ticket->id.'@example.org', $rootId);

        $sendMail = function (bool $root) use ($ticket, $client): Email {
            $mailable = new TicketAlertMail(
                ticket: $ticket,
                subjectLine: TicketEmailThread::subject($ticket, $root),
                headline: 'h',
                messageLine: 'm',
                clientThread: true,
                clientThreadRoot: $root,
            );
            // Mail::fake() replaces the bound manager, so build a real one to see the final headers.
            static $realManager = null;
            $realManager ??= new MailManager(app());
            $mailer = $realManager->mailer('array');
            $mailer->to($client->email)->sendNow($mailable);
            $messages = $mailer->getSymfonyTransport()->messages();

            return $messages->last()->getOriginalMessage();
        };

        $first = $sendMail(true);
        $this->assertSame('<'.$rootId.'>', '<'.$first->getHeaders()->get('Message-ID')->getId().'>');
        $this->assertFalse($first->getHeaders()->has('In-Reply-To'));

        $later = $sendMail(false);
        $this->assertSame('<'.$rootId.'>', $later->getHeaders()->get('In-Reply-To')->getBodyAsString());
        $this->assertSame('<'.$rootId.'>', $later->getHeaders()->get('References')->getBodyAsString());
        $this->assertNotSame($first->getSubject(), $later->getSubject());
        $this->assertSame('Re: '.$first->getSubject(), $later->getSubject());
    }

    public function test_thread_subject_is_single_line_and_bounded(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['subject' => "Line one\r\nBcc: x@evil.test ".str_repeat('a', 200)]);

        $subject = TicketEmailThread::subject($ticket, true);

        $this->assertStringNotContainsString("\n", $subject);
        $this->assertStringNotContainsString("\r", $subject);
        $this->assertLessThanOrEqual(110, strlen($subject));
        $this->assertStringStartsWith('['.$ticket->ticket_number.'] Line one', $subject);
    }

    public function test_client_emails_say_that_replying_does_not_reach_the_ticket_but_staff_emails_do_not(): void
    {
        $ticket = $this->createTicket($this->createClient(), $this->createCategory());

        $clientMail = (new TicketAlertMail($ticket, 'S', 'Headline', 'Body', actionUrl: 'https://example.test/t', actionLabel: 'Open Ticket', clientThread: true))->render();
        $staffMail = (new TicketAlertMail($ticket, 'S', 'Headline', 'Body', actionUrl: 'https://example.test/t', actionLabel: 'Open Ticket'))->render();

        $this->assertStringContainsString('will not add your message to the ticket', $clientMail);
        $this->assertStringNotContainsString('will not add your message to the ticket', $staffMail);
    }

    public function test_staff_emails_are_not_threaded(): void
    {
        $mail = new TicketAlertMail(
            ticket: $this->createTicket($this->createClient(), $this->createCategory()),
            subjectLine: 'Ticket Assigned: X',
            headline: 'h',
            messageLine: 'm',
        );

        $headers = $mail->headers();
        $this->assertNull($headers->messageId);
        $this->assertSame([], $headers->references);
        $this->assertSame([], $headers->text);
    }

    public function test_the_staff_requester_of_an_internal_ticket_gets_no_client_emails(): void
    {
        $requester = $this->createUser('Staff Requester', 'staff-requester@example.com', User::ROLE_TECHNICAL);
        $assignee = $this->createAssignedSupportUser('Assignee Tech', 'assignee-tech@example.com');
        $category = $this->createCategory();

        // Another staff member files it for them, so it is not the "filed it for myself" case.
        $this->actingAs($this->admin())->post(route('admin.tickets.store'), [
            'ticket_type' => 'internal',
            'user_id' => $requester->id,
            'name' => $requester->name,
            'contact_number' => '09170000000',
            'email' => $requester->email,
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Internal request',
            'description' => 'Need a laptop.',
            'category_id' => $category->id,
            'assigned_to' => [$assignee->id],
        ])->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Internal request')->firstOrFail();
        $alerts = app(TicketEmailAlertService::class);

        $this->assertFalse($alerts->notifyClientOfTicketReceived($ticket));
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
        $this->assertFalse($alerts->notifyClientOfStatusChange($ticket->fresh()));
        $this->assertFalse($alerts->shouldWarnClientBeforeAutoClose($ticket->fresh()));
        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $assignee->id, 'message' => 'Done', 'is_internal' => false]);
        $alerts->sendScheduledReminders();

        // The link in these emails is the client's own ticket page, which staff cannot open.
        Mail::assertNotQueued(TicketAlertMail::class, fn (TicketAlertMail $mail) => $mail->hasTo($requester->email) && str_contains((string) $mail->actionUrl, '/client/tickets'));
        $this->assertNull($ticket->fresh()->client_notified_received_at);
        $this->assertNull($ticket->fresh()->client_notified_resolved_at);
    }

    public function test_a_client_still_gets_the_same_emails_for_an_external_ticket(): void
    {
        $client = $this->createClient('Still Notified', 'still-notified@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->assertTrue(app(TicketEmailAlertService::class)->notifyClientOfTicketReceived($ticket));
        $this->assertCount(1, $this->queuedTo($client->email));
    }
}
