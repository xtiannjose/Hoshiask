<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentDownloadAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_download_internal_reply_attachment_even_for_owned_ticket(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketContext();
        $internalReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);
        $attachment = $this->createReplyAttachment($internalReply, 'internal-note.txt', 'internal note');

        $response = $this->actingAs($client)
            ->get(route('attachments.download', $attachment));

        // A client is told "not found", the same as for a file that does not exist (Q-42).
        $response->assertNotFound();
    }

    public function test_super_user_can_download_internal_reply_attachment(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $internalReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);
        $attachment = $this->createReplyAttachment($internalReply, 'internal-visible.txt', 'internal visible');

        $response = $this->actingAs($superUser)
            ->get(route('attachments.download', $attachment));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
    }

    public function test_non_shadow_admin_cannot_download_shadow_reply_attachment(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $shadow = $this->createSupportUser('Attachment Shadow', 'attachment-shadow@example.com', User::ROLE_SHADOW);
        $shadowReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow-only attachment note',
            'is_internal' => true,
        ]);
        $attachment = $this->createReplyAttachment($shadowReply, 'shadow-note.txt', 'shadow note');

        $response = $this->actingAs($superUser)
            ->get(route('attachments.download', $attachment));

        $response->assertForbidden();
    }

    public function test_shadow_user_can_download_shadow_reply_attachment(): void
    {
        [,, $ticket] = $this->seedTicketContext();
        $shadow = $this->createSupportUser('Attachment Shadow Viewer', 'attachment-shadow-viewer@example.com', User::ROLE_SHADOW);
        $shadowReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow-only attachment note',
            'is_internal' => true,
        ]);
        $attachment = $this->createReplyAttachment($shadowReply, 'shadow-visible.txt', 'shadow visible');

        $response = $this->actingAs($shadow)
            ->get(route('attachments.download', $attachment));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
    }

    public function test_deleted_reply_attachment_cannot_be_downloaded(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'This message was deleted.',
            'is_internal' => false,
            'deleted_at' => now(),
        ]);
        $attachment = $this->createReplyAttachment($reply, 'deleted-reply.txt', 'deleted content');

        $response = $this->actingAs($superUser)
            ->get(route('attachments.download', $attachment));

        $response->assertForbidden();
    }

    public function test_technical_requester_can_download_closed_internal_ticket_attachments(): void
    {
        Storage::fake('local');
        config()->set('hoshiask.attachments_disk', 'local');

        $technicalRequester = $this->createSupportUser('Internal Requester', 'internal-requester-attachments@example.com', User::ROLE_TECHNICAL);
        $assignedTechnical = $this->createSupportUser('Assigned Technical', 'assigned-technical-attachments@example.com', User::ROLE_TECHNICAL);
        $category = Category::create([
            'name' => 'Internal Attachment',
            'description' => 'Internal attachment category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
        $ticket = Ticket::create([
            'name' => 'Internal Requester',
            'contact_number' => '09123450004',
            'email' => 'internal-requester-attachments@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig City',
            'subject' => 'Closed internal attachment visibility',
            'description' => 'Closed internal requester can inspect attachments.',
            'priority' => 'medium',
            'status' => 'closed',
            'ticket_type' => Ticket::TYPE_INTERNAL,
            'user_id' => $technicalRequester->id,
            'assigned_to' => $assignedTechnical->id,
            'category_id' => $category->id,
            'resolved_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
        ]);

        Storage::disk('local')->put('attachments/internal-ticket.txt', 'ticket file');
        $ticketAttachment = Attachment::create([
            'filename' => 'internal-ticket.txt',
            'original_filename' => 'internal-ticket.txt',
            'file_path' => 'attachments/internal-ticket.txt',
            'mime_type' => 'text/plain',
            'file_size' => 11,
            'attachable_type' => Ticket::class,
            'attachable_id' => $ticket->id,
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignedTechnical->id,
            'message' => 'Internal resolution attachment.',
            'is_internal' => true,
        ]);
        $replyAttachment = $this->createReplyAttachment($reply, 'internal-reply.txt', 'reply file');

        $this->actingAs($technicalRequester)
            ->get(route('attachments.download', $ticketAttachment))
            ->assertOk();
        $this->actingAs($technicalRequester)
            ->get(route('attachments.download', $replyAttachment))
            ->assertOk();
    }

    public function test_a_missing_file_is_not_written_to_the_audit_trail_as_downloaded(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Reply whose file has gone missing',
            'is_internal' => false,
        ]);
        $attachment = $this->createReplyAttachment($reply, 'gone.txt', 'gone');
        Storage::disk('local')->delete($attachment->file_path);

        $this->actingAs($superUser)
            ->get(route('attachments.download', $attachment))
            ->assertNotFound();

        $this->assertDatabaseMissing('system_logs', ['event_type' => 'attachment.downloaded']);
    }

    public function test_a_delivered_download_is_written_to_the_audit_trail(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Reply with a file',
            'is_internal' => false,
        ]);
        $attachment = $this->createReplyAttachment($reply, 'delivered.txt', 'delivered');

        $this->actingAs($superUser)
            ->get(route('attachments.download', $attachment))
            ->assertOk();

        $this->assertDatabaseHas('system_logs', [
            'event_type' => 'attachment.downloaded',
            'target_id' => $attachment->id,
        ]);
    }

    public function test_the_same_preview_is_written_to_the_audit_trail_once_an_hour(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $attachment = $this->createReplyAttachment($this->publicReply($ticket, $superUser), 'photo.txt', 'photo');

        foreach (range(1, 3) as $view) {
            $this->actingAs($superUser)->get($this->previewUrl($attachment))->assertOk();
        }

        $rows = $this->auditRowsFor($attachment);
        $this->assertCount(1, $rows);
        $this->assertTrue($rows->first()->metadata['preview']);

        $this->travel(59)->minutes();
        $this->actingAs($superUser)->get($this->previewUrl($attachment))->assertOk();
        $this->assertCount(1, $this->auditRowsFor($attachment), 'Still inside the hour.');

        $this->travel(2)->minutes();
        $this->actingAs($superUser)->get($this->previewUrl($attachment))->assertOk();
        $this->assertCount(2, $this->auditRowsFor($attachment), 'An hour has passed, so the next preview is written again.');
    }

    public function test_each_person_and_each_file_gets_their_own_preview_row(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $secondViewer = $this->createSupportUser('Second Viewer', 'second-viewer@example.com', User::ROLE_ADMIN);
        $reply = $this->publicReply($ticket, $superUser);
        $first = $this->createReplyAttachment($reply, 'first.txt', 'first');
        $second = $this->createReplyAttachment($reply, 'second.txt', 'second');

        $this->actingAs($superUser)->get($this->previewUrl($first))->assertOk();
        $this->actingAs($superUser)->get($this->previewUrl($second))->assertOk();
        $this->actingAs($secondViewer)->get($this->previewUrl($first))->assertOk();

        $this->assertCount(1, $this->auditRowsFor($first, $superUser));
        $this->assertCount(1, $this->auditRowsFor($second, $superUser));
        $this->assertCount(1, $this->auditRowsFor($first, $secondViewer));
    }

    public function test_a_real_download_is_always_written_and_never_counts_as_the_hourly_preview(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $attachment = $this->createReplyAttachment($this->publicReply($ticket, $superUser), 'report.txt', 'report');

        $this->actingAs($superUser)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($superUser)->get($this->previewUrl($attachment))->assertOk();
        $this->actingAs($superUser)->get($this->previewUrl($attachment))->assertOk();
        $this->actingAs($superUser)->get(route('attachments.download', $attachment))->assertOk();

        $this->assertSame(
            [false, true, false],
            $this->auditRowsFor($attachment)->map(fn (SystemLog $row): bool => (bool) $row->metadata['preview'])->all()
        );
    }

    public function test_preview_rejects_unsupported_mime_types(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Reply with unsupported preview attachment',
            'is_internal' => false,
        ]);

        $attachment = $this->createReplyAttachment($reply, 'unsupported.bin', "\x00\x01\x02\x03");
        $attachment->update(['mime_type' => 'application/octet-stream']);
        $this->assertSame('application/octet-stream', (string) $attachment->fresh()->mime_type);

        $response = $this->actingAs($superUser)
            ->getJson(route('attachments.download', [
                'attachment' => $attachment,
                'preview' => 1,
            ]));

        $response->assertStatus(415);
        $this->assertDatabaseMissing('system_logs', ['event_type' => 'attachment.downloaded']);
    }

    public function test_preview_rejects_svg_even_if_legacy_attachment_record_exists(): void
    {
        [, $superUser, $ticket] = $this->seedTicketContext();
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Reply with legacy svg attachment',
            'is_internal' => false,
        ]);

        $attachment = $this->createReplyAttachment($reply, 'legacy.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $attachment->update(['mime_type' => 'image/svg+xml']);

        $response = $this->actingAs($superUser)
            ->getJson(route('attachments.download', [
                'attachment' => $attachment,
                'preview' => 1,
            ]));

        $response->assertStatus(415);
        $this->assertNull($attachment->fresh()->preview_url);
    }

    private function seedTicketContext(): array
    {
        Storage::fake('local');
        Storage::fake('public');
        config()->set('hoshiask.attachments_disk', 'local');

        $client = User::create([
            'name' => 'Attachment Client',
            'email' => 'attachment-client@example.com',
            'phone' => '09123450001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superUser = User::create([
            'name' => 'Attachment Super User',
            'email' => 'attachment-super-user@example.com',
            'phone' => '09123450002',
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
            'name' => 'Attachment Requester',
            'contact_number' => '09120000001',
            'email' => 'attachment-requester@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig City',
            'subject' => 'Attachment visibility test',
            'description' => 'Attachment visibility test ticket.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$client, $superUser, $ticket];
    }

    private function createSupportUser(string $name, string $email, string $role): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09123450003',
            'department' => 'Hoshishi',
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function publicReply(Ticket $ticket, User $author): TicketReply
    {
        return TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'message' => 'Reply that carries a file',
            'is_internal' => false,
        ]);
    }

    private function previewUrl(Attachment $attachment): string
    {
        return route('attachments.download', ['attachment' => $attachment, 'preview' => 1]);
    }

    /**
     * The audit trail rows for one file, oldest first, optionally only those written for one person.
     *
     * @return Collection<int, SystemLog>
     */
    private function auditRowsFor(Attachment $attachment, ?User $actor = null): Collection
    {
        return SystemLog::query()
            ->where('event_type', 'attachment.downloaded')
            ->where('target_id', $attachment->id)
            ->when($actor, fn ($query) => $query->where('actor_user_id', $actor->id))
            ->orderBy('id')
            ->get();
    }

    private function createReplyAttachment(TicketReply $reply, string $filename, string $contents): Attachment
    {
        $filePath = 'attachments/'.$filename;
        Storage::disk('local')->put($filePath, $contents);

        return Attachment::create([
            'filename' => $filename,
            'original_filename' => $filename,
            'file_path' => $filePath,
            'mime_type' => 'text/plain',
            'file_size' => strlen($contents),
            'attachable_type' => TicketReply::class,
            'attachable_id' => $reply->id,
        ]);
    }
}
