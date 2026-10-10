<?php

namespace Tests\Unit;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\Admin\TicketMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketMutationServiceTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function service(): TicketMutationService
    {
        return app(TicketMutationService::class);
    }

    public function test_merge_reparents_replies_and_attachments_and_closes_the_other_ticket(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $technical = $this->createAssignedSupportUser();
        $admin = $this->createSupportUser();

        $primary = $this->createTicket($client, $category);
        $other = $this->createTicket($client, $category);
        $other->assignedUsers()->sync([$technical->id]);
        $other->update(['assigned_to' => $technical->id]);

        $reply = TicketReply::create([
            'ticket_id' => $other->id,
            'user_id' => $client->id,
            'message' => 'Original ticket reply',
            'is_internal' => false,
        ]);
        $attachment = Attachment::create([
            'attachable_type' => Ticket::class,
            'attachable_id' => $other->id,
            'filename' => 'test.txt',
            'original_filename' => 'test.txt',
            'file_path' => 'attachments/test.txt',
            'mime_type' => 'text/plain',
            'file_size' => 10,
        ]);

        $this->service()->mergeTickets($primary, collect([$other]), $admin->id);

        $reply->refresh();
        $attachment->refresh();
        $other->refresh();
        $primary->refresh();

        $this->assertSame($primary->id, $reply->ticket_id);
        $this->assertSame($primary->id, $attachment->attachable_id);
        $this->assertSame('closed', $other->status);
        $this->assertNotNull($other->closed_at);
        $this->assertSame($admin->id, $other->closed_by);
        $this->assertContains($technical->id, $primary->assigned_user_ids);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $primary->id,
            'message' => "Merged ticket {$other->ticket_number}: {$other->subject}",
            'is_internal' => true,
        ]);
    }

    public function test_merging_a_stale_other_ticket_reference_does_not_clobber_its_true_resolved_at(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $admin = $this->createSupportUser();

        $primary = $this->createTicket($client, $category);
        $other = $this->createTicket($client, $category, ['status' => 'open']);

        // Someone else fetches the same ticket before it gets resolved elsewhere.
        $staleOther = Ticket::find($other->id);

        // A concurrent request resolves it for real, in between the stale fetch and the merge below.
        // (Truncated to whole seconds since that's what round-trips through the DB column.)
        $trueResolvedAt = Carbon::now()->subMinutes(30)->startOfSecond();
        Ticket::whereKey($other->id)->update([
            'status' => 'resolved',
            'resolved_at' => $trueResolvedAt,
        ]);

        $this->service()->mergeTickets($primary, collect([$staleOther]), $admin->id);

        $other->refresh();
        $this->assertTrue(
            $other->resolved_at->equalTo($trueResolvedAt),
            'The true resolved_at set by the concurrent update must survive the merge, not be overwritten by a stale in-memory null.'
        );
    }

    public function test_merge_combines_assignments_from_all_merged_tickets(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $admin = $this->createSupportUser();
        $technicalOne = $this->createAssignedSupportUser('Technical One', 'technical-one@example.com');
        $technicalTwo = $this->createAssignedSupportUser('Technical Two', 'technical-two@example.com');

        $primary = $this->createTicket($client, $category);
        $primary->assignedUsers()->sync([$technicalOne->id]);
        $primary->update(['assigned_to' => $technicalOne->id]);

        $other = $this->createTicket($client, $category);
        $other->assignedUsers()->sync([$technicalTwo->id]);
        $other->update(['assigned_to' => $technicalTwo->id]);

        $this->service()->mergeTickets($primary, collect([$other]), $admin->id);

        $primary->refresh();
        $this->assertEqualsCanonicalizing([$technicalOne->id, $technicalTwo->id], $primary->assigned_user_ids);
    }
}
