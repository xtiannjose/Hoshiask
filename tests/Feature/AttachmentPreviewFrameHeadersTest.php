<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * A PDF or text attachment opens in a frame inside the ticket page. The site refuses framing everywhere else,
 * so only that one response, and only our own pages, are allowed to frame it.
 */
class AttachmentPreviewFrameHeadersTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false, 'hoshiask.attachments_disk' => 'local']);
        Storage::fake('local');
    }

    private function attachmentOn(Ticket $ticket): Attachment
    {
        Storage::disk('local')->put('attachments/notes.txt', 'Plain notes');

        return Attachment::create([
            'filename' => 'notes.txt',
            'original_filename' => 'notes.txt',
            'file_path' => 'attachments/notes.txt',
            'mime_type' => 'text/plain',
            'file_size' => 11,
            'attachable_type' => Ticket::class,
            'attachable_id' => $ticket->id,
        ]);
    }

    public function test_the_preview_response_may_be_framed_by_our_own_pages_and_nobody_else(): void
    {
        $staff = $this->createUser('Preview Staff', 'preview-staff@example.com', User::ROLE_SUPER_USER);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory());
        $attachment = $this->attachmentOn($ticket);

        $response = $this->actingAs($staff)->get(route('attachments.download', ['attachment' => $attachment, 'preview' => 1]));

        $response->assertOk();
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'self'", $this->contentSecurityPolicy($response));
        $this->assertStringNotContainsString("frame-ancestors 'none'", $this->contentSecurityPolicy($response));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_every_other_response_still_refuses_to_be_framed(): void
    {
        $staff = $this->createUser('Frame Staff', 'frame-staff@example.com', User::ROLE_SUPER_USER);
        $ticket = $this->createTicket($this->createClient('Frame Client', 'frame-client@example.com'), $this->createCategory());
        $attachment = $this->attachmentOn($ticket);

        foreach ([
            $this->actingAs($staff)->get(route('attachments.download', $attachment)),
            $this->actingAs($staff)->get(route('admin.tickets.show', $ticket)),
            $this->get(route('login')),
        ] as $response) {
            $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
            $this->assertStringContainsString("frame-ancestors 'none'", $this->contentSecurityPolicy($response));
        }
    }

    public function test_the_pages_may_embed_their_own_preview_but_nothing_from_elsewhere(): void
    {
        $staff = $this->createUser('Embed Staff', 'embed-staff@example.com', User::ROLE_SUPER_USER);
        $ticket = $this->createTicket($this->createClient('Embed Client', 'embed-client@example.com'), $this->createCategory());

        $policy = $this->contentSecurityPolicy($this->actingAs($staff)->get(route('admin.tickets.show', $ticket)));

        $this->assertStringContainsString("frame-src 'self'", $policy);
        $this->assertStringNotContainsString('frame-src *', $policy);
    }

    private function contentSecurityPolicy(TestResponse $response): string
    {
        return (string) ($response->headers->get('Content-Security-Policy') ?? $response->headers->get('Content-Security-Policy-Report-Only') ?? '');
    }
}
