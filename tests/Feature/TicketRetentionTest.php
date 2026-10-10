<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Operations\HelpdeskOperationsStatusService;
use App\Services\Retention\TicketRetentionService;
use App\Services\SystemLogService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketRetentionTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $client;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['helpdesk.attachments_disk' => 'local', 'retention.enabled' => true, 'retention.years' => 3]);
        $this->client = $this->createClient();
        $this->category = $this->createCategory();
    }

    private function oldClosedTicket(array $overrides = [], int $yearsAgo = 4): Ticket
    {
        $ticket = $this->createTicket($this->client, $this->category, array_merge([
            'status' => 'closed',
            'closed_at' => now()->subYears($yearsAgo),
            'resolved_at' => now()->subYears($yearsAgo)->subDay(),
            'satisfaction_rating' => 4,
            'satisfaction_comment' => 'Thank you, Maria Santos',
            'root_cause_category' => 'hardware',
            'root_cause_notes' => 'Maria Santos spilled coffee',
            'consent_accepted_at' => now()->subYears($yearsAgo)->subDays(2),
            'consent_version' => '2025-01',
            'consent_ip_address' => '203.0.113.9',
            'consent_user_agent' => 'Mozilla/5.0 test',
        ], $overrides));

        DB::table('tickets')->where('id', $ticket->id)->update(['updated_at' => now()->subYears($yearsAgo)]);

        return $ticket->fresh();
    }

    private function attach(Ticket|TicketReply $owner, string $name): Attachment
    {
        $path = 'attachments/'.$name;
        Storage::disk('local')->put($path, 'file '.$name);

        return Attachment::create([
            'filename' => $name,
            'original_filename' => $name,
            'file_path' => $path,
            'mime_type' => 'text/plain',
            'file_size' => 10,
            'attachable_type' => $owner::class,
            'attachable_id' => $owner->id,
        ]);
    }

    public function test_eligibility_follows_status_age_hold_and_earlier_clean_up(): void
    {
        $service = app(TicketRetentionService::class);
        $closedLongAgo = $this->oldClosedTicket();
        $cancelledLongAgo = $this->oldClosedTicket(['status' => 'cancelled', 'closed_at' => null, 'cancelled_at' => now()->subYears(5)]);
        $justInside = $this->oldClosedTicket(['closed_at' => now()->subYears(3)->addDay()]);
        $justOutside = $this->oldClosedTicket(['closed_at' => now()->subYears(3)->subMinute()]);
        $open = $this->oldClosedTicket(['status' => 'open', 'closed_at' => null, 'resolved_at' => null]);
        $resolved = $this->oldClosedTicket(['status' => 'resolved', 'closed_at' => null]);
        $held = $this->oldClosedTicket();
        $service->setHold($held, true);
        $done = $this->oldClosedTicket();
        DB::table('tickets')->where('id', $done->id)->update(['anonymized_at' => now()]);

        $ids = $service->eligible()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$closedLongAgo->id, $cancelledLongAgo->id, $justOutside->id], $ids);
        $this->assertNotContains($justInside->id, $ids);
        $this->assertNotContains($open->id, $ids);
        $this->assertNotContains($resolved->id, $ids);
        $this->assertNotContains($held->id, $ids);
        $this->assertNotContains($done->id, $ids);
        $this->assertSame(3, $service->countEligible());
        $this->assertSame(0, $service->countEligible(10));
    }

    public function test_a_cleaned_ticket_loses_every_personal_detail_and_keeps_its_statistics(): void
    {
        $ticket = $this->oldClosedTicket(['cancellation_reason' => 'Maria Santos asked', 'escalation_reason' => 'Call Maria Santos']);
        $before = DB::table('tickets')->where('id', $ticket->id)->first();
        $reply = TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $this->client->id, 'message' => 'My number is 09185551234', 'is_internal' => false]);
        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $this->client->id, 'message' => 'Deleted note about Maria', 'is_internal' => false, 'deleted_at' => now()]);
        $ticketFile = $this->attach($ticket, 'ticket-file.txt');
        $replyFile = $this->attach($reply, 'reply-file.txt');

        app(TicketRetentionService::class)->anonymize($ticket);

        $after = DB::table('tickets')->where('id', $ticket->id)->first();
        $this->assertSame('Removed', $after->name);
        $this->assertNull($after->contact_number);
        $this->assertNull($after->email);
        $this->assertSame('Ticket '.$ticket->ticket_number, $after->subject);
        $this->assertSame(TicketRetentionService::PLACEHOLDER, $after->description);
        foreach (['satisfaction_comment', 'root_cause_notes', 'escalation_reason', 'cancellation_reason', 'consent_ip_address', 'consent_user_agent'] as $column) {
            $this->assertNull($after->{$column}, $column);
        }
        $this->assertNotNull($after->anonymized_at);

        foreach (['ticket_number', 'status', 'priority', 'category_id', 'department', 'province', 'municipality', 'ticket_type', 'user_id', 'assigned_to', 'resolved_at', 'closed_at', 'cancelled_at', 'created_at', 'updated_at', 'satisfaction_rating', 'root_cause_category', 'consent_accepted_at', 'consent_version'] as $column) {
            $this->assertEquals($before->{$column}, $after->{$column}, $column);
        }

        $this->assertSame(2, TicketReply::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame([TicketRetentionService::PLACEHOLDER], TicketReply::query()->where('ticket_id', $ticket->id)->pluck('message')->unique()->all());
        $this->assertNull(Attachment::find($ticketFile->id));
        $this->assertNull(Attachment::find($replyFile->id));
        Storage::disk('local')->assertMissing('attachments/ticket-file.txt');
        Storage::disk('local')->assertMissing('attachments/reply-file.txt');
    }

    public function test_a_missing_file_does_not_stop_the_clean_up(): void
    {
        $ticket = $this->oldClosedTicket();
        $file = $this->attach($ticket, 'gone.txt');
        Storage::disk('local')->delete('attachments/gone.txt');

        app(TicketRetentionService::class)->anonymize($ticket);

        $this->assertNull(Attachment::find($file->id));
        $this->assertNotNull($ticket->fresh()->anonymized_at);
    }

    public function test_audit_entries_that_copied_typed_text_are_scrubbed(): void
    {
        $ticket = $this->oldClosedTicket();
        app(SystemLogService::class)->record('ticket.cancelled', 'Cancelled a ticket.', [
            'category' => 'ticket',
            'target_type' => Ticket::class,
            'target_id' => $ticket->id,
            'metadata' => ['reason' => 'Maria Santos asked', 'ticket_number' => $ticket->ticket_number],
        ]);

        app(TicketRetentionService::class)->anonymize($ticket);

        $metadata = SystemLog::query()->where('event_type', 'ticket.cancelled')->firstOrFail()->metadata;
        $this->assertSame(TicketRetentionService::PLACEHOLDER, $metadata['reason']);
        $this->assertSame($ticket->ticket_number, $metadata['ticket_number']);
    }

    public function test_the_old_text_kept_for_an_edited_reply_is_scrubbed_too(): void
    {
        $ticket = $this->oldClosedTicket();
        app(SystemLogService::class)->record('ticket.reply.updated', 'Edited a ticket reply.', [
            'category' => 'ticket',
            'target_type' => Ticket::class,
            'target_id' => $ticket->id,
            'metadata' => ['message_before' => 'Call Maria Santos on 0917 000 0000', 'reply_id' => 12],
        ]);

        app(TicketRetentionService::class)->anonymize($ticket);

        $metadata = SystemLog::query()->where('event_type', 'ticket.reply.updated')->firstOrFail()->metadata;
        $this->assertSame(TicketRetentionService::PLACEHOLDER, $metadata['message_before']);
        $this->assertSame(12, $metadata['reply_id']);
    }

    public function test_audit_entries_naming_the_tickets_files_are_scrubbed(): void
    {
        $ticket = $this->oldClosedTicket();
        $reply = TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $this->client->id, 'message' => 'Here it is']);
        $other = $this->createTicket($this->client, $this->category);
        foreach ([$this->attach($ticket, 'MariaSantos_Passport.pdf'), $this->attach($reply, 'MariaSantos_Payslip.pdf'), $this->attach($other, 'Kept_Invoice.pdf')] as $attachment) {
            app(SystemLogService::class)->record('attachment.downloaded', 'Downloaded attachment: '.$attachment->original_filename, [
                'category' => 'ticket',
                'target_type' => Attachment::class,
                'target_id' => $attachment->id,
            ]);
        }

        app(TicketRetentionService::class)->anonymize($ticket);

        $descriptions = SystemLog::query()->where('event_type', 'attachment.downloaded')->pluck('description');
        $this->assertCount(3, $descriptions);
        $this->assertFalse($descriptions->contains(fn (string $text) => str_contains($text, 'MariaSantos')));
        $this->assertContains('Downloaded attachment: Kept_Invoice.pdf', $descriptions);
    }

    public function test_other_tickets_are_untouched(): void
    {
        $old = $this->oldClosedTicket();
        $recent = $this->oldClosedTicket([], 1);
        $open = $this->createTicket($this->client, $this->category, ['subject' => 'Printer is jammed']);

        $result = app(TicketRetentionService::class)->enforce();

        $this->assertSame([$old->ticket_number], $result['cleaned']);
        $this->assertSame('Test ticket subject', $recent->fresh()->subject);
        $this->assertSame('Printer is jammed', $open->fresh()->subject);
        $this->assertNull($recent->fresh()->anonymized_at);
    }

    public function test_updated_at_is_left_alone(): void
    {
        $ticket = $this->oldClosedTicket();
        $updatedAt = DB::table('tickets')->where('id', $ticket->id)->value('updated_at');

        app(TicketRetentionService::class)->anonymize($ticket);

        $this->assertSame($updatedAt, DB::table('tickets')->where('id', $ticket->id)->value('updated_at'));
    }

    public function test_the_command_works_in_batches_and_a_second_run_does_nothing(): void
    {
        config(['retention.batch_size' => 2]);
        foreach (range(1, 5) as $unused) {
            $this->oldClosedTicket();
        }

        $this->artisan('helpdesk:enforce-retention')->expectsOutputToContain('Removed the personal details from 5 ticket(s).')->assertSuccessful();
        $this->assertSame(5, Ticket::query()->whereNotNull('anonymized_at')->count());
        $this->artisan('helpdesk:enforce-retention')->expectsOutputToContain('Removed the personal details from 0 ticket(s).')->assertSuccessful();
        $this->assertSame(1, SystemLog::query()->where('event_type', 'retention.enforced')->count());
    }

    public function test_the_log_entry_holds_counts_and_ticket_numbers_but_no_personal_data(): void
    {
        $ticket = $this->oldClosedTicket();

        $this->artisan('helpdesk:enforce-retention')->assertSuccessful();

        $entry = SystemLog::query()->where('event_type', 'retention.enforced')->firstOrFail();
        $this->assertSame(1, $entry->metadata['tickets_cleaned']);
        $this->assertSame([$ticket->ticket_number], $entry->metadata['ticket_numbers']);
        $encoded = json_encode([$entry->description, $entry->metadata]);
        foreach (['Maria', 'requester@example.com', '09185551234', 'Test ticket subject'] as $personal) {
            $this->assertStringNotContainsString($personal, $encoded);
        }
    }

    public function test_the_log_entry_lists_at_most_one_hundred_ticket_numbers(): void
    {
        foreach (range(1, 103) as $unused) {
            $this->oldClosedTicket();
        }

        $this->artisan('helpdesk:enforce-retention')->assertSuccessful();

        $entry = SystemLog::query()->where('event_type', 'retention.enforced')->firstOrFail();
        $this->assertSame(103, $entry->metadata['tickets_cleaned']);
        $this->assertCount(100, $entry->metadata['ticket_numbers']);
    }

    public function test_nothing_is_logged_when_nothing_was_cleaned(): void
    {
        $this->artisan('helpdesk:enforce-retention')->assertSuccessful();

        $this->assertSame(0, SystemLog::query()->where('event_type', 'retention.enforced')->count());
    }

    public function test_dry_run_lists_tickets_and_changes_nothing_even_when_switched_on(): void
    {
        $ticket = $this->oldClosedTicket();

        $this->artisan('helpdesk:enforce-retention --dry-run')
            ->expectsOutputToContain('Tickets past it that still hold personal details: 1.')
            ->expectsOutputToContain('would clean '.$ticket->ticket_number)
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->assertSuccessful();

        $this->assertNull($ticket->fresh()->anonymized_at);
        $this->assertSame('Test ticket subject', $ticket->fresh()->subject);
    }

    public function test_when_switched_off_the_command_names_the_setting_and_changes_nothing(): void
    {
        config(['retention.enabled' => false]);
        $ticket = $this->oldClosedTicket();

        $this->artisan('helpdesk:enforce-retention')
            ->expectsOutputToContain('Tickets past it that still hold personal details: 1.')
            ->expectsOutputToContain('HELPDESK_RETENTION_ENABLED=false')
            ->assertSuccessful();

        $this->assertNull($ticket->fresh()->anonymized_at);
        $this->assertSame(0, SystemLog::query()->where('event_type', 'retention.enforced')->count());
    }

    public function test_the_years_option_overrides_the_setting(): void
    {
        $recent = $this->oldClosedTicket([], 1);

        $this->artisan('helpdesk:enforce-retention --years=1')->assertSuccessful();

        $this->assertNotNull($recent->fresh()->anonymized_at);
    }

    public function test_a_ticket_on_hold_is_skipped_until_the_hold_is_released(): void
    {
        $ticket = $this->oldClosedTicket();

        $this->artisan('helpdesk:retention-hold '.$ticket->ticket_number.' --reason="Complaint 14"')->expectsOutputToContain('is on legal hold')->assertSuccessful();
        $this->artisan('helpdesk:enforce-retention')->assertSuccessful();
        $this->assertNull($ticket->fresh()->anonymized_at);
        $held = SystemLog::query()->where('event_type', 'retention.hold_set')->firstOrFail();
        $this->assertSame('Complaint 14', $held->metadata['reason']);

        $this->artisan('helpdesk:retention-hold '.strtolower($ticket->ticket_number).' --release')->expectsOutputToContain('no longer on legal hold')->assertSuccessful();
        $this->artisan('helpdesk:enforce-retention')->assertSuccessful();
        $this->assertNotNull($ticket->fresh()->anonymized_at);
        $this->assertSame(1, SystemLog::query()->where('event_type', 'retention.hold_released')->count());
    }

    public function test_a_hold_cannot_be_set_on_a_ticket_that_was_already_cleaned_or_does_not_exist(): void
    {
        $ticket = $this->oldClosedTicket();
        app(TicketRetentionService::class)->anonymize($ticket);

        $this->artisan('helpdesk:retention-hold '.$ticket->ticket_number)->expectsOutputToContain('already cleaned')->assertFailed();
        $this->artisan('helpdesk:retention-hold TK-NOPE0000')->expectsOutputToContain('No ticket has the number')->assertFailed();
    }

    public function test_a_ticket_that_fails_is_reported_and_tried_again_next_time(): void
    {
        $failing = $this->oldClosedTicket();
        $fine = $this->oldClosedTicket();
        $this->partialMock(TicketRetentionService::class, function ($mock) use ($failing) {
            $mock->shouldReceive('anonymize')->andReturnUsing(function (Ticket $candidate) use ($failing): void {
                if ($candidate->id === $failing->id) {
                    throw new RuntimeException('disk unavailable');
                }
                DB::table('tickets')->where('id', $candidate->id)->update(['anonymized_at' => now()]);
            });
        });

        $this->artisan('helpdesk:enforce-retention')->expectsOutputToContain('1 ticket(s) could not be cleaned')->assertFailed();

        $this->assertNull($failing->fresh()->anonymized_at);
        $this->assertNotNull($fine->fresh()->anonymized_at);
        $entry = SystemLog::query()->where('event_type', 'retention.enforced')->firstOrFail();
        $this->assertSame(1, $entry->metadata['tickets_failed']);
        $this->assertSame([$failing->ticket_number], $entry->metadata['failed_ticket_numbers']);
    }

    public function test_the_clean_up_is_scheduled_daily_after_the_other_night_jobs(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn (Event $event) => str_contains((string) $event->command, 'helpdesk:enforce-retention'));

        $this->assertNotNull($event);
        $this->assertSame('55 3 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_the_status_check_advises_in_each_state_and_never_warns(): void
    {
        app()->detectEnvironment(fn () => 'production');
        $service = new HelpdeskOperationsStatusService;
        $advisories = fn () => implode("\n", $service->buildStatus(false)['advisories']);

        $this->assertStringNotContainsString('retention period', $advisories());

        $this->oldClosedTicket();
        $this->assertStringContainsString('1 ticket is older than the retention period (3 years)', $advisories());
        $this->assertStringContainsString('has not removed anything in the last 3 days', $advisories());

        config(['retention.enabled' => false]);
        $this->assertStringContainsString('switched off (HELPDESK_RETENTION_ENABLED=false)', $advisories());
        $this->assertStringContainsString('helpdesk:enforce-retention --dry-run', $advisories());
        $this->assertStringNotContainsString('retention period', implode("\n", $service->buildStatus(false)['warnings']));

        config(['retention.enabled' => true]);
        app(SystemLogService::class)->record('retention.enforced', 'Ran.', ['category' => 'system']);
        $this->assertStringNotContainsString('retention period', $advisories());
    }
}
