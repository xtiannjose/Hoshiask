<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketRepliesFeedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_replies_feed_excludes_internal_replies(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();
        $shadow = $this->createUser('Shadow User', 'shadow-feed-client@example.com', User::ROLE_SHADOW, 'Hoshishi');

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client update',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Public support reply',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow public reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($client)->getJson(route('client.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->every(fn (array $reply) => ! $reply['is_internal']));
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['from_support'] === true));
        $this->assertFalse(collect($replies)->contains(fn (array $reply) => $reply['message'] === 'Shadow public reply'));
    }

    public function test_super_user_replies_feed_includes_internal_replies(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client reply',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($superUser)->getJson(route('admin.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['is_internal'] === true));
    }

    public function test_non_shadow_admin_replies_feed_hides_shadow_replies(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();
        $shadow = $this->createUser('Shadow User', 'shadow-feed-admin@example.com', User::ROLE_SHADOW, 'Hoshishi');

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client reply',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Internal support note',
            'is_internal' => true,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow private support note',
            'is_internal' => true,
        ]);

        $response = $this->actingAs($superUser)->getJson(route('admin.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertFalse(collect($replies)->contains(fn (array $reply) => $reply['message'] === 'Shadow private support note'));
    }

    public function test_shadow_replies_feed_includes_shadow_replies_for_shadow_viewer(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();
        $shadow = $this->createUser('Shadow User', 'shadow-feed-shadow@example.com', User::ROLE_SHADOW, 'Hoshishi');

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client reply',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $shadow->id,
            'message' => 'Shadow private support note',
            'is_internal' => true,
        ]);

        $response = $this->actingAs($shadow)->getJson(route('admin.tickets.replies.feed', $ticket));
        $response->assertOk();

        $replies = $response->json('replies');
        $this->assertCount(2, $replies);
        $this->assertTrue(collect($replies)->contains(fn (array $reply) => $reply['message'] === 'Shadow private support note'));
    }

    public function test_replies_feed_uses_trimmed_reply_payload_contract(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();

        $parentReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Original support message',
            'is_internal' => false,
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client follow-up',
            'reply_to_id' => $parentReply->id,
            'is_internal' => false,
        ]);

        $response = $this->actingAs($client)->getJson(route('client.tickets.replies.feed', $ticket));
        $response->assertOk();

        $reply = collect($response->json('replies'))
            ->first(fn (array $item) => $item['message'] === 'Client follow-up');

        $this->assertIsArray($reply);
        $this->assertSame('Original support message', $reply['reply_to_text']);
        $this->assertArrayNotHasKey('reply_to_message', $reply);
        $this->assertArrayNotHasKey('reply_to_excerpt', $reply);
        $this->assertArrayNotHasKey('created_at_human', $reply);
        $this->assertArrayNotHasKey('created_at_label', $reply);
    }

    public function test_deleted_reply_payload_omits_attachments(): void
    {
        [$client, , $ticket] = $this->seedTicketWithUsers();
        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'This message was deleted.',
            'is_internal' => false,
            'deleted_at' => now(),
        ]);
        Attachment::create([
            'filename' => 'deleted-attachment.txt',
            'original_filename' => 'deleted-attachment.txt',
            'file_path' => 'attachments/deleted-attachment.txt',
            'mime_type' => 'text/plain',
            'file_size' => 10,
            'attachable_type' => TicketReply::class,
            'attachable_id' => $reply->id,
        ]);

        $response = $this->actingAs($client)->getJson(route('client.tickets.replies.feed', $ticket));
        $response->assertOk();

        $payload = collect($response->json('replies'))
            ->first(fn (array $item) => (int) $item['id'] === (int) $reply->id);

        $this->assertIsArray($payload);
        $this->assertTrue($payload['deleted']);
        $this->assertSame([], $payload['attachments']);
    }

    public function test_replies_feed_can_return_only_recent_changes_with_cursor(): void
    {
        [$client, $superUser, $ticket] = $this->seedTicketWithUsers();

        $olderReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $superUser->id,
            'message' => 'Older support reply',
            'is_internal' => false,
        ]);
        $olderReply->forceFill([
            'created_at' => Carbon::parse('2026-04-13 08:00:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-04-13 08:00:00', 'UTC'),
        ])->saveQuietly();

        $newerReply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Newest reply',
            'is_internal' => false,
        ]);
        $newerReply->forceFill([
            'created_at' => Carbon::parse('2026-04-13 08:05:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-04-13 08:05:00', 'UTC'),
        ])->saveQuietly();

        $olderCursor = $olderReply->fresh()->updated_at->copy()->utc();
        $newerCursor = $newerReply->fresh()->updated_at->copy()->utc();

        $response = $this->actingAs($client)->getJson(route('client.tickets.replies.feed', [
            'ticket' => $ticket,
            'updated_after' => $olderCursor->copy()->addMinutes(4)->toIso8601String(),
        ]));
        $response->assertOk();

        $response->assertJsonPath('cursor', $newerCursor->toIso8601String());
        $replies = $response->json('replies');
        $this->assertCount(1, $replies);
        $this->assertSame('Newest reply', $replies[0]['message']);
    }

    private function seedTicketWithUsers(): array
    {
        $client = $this->createUser('Client User', 'client-feed@example.com', User::ROLE_CLIENT, 'Hoshishi');
        $superUser = $this->createUser('Super User', 'super-user-feed@example.com', User::ROLE_SUPER_USER, 'Hoshishi');

        $category = Category::create([
            'name' => 'General',
            'description' => 'General concerns',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester Name',
            'contact_number' => '09120000000',
            'email' => 'requester@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$client, $superUser, $ticket];
    }

    private function createUser(string $name, string $email, string $role, string $department): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09123450000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}
