<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AdminManualTicketDatesTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
    }

    public function test_admin_role_can_now_access_ticket_creation(): void
    {
        $admin = $this->createUser('Manual Dates Admin', 'manual-dates-admin@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('admin.tickets.create'))->assertOk();

        $client = $this->createClient('Manual Dates Client', 'manual-dates-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($admin)->post(route('admin.tickets.store'), $this->baseTicketPayload($client, $category));

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', ['subject' => 'Manual logging test ticket']);
    }

    public function test_admin_sees_manual_dates_section(): void
    {
        $admin = $this->createUser('Section Admin', 'section-admin@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('admin.tickets.create'))
            ->assertOk()
            ->assertSee('Manual Logging Dates');
    }

    public function test_super_user_sees_manual_dates_section(): void
    {
        $superUser = $this->createUser('Section Super', 'section-super@example.com', User::ROLE_SUPER_USER);

        $this->actingAs($superUser)->get(route('admin.tickets.create'))
            ->assertOk()
            ->assertSee('Manual Logging Dates');
    }

    public function test_technical_does_not_see_manual_dates_section(): void
    {
        $technical = $this->createUser('Section Technical', 'section-technical@example.com', User::ROLE_TECHNICAL);

        $this->actingAs($technical)->get(route('admin.tickets.create'))
            ->assertOk()
            ->assertDontSee('Manual Logging Dates');
    }

    public function test_technical_cannot_submit_manual_dates(): void
    {
        $technical = $this->createUser('Blocked Technical', 'blocked-technical@example.com', User::ROLE_TECHNICAL);
        $client = $this->createClient('Blocked Dates Client', 'blocked-dates-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($technical)
            ->from(route('admin.tickets.create'))
            ->post(route('admin.tickets.store'), array_merge(
                $this->baseTicketPayload($client, $category),
                ['created_at' => Carbon::create(2026, 1, 15)->format('Y-m-d\TH:i')]
            ));

        $response->assertRedirect(route('admin.tickets.create'));
        $response->assertSessionHasErrors('created_at');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_technical_can_still_create_a_ticket_without_manual_dates(): void
    {
        $technical = $this->createUser('Unblocked Technical', 'unblocked-technical@example.com', User::ROLE_TECHNICAL);
        $client = $this->createClient('Unblocked Dates Client', 'unblocked-dates-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($technical)->post(route('admin.tickets.store'), $this->baseTicketPayload($client, $category));

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', ['subject' => 'Manual logging test ticket']);
    }

    public function test_omitting_dates_behaves_exactly_as_before(): void
    {
        $superUser = $this->createUser('No Dates Super', 'no-dates-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('No Dates Client', 'no-dates-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($superUser)->post(route('admin.tickets.store'), $this->baseTicketPayload($client, $category));

        $response->assertRedirect();
        $ticket = $client->tickets()->firstOrFail();

        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->resolved_at);
        $this->assertNull($ticket->closed_at);
        $this->assertTrue($ticket->created_at->betweenIncluded(now()->subMinute(), now()->addMinute()));
    }

    public function test_a_backdated_logged_date_is_applied(): void
    {
        $superUser = $this->createUser('Backdate Super', 'backdate-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Backdate Client', 'backdate-client@example.com');
        $category = $this->createCategory();
        $loggedAt = Carbon::create(2026, 1, 15, 9, 30, 0);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.store'), array_merge(
            $this->baseTicketPayload($client, $category),
            ['created_at' => $loggedAt->format('Y-m-d\TH:i')]
        ));

        $response->assertRedirect();
        $ticket = $client->tickets()->firstOrFail();

        $this->assertSame('open', $ticket->status);
        $this->assertTrue($ticket->created_at->equalTo($loggedAt));
    }

    public function test_a_resolved_date_marks_the_ticket_resolved(): void
    {
        $superUser = $this->createUser('Resolved Super', 'resolved-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Resolved Client', 'resolved-client@example.com');
        $category = $this->createCategory();
        $loggedAt = Carbon::create(2026, 1, 10, 8, 0, 0);
        $resolvedAt = Carbon::create(2026, 1, 12, 16, 0, 0);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.store'), array_merge(
            $this->baseTicketPayload($client, $category),
            [
                'created_at' => $loggedAt->format('Y-m-d\TH:i'),
                'resolved_at' => $resolvedAt->format('Y-m-d\TH:i'),
            ]
        ));

        $response->assertRedirect();
        $ticket = $client->tickets()->firstOrFail();

        $this->assertSame('resolved', $ticket->status);
        $this->assertTrue($ticket->created_at->equalTo($loggedAt));
        $this->assertTrue($ticket->resolved_at->equalTo($resolvedAt));
        $this->assertNull($ticket->closed_at);
    }

    public function test_a_closed_date_marks_the_ticket_closed_and_backfills_resolved_at(): void
    {
        $superUser = $this->createUser('Closed Super', 'closed-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Closed Client', 'closed-client@example.com');
        $category = $this->createCategory();
        $loggedAt = Carbon::create(2026, 1, 5, 8, 0, 0);
        $closedAt = Carbon::create(2026, 1, 6, 17, 0, 0);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.store'), array_merge(
            $this->baseTicketPayload($client, $category),
            [
                'created_at' => $loggedAt->format('Y-m-d\TH:i'),
                'closed_at' => $closedAt->format('Y-m-d\TH:i'),
            ]
        ));

        $response->assertRedirect();
        $ticket = $client->tickets()->firstOrFail();

        $this->assertSame('closed', $ticket->status);
        $this->assertTrue($ticket->closed_at->equalTo($closedAt));
        $this->assertTrue($ticket->resolved_at->equalTo($closedAt));
        $this->assertSame($superUser->id, (int) $ticket->closed_by);
    }

    public function test_resolved_date_before_logged_date_is_rejected(): void
    {
        $superUser = $this->createUser('Invalid Order Super', 'invalid-order-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Invalid Order Client', 'invalid-order-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.create'))
            ->post(route('admin.tickets.store'), array_merge(
                $this->baseTicketPayload($client, $category),
                [
                    'created_at' => Carbon::create(2026, 1, 15)->format('Y-m-d\TH:i'),
                    'resolved_at' => Carbon::create(2026, 1, 10)->format('Y-m-d\TH:i'),
                ]
            ));

        $response->assertRedirect(route('admin.tickets.create'));
        $response->assertSessionHasErrors('resolved_at');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_closed_date_before_resolved_date_is_rejected(): void
    {
        $superUser = $this->createUser('Invalid Close Super', 'invalid-close-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Invalid Close Client', 'invalid-close-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.create'))
            ->post(route('admin.tickets.store'), array_merge(
                $this->baseTicketPayload($client, $category),
                [
                    'created_at' => Carbon::create(2026, 1, 5)->format('Y-m-d\TH:i'),
                    'resolved_at' => Carbon::create(2026, 1, 10)->format('Y-m-d\TH:i'),
                    'closed_at' => Carbon::create(2026, 1, 8)->format('Y-m-d\TH:i'),
                ]
            ));

        $response->assertRedirect(route('admin.tickets.create'));
        $response->assertSessionHasErrors('closed_at');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_future_logged_date_is_rejected(): void
    {
        $superUser = $this->createUser('Future Super', 'future-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Future Client', 'future-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.create'))
            ->post(route('admin.tickets.store'), array_merge(
                $this->baseTicketPayload($client, $category),
                ['created_at' => now()->addDay()->format('Y-m-d\TH:i')]
            ));

        $response->assertRedirect(route('admin.tickets.create'));
        $response->assertSessionHasErrors('created_at');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_admin_can_edit_dates_on_an_existing_ticket(): void
    {
        $admin = $this->createUser('Edit Dates Admin', 'edit-dates-admin@example.com', User::ROLE_ADMIN);
        $client = $this->createClient('Edit Dates Client', 'edit-dates-client@example.com');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);
        $loggedAt = Carbon::create(2026, 2, 1, 9, 0, 0);
        $resolvedAt = Carbon::create(2026, 2, 3, 15, 0, 0);

        $response = $this->actingAs($admin)->post(route('admin.tickets.dates', $ticket), [
            'created_at' => $loggedAt->format('Y-m-d\TH:i'),
            'resolved_at' => $resolvedAt->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertTrue($ticket->created_at->equalTo($loggedAt));
        $this->assertTrue($ticket->resolved_at->equalTo($resolvedAt));
    }

    public function test_editing_dates_does_not_change_the_tickets_status(): void
    {
        $superUser = $this->createUser('Status Preserve Super', 'status-preserve-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Status Preserve Client', 'status-preserve-client@example.com');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category, ['status' => 'open']);
        Ticket::query()->whereKey($ticket->id)->update(['created_at' => Carbon::create(2026, 2, 1, 8, 0, 0)]);
        $ticket->refresh();

        $response = $this->actingAs($superUser)->post(route('admin.tickets.dates', $ticket), [
            'created_at' => $ticket->created_at->format('Y-m-d\TH:i'),
            'resolved_at' => Carbon::create(2026, 2, 3, 15, 0, 0)->format('Y-m-d\TH:i'),
            'closed_at' => Carbon::create(2026, 2, 4, 9, 0, 0)->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_shadow_can_edit_the_closed_date_on_an_existing_ticket(): void
    {
        $shadow = $this->createUser('Edit Dates Shadow', 'edit-dates-shadow@example.com', User::ROLE_SHADOW);
        $client = $this->createClient('Edit Dates Shadow Client', 'edit-dates-shadow-client@example.com');
        $category = $this->createCategory();
        $loggedAt = Carbon::create(2026, 2, 1, 9, 0, 0);
        $ticket = $this->createTicket($client, $category, ['status' => 'closed']);
        Ticket::query()->whereKey($ticket->id)->update(['created_at' => $loggedAt]);
        $ticket->refresh();
        $closedAt = Carbon::create(2026, 2, 6, 11, 0, 0);

        $response = $this->actingAs($shadow)->post(route('admin.tickets.dates', $ticket), [
            'created_at' => $loggedAt->format('Y-m-d\TH:i'),
            'closed_at' => $closedAt->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertTrue($ticket->closed_at->equalTo($closedAt));
    }

    public function test_technical_cannot_access_the_edit_dates_route(): void
    {
        $technical = $this->createUser('Edit Dates Technical', 'edit-dates-technical@example.com', User::ROLE_TECHNICAL);
        $client = $this->createClient('Edit Dates Blocked Client', 'edit-dates-blocked-client@example.com');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($technical)->post(route('admin.tickets.dates', $ticket), [
            'created_at' => $ticket->created_at->format('Y-m-d\TH:i'),
        ]);

        $response->assertForbidden();
    }

    public function test_editing_dates_with_invalid_order_is_rejected(): void
    {
        $superUser = $this->createUser('Edit Order Super', 'edit-order-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Edit Order Client', 'edit-order-client@example.com');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);
        $originalCreatedAt = $ticket->created_at;

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.dates', $ticket), [
                'created_at' => Carbon::create(2026, 2, 10)->format('Y-m-d\TH:i'),
                'resolved_at' => Carbon::create(2026, 2, 5)->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticket));
        $response->assertSessionHasErrors('resolved_at');
        $ticket->refresh();
        $this->assertTrue($ticket->created_at->equalTo($originalCreatedAt));
    }

    public function test_editing_dates_with_no_actual_changes_reports_no_changes(): void
    {
        $superUser = $this->createUser('No Change Super', 'no-change-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('No Change Client', 'no-change-client@example.com');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);

        $response = $this->actingAs($superUser)->post(route('admin.tickets.dates', $ticket), [
            'created_at' => $ticket->created_at->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'No changes were detected.');
    }

    public function test_editing_one_date_keeps_the_seconds_of_the_dates_left_alone(): void
    {
        $superUser = $this->createUser('Seconds Super', 'seconds-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createClient('Seconds Client', 'seconds-client@example.com');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);
        $loggedAt = Carbon::create(2026, 2, 1, 9, 15, 42);
        $resolvedAt = Carbon::create(2026, 2, 3, 15, 30, 17);
        $ticket->forceFill(['created_at' => $loggedAt, 'resolved_at' => $resolvedAt])->saveQuietly();

        // The form can only send minutes, so the untouched dates come back without their seconds.
        $response = $this->actingAs($superUser)->post(route('admin.tickets.dates', $ticket), [
            'created_at' => $loggedAt->format('Y-m-d\TH:i'),
            'resolved_at' => $resolvedAt->format('Y-m-d\TH:i'),
            'closed_at' => Carbon::create(2026, 2, 4, 10, 0, 0)->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $ticket->refresh();
        $this->assertTrue($ticket->closed_at->equalTo(Carbon::create(2026, 2, 4, 10, 0, 0)));
        $this->assertTrue($ticket->created_at->equalTo($loggedAt), 'Logged date lost its seconds.');
        $this->assertTrue($ticket->resolved_at->equalTo($resolvedAt), 'Resolved date lost its seconds.');
    }

    private function baseTicketPayload(User $client, Category $category): array
    {
        return [
            'user_id' => $client->id,
            'name' => $client->name,
            'contact_number' => '09170000000',
            'email' => $client->email,
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Manual logging test ticket',
            'description' => 'Entered after being handled on paper.',
            'category_id' => $category->id,
            'ticket_type' => 'external',
        ];
    }
}
