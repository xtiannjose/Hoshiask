<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Technicians can open (read-only) any client ticket, for example from a link a colleague shared while
 * consulting. They still cannot change it, and their own lists, counts and notifications are unchanged.
 */
class TechnicianReadOnlyViewTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $owner;

    private User $viewer;

    private User $client;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createAssignedSupportUser('Owner Tech', 'owner-tech-ro@example.com');
        $this->viewer = $this->createAssignedSupportUser('Viewer Tech', 'viewer-tech-ro@example.com');
        $this->client = $this->createClient('Ticket Client', 'ticket-client-ro@example.com');
        $this->ticket = $this->createTicket($this->client, $this->createCategory(), [
            'subject' => 'Printer on the third floor',
            'status' => 'in_progress',
            'assigned_to' => $this->owner->id,
        ]);
        $this->ticket->assignedUsers()->sync([$this->owner->id]);
    }

    public function test_a_technician_can_open_a_colleagues_client_ticket_and_sees_a_read_only_notice(): void
    {
        $this->actingAs($this->viewer)->get(route('admin.tickets.show', $this->ticket))
            ->assertOk()
            ->assertSee('Printer on the third floor')
            ->assertSee('data-read-only-notice', false)
            ->assertSee('Read-only view')
            ->assertSee('This ticket is assigned to Owner Tech.')
            ->assertSee('You are viewing this ticket read-only');
    }

    public function test_the_read_only_page_has_no_reply_box_and_no_change_forms(): void
    {
        $html = $this->actingAs($this->viewer)->get(route('admin.tickets.show', $this->ticket))->assertOk()->getContent();

        foreach (['admin-ticket-reply-form', route('admin.tickets.status', $this->ticket), route('admin.tickets.assign', $this->ticket),
            route('admin.tickets.severity', $this->ticket), route('admin.tickets.escalate', $this->ticket),
            route('admin.tickets.acknowledge', $this->ticket)] as $mustNotAppear) {
            $this->assertStringNotContainsString($mustNotAppear, $html, $mustNotAppear);
        }
    }

    public function test_the_assigned_technician_still_gets_the_full_page_without_the_notice(): void
    {
        $this->actingAs($this->owner)->get(route('admin.tickets.show', $this->ticket))
            ->assertOk()
            ->assertDontSee('data-read-only-notice', false)
            ->assertSee('admin-ticket-reply-form', false)
            ->assertSee(route('admin.tickets.status', $this->ticket), false);
    }

    public function test_a_viewing_technician_cannot_change_anything(): void
    {
        $actions = [
            ['post', route('admin.tickets.reply', $this->ticket), ['message' => 'I should not be able to post this']],
            ['post', route('admin.tickets.status', $this->ticket), ['status' => 'resolved']],
            ['post', route('admin.tickets.assign', $this->ticket), ['assigned_to' => [$this->viewer->id]]],
            ['post', route('admin.tickets.severity', $this->ticket), ['severity' => 'severity_1']],
            ['post', route('admin.tickets.escalate', $this->ticket), ['escalated_to' => $this->owner->id, 'reason' => 'Because']],
            ['post', route('admin.tickets.acknowledge', $this->ticket), []],
            ['delete', route('admin.tickets.destroy', $this->ticket), []],
        ];

        foreach ($actions as [$method, $url, $payload]) {
            $this->actingAs($this->viewer)->{$method}($url, $payload)->assertForbidden();
        }

        $fresh = $this->ticket->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame((int) $this->owner->id, (int) $fresh->assigned_to);
        $this->assertNotSame('severity_1', $fresh->priority);
        $this->assertSame(0, TicketReply::query()->where('ticket_id', $this->ticket->id)->count());
    }

    public function test_a_viewing_technician_cannot_edit_or_delete_someone_elses_reply(): void
    {
        $reply = TicketReply::create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->owner->id,
            'message' => 'Original message',
            'is_internal' => false,
        ]);

        $this->actingAs($this->viewer)
            ->patch(route('admin.tickets.replies.update', ['ticket' => $this->ticket, 'reply' => $reply]), ['message' => 'Edited by a viewer'])
            ->assertForbidden();
        $this->actingAs($this->viewer)
            ->delete(route('admin.tickets.replies.delete', ['ticket' => $this->ticket, 'reply' => $reply]))
            ->assertForbidden();

        $this->assertSame('Original message', $reply->fresh()->message);
    }

    public function test_the_conversation_and_attachments_can_be_read(): void
    {
        Storage::fake('local');
        config()->set('hoshiask.attachments_disk', 'local');
        TicketReply::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->client->id, 'message' => 'It prints blank pages', 'is_internal' => false]);
        Storage::disk('local')->put('attachments/blank.txt', 'sample');
        $attachment = Attachment::create([
            'filename' => 'blank.txt',
            'original_filename' => 'blank.txt',
            'file_path' => 'attachments/blank.txt',
            'mime_type' => 'text/plain',
            'file_size' => 6,
            'attachable_type' => Ticket::class,
            'attachable_id' => $this->ticket->id,
        ]);

        $this->actingAs($this->viewer)->get(route('admin.tickets.show', $this->ticket))->assertOk()->assertSee('It prints blank pages');
        $this->actingAs($this->viewer)->getJson(route('admin.tickets.replies.feed', $this->ticket))->assertOk();
        $this->actingAs($this->viewer)->get(route('attachments.download', $attachment))->assertOk();
    }

    public function test_the_technicians_own_lists_and_counts_still_show_only_their_own_tickets(): void
    {
        $mine = $this->createTicket($this->client, $this->createCategory('Mine'), ['subject' => 'My own ticket', 'assigned_to' => $this->viewer->id]);
        $mine->assignedUsers()->sync([$this->viewer->id]);

        $listed = $this->actingAs($this->viewer)->get(route('admin.tickets.index', ['tab' => 'all']))->assertOk()
            ->viewData('tickets')->pluck('ticket_number')->all();

        $this->assertContains($mine->ticket_number, $listed);
        $this->assertNotContains($this->ticket->ticket_number, $listed);
    }

    public function test_staff_to_staff_requests_stay_limited_to_the_people_involved(): void
    {
        $requester = $this->createAssignedSupportUser('Requester Tech', 'requester-tech-ro@example.com');
        $internal = $this->createTicket($requester, $this->createCategory('Internal'), [
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'assigned_to' => $this->owner->id,
        ]);
        $internal->assignedUsers()->sync([$this->owner->id]);

        $this->actingAs($this->viewer)->get(route('admin.tickets.show', $internal))
            ->assertForbidden()
            ->assertSee('This ticket is assigned to someone else');
        $this->actingAs($this->owner)->get(route('admin.tickets.show', $internal))->assertOk();
    }

    public function test_supervisors_can_still_open_and_change_any_ticket(): void
    {
        $superUser = $this->createSupportUser('Supervisor', 'supervisor-ro@example.com');

        $this->actingAs($superUser)->get(route('admin.tickets.show', $this->ticket))
            ->assertOk()
            ->assertDontSee('data-read-only-notice', false);
        $this->actingAs($superUser)->post(route('admin.tickets.severity', $this->ticket), ['severity' => 'severity_2'])
            ->assertSessionHasNoErrors();
    }

    public function test_clients_are_unaffected_and_still_see_not_found_for_other_peoples_tickets(): void
    {
        $other = $this->createClient('Other Client', 'other-client-ro@example.com');

        $this->actingAs($other)->get(route('client.tickets.show', $this->ticket))->assertNotFound();
        $this->actingAs($other)->get(route('admin.tickets.show', $this->ticket))->assertStatus(403);
    }

    public function test_a_read_only_view_is_recorded_in_the_system_logs_but_a_normal_view_is_not(): void
    {
        $this->actingAs($this->owner)->get(route('admin.tickets.show', $this->ticket))->assertOk();
        $this->assertSame(0, SystemLog::query()->where('event_type', 'ticket.viewed_read_only')->count());

        $this->actingAs($this->viewer)->get(route('admin.tickets.show', $this->ticket))->assertOk();

        $log = SystemLog::query()->where('event_type', 'ticket.viewed_read_only')->firstOrFail();
        $this->assertSame((int) $this->viewer->id, (int) $log->actor_user_id);
        $this->assertSame((int) $this->ticket->id, (int) $log->target_id);
        $this->assertSame($this->ticket->ticket_number, $log->metadata['ticket_number'] ?? null);
    }

    public function test_opening_a_ticket_read_only_does_not_acknowledge_it_or_touch_its_workflow(): void
    {
        $before = $this->ticket->fresh();

        $this->actingAs($this->viewer)->get(route('admin.tickets.show', $this->ticket))->assertOk();

        $after = $this->ticket->fresh();
        $this->assertEquals($before->updated_at, $after->updated_at);
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->assigned_to, $after->assigned_to);
    }
}
