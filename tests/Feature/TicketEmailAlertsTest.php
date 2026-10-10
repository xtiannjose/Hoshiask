<?php

namespace Tests\Feature;

use App\Mail\TicketAlertMail;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketEmailAlertsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 2, 25, 8, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_client_ticket_creation_only_queues_the_clients_own_receipt(): void
    {
        Mail::fake();

        $client = $this->createUser('Client Alerts', 'client-alerts@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $response = $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Client Alerts',
            'contact_number' => '09181234567',
            'email' => 'client-alerts@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Email alert on new ticket',
            'description' => 'Need confirmation that super users are alerted.',
            'category_id' => $category->id,
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ]);

        $response->assertRedirect();

        Ticket::query()->where('subject', 'Email alert on new ticket')->firstOrFail();

        // No super users exist here, so the only email is the client's "we received your ticket".
        $queued = Mail::queued(TicketAlertMail::class);
        $this->assertCount(1, $queued);
        $this->assertTrue($queued->first()->hasTo('client-alerts@example.com'));
    }

    public function test_technical_user_receives_email_when_ticket_is_assigned(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Assigner', 'super-assigner@example.com', User::ROLE_SUPER_USER);
        $technical = $this->createUser('Tech Alerts', 'tech-alerts@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Assign', 'client-assign@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $technical->id,
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame($technical->id, (int) $ticket->assigned_to);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNotNull($ticket->technical_user_notified_assignment_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical, $ticket) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:')
                && $mail->ticket->is($ticket);
        });
    }

    public function test_closed_ticket_assignment_does_not_send_email(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Closed Assigner', 'super-closed-assigner@example.com', User::ROLE_SUPER_USER);
        $technical = $this->createUser('Tech Closed Alerts', 'tech-closed-alerts@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Closed Assign', 'client-closed-assign@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, [
            'status' => 'closed',
            'closed_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => $technical->id,
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame($technical->id, (int) $ticket->assigned_to);
        $this->assertNotNull($ticket->assigned_at);
        $this->assertNull($ticket->technical_user_notified_assignment_at);

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:');
        });
    }

    public function test_multiple_technical_users_receive_assignment_email_when_ticket_is_assigned(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super Multi Assigner', 'super-multi-assigner@example.com', User::ROLE_SUPER_USER);
        $primaryTechnical = $this->createUser('Primary Tech Alerts', 'primary-tech-alerts@example.com', User::ROLE_TECHNICAL);
        $secondaryTechnical = $this->createUser('Secondary Tech Alerts', 'secondary-tech-alerts@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Multi Assign', 'client-multi-assign@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => [$primaryTechnical->id, $secondaryTechnical->id],
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame($primaryTechnical->id, (int) $ticket->assigned_to);
        $this->assertEqualsCanonicalizing(
            [$primaryTechnical->id, $secondaryTechnical->id],
            $ticket->assignedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->sort()->values()->all()
        );
        $this->assertNotNull($ticket->technical_user_notified_assignment_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($primaryTechnical, $ticket) {
            return $mail->hasTo($primaryTechnical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:')
                && $mail->ticket->is($ticket);
        });

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($secondaryTechnical, $ticket) {
            return $mail->hasTo($secondaryTechnical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:')
                && $mail->ticket->is($ticket);
        });
    }

    public function test_only_technical_users_receive_assignment_email_when_jointly_assigned(): void
    {
        Mail::fake();

        $assigningSuperUser = $this->createUser('Super Joint Assigner', 'super-joint-assigner@example.com', User::ROLE_SUPER_USER);
        $assignedSuperUser = $this->createUser('Assigned Super User', 'assigned-super-user@example.com', User::ROLE_SUPER_USER);
        $technical = $this->createUser('Joint Tech Alerts', 'joint-tech-alerts@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Joint Client', 'joint-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($assigningSuperUser)->post(route('admin.tickets.assign', $ticket), [
            'assigned_to' => [$assignedSuperUser->id, $technical->id],
        ]);

        $response->assertRedirect();

        $ticket->refresh();
        $this->assertSame($assignedSuperUser->id, (int) $ticket->assigned_to);
        $this->assertEqualsCanonicalizing(
            [$assignedSuperUser->id, $technical->id],
            $ticket->assigned_user_ids
        );

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical, $ticket) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:')
                && $mail->ticket->is($ticket);
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($assignedSuperUser) {
            return $mail->hasTo($assignedSuperUser->email)
                && str_starts_with($mail->subjectLine, 'Ticket Assigned:');
        });
    }

    public function test_command_does_not_send_unchecked_ticket_alerts_to_super_users(): void
    {
        Mail::fake();

        $client = $this->createUser('Client Reminder', 'client-reminder@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category);
        $ticket->forceFill([
            'created_at' => now()->subMinutes(51),
            'updated_at' => now()->subMinutes(51),
        ])->save();

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $this->assertCount(0, Mail::queued(TicketAlertMail::class));
    }

    public function test_command_does_not_send_unassigned_sla_warning_after_super_user_view(): void
    {
        Mail::fake();

        $superUser = $this->createUser('Super SLA', 'super-sla@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Client SLA', 'client-sla@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category);

        TicketUserState::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'last_seen_at' => now()->subHours(3)->subMinutes(31),
            'acknowledged_at' => now()->subHours(3)->subMinutes(31),
            'dismissed_at' => null,
        ]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $this->assertCount(0, Mail::queued(TicketAlertMail::class));
    }

    public function test_super_user_reminders_skip_a_ticket_that_is_assigned_only_through_the_assignee_list(): void
    {
        Mail::fake();

        $this->createUser('Super Reminder', 'super-reminder@example.com', User::ROLE_SUPER_USER);
        $technical = $this->createUser('Tech Listed', 'tech-listed@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Listed', 'client-listed@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $unassigned = $this->createTicket($client, $category, ['subject' => 'Nobody has it']);
        $listedOnly = $this->createTicket($client, $category, ['subject' => 'Only in the assignee list']);
        $listedOnly->assignedUsers()->sync([$technical->id]);

        foreach ([$unassigned, $listedOnly] as $ticket) {
            $ticket->forceFill([
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(4),
            ])->save();
        }
        // The primary assignee is gone but the list still has the technician: the state that
        // deleting a user passes through.
        $listedOnly->forceFill(['assigned_to' => null])->save();
        $this->assertNull($listedOnly->fresh()->assigned_to);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $mailsFor = fn (Ticket $ticket) => Mail::queued(TicketAlertMail::class)
            ->filter(fn (TicketAlertMail $mail) => $mail->ticket->is($ticket))
            ->count();

        $this->assertGreaterThan(0, $mailsFor($unassigned), 'A really unassigned ticket should still be reported.');
        $this->assertSame(0, $mailsFor($listedOnly));
    }

    public function test_super_user_reminders_say_how_long_the_ticket_has_been_unassigned(): void
    {
        Mail::fake();

        $this->createUser('Super Wording', 'super-wording@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Client Wording', 'client-wording@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $ticket = $this->createTicket($client, $this->createCategory());
        $ticket->forceFill(['created_at' => now()->subHours(4), 'updated_at' => now()->subHours(4)])->save();

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $mails = Mail::queued(TicketAlertMail::class)->keyBy(fn (TicketAlertMail $mail) => $mail->subjectLine);
        $number = $ticket->ticket_number;

        $unchecked = $mails->get('Unchecked Ticket Alert: '.$number);
        $this->assertNotNull($unchecked);
        $this->assertSame('A ticket has been open and unassigned for over 2 hours.', $unchecked->headline);
        $this->assertSame('This ticket needs to be reviewed and assigned to technical staff.', $unchecked->messageLine);

        $unassigned = $mails->get('Unassigned SLA Warning: '.$number);
        $this->assertNotNull($unassigned);
        $this->assertSame('An unassigned ticket is approaching the SLA resolution target.', $unassigned->headline);
        $this->assertSame('This ticket has been unassigned for 3 hours and risks breaching the SLA.', $unassigned->messageLine);

        $ticket->refresh();
        $this->assertNotNull($ticket->super_users_notified_unchecked_at);
        $this->assertNotNull($ticket->super_users_notified_unassigned_sla_at);
    }

    public function test_command_sends_assigned_sla_warning_to_technical_user(): void
    {
        Mail::fake();

        $technical = $this->createUser('Tech SLA', 'tech-sla@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Tech SLA', 'client-tech-sla@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category, [
            'assigned_to' => $technical->id,
            'assigned_at' => now()->subHours(3)->subMinutes(31),
            'status' => 'in_progress',
        ]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->technical_user_notified_sla_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical, $ticket) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, '4-Hours SLA Reminder (Assigned):')
                && $mail->ticket->is($ticket);
        });
    }

    public function test_recording_a_sent_reminder_does_not_count_as_ticket_activity(): void
    {
        Mail::fake();

        $technical = $this->createUser('Tech Quiet', 'tech-quiet@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Client Quiet', 'client-quiet@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category, [
            'assigned_to' => $technical->id,
            'assigned_at' => now()->subHours(3)->subMinutes(31),
            'status' => 'in_progress',
        ]);
        $originalUpdatedAt = $ticket->fresh()->updated_at;

        Carbon::setTestNow(now()->addMinutes(10));
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->technical_user_notified_sla_at);
        $this->assertTrue(
            $ticket->updated_at->equalTo($originalUpdatedAt),
            'Stamping the reminder time must not bump updated_at (it drives list refreshes and notification ordering).'
        );
    }

    public function test_command_sends_assigned_sla_warning_only_to_assigned_technical_users(): void
    {
        Mail::fake();

        $assignedSuperUser = $this->createUser('Assigned SLA Super', 'assigned-sla-super@example.com', User::ROLE_SUPER_USER);
        $technical = $this->createUser('Assigned SLA Tech', 'assigned-sla-tech@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Assigned SLA Client', 'assigned-sla-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category, [
            'assigned_to' => $assignedSuperUser->id,
            'assigned_at' => now()->subHours(3)->subMinutes(31),
            'status' => 'in_progress',
        ]);
        $ticket->assignedUsers()->sync([$assignedSuperUser->id, $technical->id]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->technical_user_notified_sla_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical, $ticket) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, '4-Hours SLA Reminder (Assigned):')
                && $mail->ticket->is($ticket);
        });

        Mail::assertNotQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($assignedSuperUser) {
            return $mail->hasTo($assignedSuperUser->email)
                && str_starts_with($mail->subjectLine, '4-Hours SLA Reminder (Assigned):');
        });
    }

    public function test_command_uses_configured_assignment_sla_reminder_threshold(): void
    {
        Mail::fake();
        config([
            'helpdesk.sla.assignment_reminder_minutes' => 30,
            'helpdesk.sla.resolution_target_minutes' => 60,
        ]);

        $technical = $this->createUser('Config SLA Tech', 'config-sla-tech@example.com', User::ROLE_TECHNICAL);
        $client = $this->createUser('Config SLA Client', 'config-email-sla-client@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $category = $this->createCategory();

        $ticket = $this->createTicket($client, $category, [
            'assigned_to' => $technical->id,
            'assigned_at' => now()->subMinutes(31),
            'status' => 'in_progress',
        ]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();

        $ticket->refresh();
        $this->assertNotNull($ticket->technical_user_notified_sla_at);

        Mail::assertQueued(TicketAlertMail::class, function (TicketAlertMail $mail) use ($technical, $ticket) {
            return $mail->hasTo($technical->email)
                && str_starts_with($mail->subjectLine, '1-Hour SLA Reminder (Assigned):')
                && $mail->ticket->is($ticket);
        });
    }

    public function test_moving_an_active_ticket_to_another_active_status_does_not_resend_the_sla_reminder(): void
    {
        Mail::fake();

        $admin = $this->createUser('Admin Active Move', 'admin-active-move@example.com', User::ROLE_ADMIN);
        $technical = $this->createUser('Tech Active Move', 'tech-active-move@example.com', User::ROLE_TECHNICAL);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'assigned_to' => $technical->id,
            'assigned_at' => now()->subHours(3)->subMinutes(31),
            'status' => 'in_progress',
        ]);

        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        $this->assertNotNull($ticket->fresh()->technical_user_notified_sla_at);

        $this->actingAs($admin)->post(route('admin.tickets.status', $ticket), ['status' => 'pending']);
        $this->assertSame('pending', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->technical_user_notified_sla_at);

        Mail::fake();
        $this->artisan('tickets:send-alert-emails')->assertSuccessful();
        Mail::assertNotQueued(TicketAlertMail::class, fn (TicketAlertMail $mail) => $mail->hasTo($technical->email));
    }

    public function test_changing_only_the_severity_of_an_open_ticket_keeps_the_super_user_reminder_marks(): void
    {
        $admin = $this->createUser('Admin Severity Edit', 'admin-severity-edit@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'open',
            'priority' => 'severity_2',
        ]);
        $ticket->forceFill([
            'super_users_notified_unchecked_at' => now()->subHour(),
            'super_users_notified_unassigned_sla_at' => now()->subHour(),
        ])->save();

        $this->actingAs($admin)->post(route('admin.tickets.quick-update', $ticket), [
            'assigned_to' => '',
            'status' => 'open',
            'priority' => 'severity_1',
        ]);

        $ticket->refresh();
        $this->assertSame('severity_1', $ticket->priority);
        $this->assertNotNull($ticket->super_users_notified_unchecked_at);
        $this->assertNotNull($ticket->super_users_notified_unassigned_sla_at);
    }

    public function test_reopening_a_resolved_ticket_still_clears_the_sla_reminder_marks(): void
    {
        $admin = $this->createUser('Admin Reopen Marks', 'admin-reopen-marks@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), [
            'status' => 'resolved',
            'resolved_at' => now()->subHour(),
        ]);
        $ticket->forceFill(['technical_user_notified_sla_at' => now()->subHours(2)])->save();

        $this->actingAs($admin)->post(route('admin.tickets.status', $ticket), ['status' => 'open']);

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->technical_user_notified_sla_at);
    }
}
