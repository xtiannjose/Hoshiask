<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\HeaderNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HeaderNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notifications_use_latest_client_reply_for_each_ticket(): void
    {
        [$client, $technical, $ticket] = $this->seedNotificationContext();

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $technical->id,
            'message' => 'Support follow-up that should not drive the admin notification.',
            'is_internal' => false,
            'created_at' => Carbon::parse('2026-04-09 10:00:00'),
            'updated_at' => Carbon::parse('2026-04-09 10:00:00'),
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client update that should appear in the notification preview.',
            'is_internal' => false,
            'created_at' => Carbon::parse('2026-04-09 10:05:00'),
            'updated_at' => Carbon::parse('2026-04-09 10:05:00'),
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $technical->id,
            'message' => 'Later support message that should not replace the client preview.',
            'is_internal' => false,
            'created_at' => Carbon::parse('2026-04-09 10:10:00'),
            'updated_at' => Carbon::parse('2026-04-09 10:10:00'),
        ]);

        $payload = app(HeaderNotificationService::class)->payloadFor($technical);

        $this->assertSame(1, $payload['unread_count']);
        $this->assertCount(1, $payload['notifications']);
        $this->assertSame('New client message', $payload['notifications'][0]['title']);
        $this->assertStringContainsString('Client User', $payload['notifications'][0]['meta']);
        $this->assertStringContainsString('Client update that should appear', $payload['notifications'][0]['meta']);
        $this->assertStringNotContainsString('Later support message', $payload['notifications'][0]['meta']);
    }

    public function test_client_notifications_use_latest_support_reply_even_if_client_replied_later(): void
    {
        [$client, $technical, $ticket] = $this->seedNotificationContext();

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $technical->id,
            'message' => 'Technical response that should remain the client notification preview.',
            'is_internal' => false,
            'created_at' => Carbon::parse('2026-04-09 11:00:00'),
            'updated_at' => Carbon::parse('2026-04-09 11:00:00'),
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Client follow-up that should not hide the latest support update.',
            'is_internal' => false,
            'created_at' => Carbon::parse('2026-04-09 11:05:00'),
            'updated_at' => Carbon::parse('2026-04-09 11:05:00'),
        ]);

        $payload = app(HeaderNotificationService::class)->payloadFor($client);

        $this->assertSame(1, $payload['unread_count']);
        $this->assertCount(1, $payload['notifications']);
        $this->assertSame('New technical message', $payload['notifications'][0]['title']);
        $this->assertStringContainsString('Technical User', $payload['notifications'][0]['meta']);
        $this->assertStringContainsString('Technical response that should remain', $payload['notifications'][0]['meta']);
        $this->assertStringNotContainsString('Client follow-up', $payload['notifications'][0]['meta']);
    }

    public function test_staff_header_cache_is_reused_until_a_ticket_changes(): void
    {
        [$client, $technical, $ticket] = $this->seedNotificationContext();
        $service = app(HeaderNotificationService::class);

        $first = $service->payloadFor($technical);

        // A write that skips model events must not show up yet: the cached payload is reused.
        TicketReply::withoutEvents(fn () => TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Unannounced client message.',
            'is_internal' => false,
        ]));
        $this->assertSame($first, $service->payloadFor($technical));

        // A normal save bumps the shared staff generation, so the next read is rebuilt.
        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Announced client message.',
            'is_internal' => false,
        ]);
        $refreshed = $service->payloadFor($technical);

        $this->assertNotSame($first, $refreshed);
        $this->assertSame('New client message', $refreshed['notifications'][0]['title']);
        $this->assertStringContainsString('Announced client message.', $refreshed['notifications'][0]['meta']);
    }

    private function seedNotificationContext(): array
    {
        $client = User::create([
            'name' => 'Client User',
            'email' => 'header-notify-client@example.test',
            'phone' => '09170001001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Technical User',
            'email' => 'header-notify-technical@example.test',
            'phone' => '09170001002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'description' => 'General category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09170001003',
            'email' => 'requester-header@example.test',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Header notification optimization test',
            'description' => 'Used to verify latest counterpart reply selection.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
            'created_at' => Carbon::parse('2026-04-09 09:00:00'),
            'updated_at' => Carbon::parse('2026-04-09 09:00:00'),
        ]);

        return [$client, $technical, $ticket];
    }
}
