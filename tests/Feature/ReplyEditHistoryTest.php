<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Models\SystemLog;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ReplyEditHistoryTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
    }

    public function test_an_edited_staff_reply_keeps_its_old_text_in_the_system_logs_only(): void
    {
        $staff = $this->createUser('History Staff', 'history-staff@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('History Client', 'history-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());
        $reply = TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $staff->id, 'message' => 'We will fix it by Friday.', 'is_internal' => false]);

        $response = $this->actingAs($staff)->patchJson(route('admin.tickets.replies.update', [$ticket, $reply]), ['message' => 'We will fix it next week.']);

        $response->assertOk();
        $this->assertStringNotContainsString('Friday', $response->getContent(), 'the edit response must not carry the old text');

        $entry = SystemLog::query()->where('event_type', 'ticket.reply.updated')->latest('id')->firstOrFail();
        $this->assertSame('We will fix it by Friday.', $entry->metadata['message_before']);
        $this->assertSame($reply->id, $entry->metadata['reply_id']);

        // The client's conversation never shows it.
        $feed = $this->actingAs($client)->getJson(route('client.tickets.replies.feed', $ticket));
        $feed->assertOk();
        $this->assertStringNotContainsString('Friday', $feed->getContent());
        $this->assertStringContainsString('next week', $feed->getContent());
    }

    public function test_a_deleted_reply_keeps_its_old_text_in_the_system_logs(): void
    {
        $client = $this->createClient('Delete History Client', 'delete-history-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());
        $reply = TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $client->id, 'message' => 'I typed the wrong thing.', 'is_internal' => false]);

        $this->actingAs($client)->deleteJson(route('client.tickets.replies.delete', [$ticket, $reply]))->assertOk();

        $entry = SystemLog::query()->where('event_type', 'ticket.reply.deleted')->latest('id')->firstOrFail();
        $this->assertSame('I typed the wrong thing.', $entry->metadata['message_before']);
        $this->assertSame('This message was deleted.', $reply->fresh()->message);
    }

    public function test_only_the_shadow_account_can_open_the_system_logs_that_hold_the_old_text(): void
    {
        $admin = $this->createUser('Log Admin', 'log-admin@example.com', User::ROLE_ADMIN);
        $shadow = $this->createUser('Log Shadow', 'log-shadow@example.com', User::ROLE_SHADOW);
        $unlocked = [EnsureSystemLogsUnlocked::SESSION_KEY => now()->addHour()->toIso8601String()];

        $this->actingAs($admin)->withSession($unlocked)->get(route('admin.system-logs.index'))->assertForbidden();
        $this->actingAs($shadow)->withSession($unlocked)->get(route('admin.system-logs.index'))->assertOk();
    }

    public function test_an_edit_that_changes_nothing_is_not_logged_as_history(): void
    {
        $client = $this->createClient('Same Text Client', 'same-text-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());
        $reply = TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $client->id, 'message' => 'Unchanged words.', 'is_internal' => false]);

        $this->actingAs($client)->patchJson(route('client.tickets.replies.update', [$ticket, $reply]), ['message' => 'Unchanged words.'])->assertOk();

        $entry = SystemLog::query()->where('event_type', 'ticket.reply.updated')->latest('id')->first();
        $this->assertTrue($entry === null || ! array_key_exists('message_before', $entry->metadata));
    }
}
