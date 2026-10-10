<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketReplyAttachmentOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_send_reply_with_attachment_only(): void
    {
        Storage::fake('local');
        [$client, $ticket] = $this->seedClientAndTicket();
        $image = UploadedFile::fake()->create('evidence.png', 128, 'image/png');

        $response = $this->actingAs($client)
            ->from(route('client.tickets.show', $ticket))
            ->post(route('client.tickets.reply', $ticket), [
                'attachments' => [$image],
            ]);

        $response->assertRedirect(route('client.tickets.show', $ticket));

        $reply = TicketReply::where('ticket_id', $ticket->id)
            ->where('user_id', $client->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($reply);
        $this->assertSame('', (string) $reply->message);
        $this->assertDatabaseHas('attachments', [
            'attachable_type' => TicketReply::class,
            'attachable_id' => $reply->id,
        ]);
    }

    private function seedClientAndTicket(): array
    {
        $client = User::create([
            'name' => 'Attachment Reply Client',
            'email' => 'attachment-reply-client@example.com',
            'phone' => '09125550001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Attachment Reply Category',
            'description' => 'Attachment-only reply tests',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Attachment Reply Requester',
            'contact_number' => '09125550002',
            'email' => 'attachment-reply-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Attachment-only reply test',
            'description' => 'Checking attachment-only replies.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$client, $ticket];
    }
}
