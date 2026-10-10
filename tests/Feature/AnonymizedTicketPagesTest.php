<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Retention\TicketRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * A ticket whose personal details were removed (no phone, no email, placeholder text) must still show up and
 * count everywhere: lists, details, dashboards, reports and exports.
 */
class AnonymizedTicketPagesTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private Ticket $ticket;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Admin User', 'admin-retention@example.com', User::ROLE_ADMIN);
        $this->client = $this->createClient();
        $category = $this->createCategory();
        $this->ticket = $this->createTicket($this->client, $category, [
            'status' => 'closed',
            'closed_at' => now()->subYears(4),
            'resolved_at' => now()->subYears(4)->subDay(),
            'satisfaction_rating' => 5,
            'subject' => 'Printer on the second floor',
            'description' => 'It jams on every page',
        ]);
        TicketReply::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->admin->id, 'message' => 'Replaced the roller', 'is_internal' => false]);
        DB::table('tickets')->where('id', $this->ticket->id)->update(['created_at' => now()->subYears(4)->subDays(3)]);

        app(TicketRetentionService::class)->anonymize($this->ticket->fresh());
        $this->ticket = $this->ticket->fresh();
    }

    public function test_admin_pages_render_the_cleaned_ticket(): void
    {
        $this->actingAs($this->admin);
        $number = $this->ticket->ticket_number;

        $this->get(route('admin.tickets.index', ['tab' => 'history']))->assertOk()->assertSee($number)->assertSee('Ticket '.$number);
        $this->get(route('admin.tickets.show', $this->ticket))->assertOk()->assertSee($number)->assertSee(TicketRetentionService::PLACEHOLDER)->assertDontSee('Printer on the second floor');
        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.tickets.export', ['tab' => 'history']))->assertOk();
    }

    public function test_the_ticket_export_and_reports_still_count_the_cleaned_ticket(): void
    {
        $this->actingAs($this->admin);
        $month = $this->ticket->created_at->format('Y-m');

        $csv = $this->get(route('admin.tickets.export', ['tab' => 'history']))->streamedContent();
        $this->assertStringContainsString($this->ticket->ticket_number, $csv);
        $this->assertStringNotContainsString('Printer on the second floor', $csv);

        $this->get(route('admin.reports.custom.index', ['month' => $month]))
            ->assertOk()
            ->assertViewHas('totalCount', 1);
        $this->get(route('admin.reports.custom.csv', ['month' => $month]))->assertOk();
        $this->get(route('admin.reports.custom.pdf', ['month' => $month]))->assertOk();
        $this->get(route('admin.reports.monthly.csv', ['month' => $month]))->assertOk();
    }

    public function test_client_pages_render_the_cleaned_ticket(): void
    {
        $this->actingAs($this->client);
        $number = $this->ticket->ticket_number;

        $this->get(route('client.tickets.index'))->assertOk()->assertSee($number);
        $this->get(route('client.tickets.show', $this->ticket))->assertOk()->assertSee($number)->assertSee(TicketRetentionService::PLACEHOLDER);
        $this->get(route('client.tickets.replies.feed', $this->ticket))->assertOk();
        $this->get(route('client.dashboard'))->assertOk();
    }
}
