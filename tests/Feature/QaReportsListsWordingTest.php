<?php

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Support\TicketListSort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class QaReportsListsWordingTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 9, 30, 10, 0, 0));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return $this->createUser('Admin', 'admin-qa-e@example.com', User::ROLE_ADMIN);
    }

    // ---- Q-21 custom report dates -----------------------------------------------

    public function test_swapped_dates_are_reported_to_the_user(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reports.custom.index', [
            'created_from' => '2026-09-30',
            'created_to' => '2026-09-01',
        ]));

        $response->assertOk()
            ->assertSee('The From date was later than the To date, so the two dates were swapped.')
            ->assertSee('data-report-date-notice', false);
        $response->assertViewHas('dateRange', fn (array $range) => $range['from'] === '2026-09-01' && $range['to'] === '2026-09-30');
    }

    public function test_an_overly_wide_range_keeps_the_most_recent_stretch_and_says_so(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.reports.custom.index', [
            'created_from' => '2010-01-01',
            'created_to' => '2026-09-30',
        ]));

        $response->assertOk()->assertSee('shows the most recent stretch');
        $response->assertViewHas('dateRange', function (array $range) {
            return $range['to'] === '2026-09-30'
                && $range['start']->diffInDays($range['end']) <= 731
                && $range['from'] >= '2024-09-01'
                && str_contains($range['label'], 'capped');
        });
    }

    public function test_a_normal_range_and_the_default_month_show_no_notices(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.reports.custom.index', ['created_from' => '2026-09-01', 'created_to' => '2026-09-30']))
            ->assertOk()->assertDontSee('data-report-date-notice', false);
        $this->actingAs($admin)->get(route('admin.reports.custom.index'))
            ->assertOk()->assertDontSee('data-report-date-notice', false);
    }

    public function test_the_csv_carries_the_notes_and_uses_the_word_severity(): void
    {
        $admin = $this->admin();
        $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'open']);

        $csv = $this->actingAs($admin)->get(route('admin.reports.custom.csv', [
            'created_from' => '2026-09-30',
            'created_to' => '2026-09-01',
            'layout' => 'detailed',
        ]))->streamedContent();

        $this->assertStringContainsString('Note,"The From date was later than the To date, so the two dates were swapped."', $csv);
        $this->assertStringContainsString('Severity', $csv);
        $this->assertStringNotContainsString('Priority', $csv);
    }

    // ---- Q-33 sorting and page size -------------------------------------------------

    /**
     * @return array<int, string> ticket numbers in the order the list shows them
     */
    private function listedNumbers(User $admin, array $query = []): array
    {
        $response = $this->actingAs($admin)->get(route('admin.tickets.index', $query))->assertOk();

        return $response->viewData('tickets')->pluck('ticket_number')->all();
    }

    public function test_the_list_sorts_by_severity_most_urgent_first_with_unrated_tickets_last(): void
    {
        $admin = $this->admin();
        $client = $this->createClient();
        $category = $this->createCategory();
        $sev3 = $this->createTicket($client, $category, ['priority' => 'severity_3']);
        $unrated = $this->createTicket($client, $category, ['priority' => null]);
        $sev1 = $this->createTicket($client, $category, ['priority' => 'severity_1']);
        $sev2 = $this->createTicket($client, $category, ['priority' => 'severity_2']);

        $this->assertSame(
            [$sev1->ticket_number, $sev2->ticket_number, $sev3->ticket_number, $unrated->ticket_number],
            $this->listedNumbers($admin, ['sort' => 'severity'])
        );
        $this->assertSame(
            [$sev3->ticket_number, $sev2->ticket_number, $sev1->ticket_number, $unrated->ticket_number],
            $this->listedNumbers($admin, ['sort' => 'severity', 'direction' => 'desc'])
        );
    }

    public function test_the_list_sorts_by_status_with_open_work_first(): void
    {
        $admin = $this->admin();
        $client = $this->createClient();
        $category = $this->createCategory();
        $closed = $this->createTicket($client, $category, ['status' => 'closed']);
        $pending = $this->createTicket($client, $category, ['status' => 'pending']);
        $open = $this->createTicket($client, $category, ['status' => 'open']);
        $progress = $this->createTicket($client, $category, ['status' => 'in_progress']);

        $this->assertSame(
            [$open->ticket_number, $progress->ticket_number, $pending->ticket_number, $closed->ticket_number],
            $this->listedNumbers($admin, ['sort' => 'status', 'tab' => 'all'])
        );
    }

    public function test_the_default_order_is_newest_first_and_oldest_first_can_be_chosen(): void
    {
        $admin = $this->admin();
        $client = $this->createClient();
        $category = $this->createCategory();
        $older = $this->createTicket($client, $category);
        Carbon::setTestNow(now()->addHour());
        $newer = $this->createTicket($client, $category);

        $this->assertSame([$newer->ticket_number, $older->ticket_number], $this->listedNumbers($admin));
        $this->assertSame([$older->ticket_number, $newer->ticket_number], $this->listedNumbers($admin, ['sort' => 'created', 'direction' => 'asc']));
    }

    public function test_unknown_sort_values_fall_back_to_the_default_and_never_break_the_page(): void
    {
        $admin = $this->admin();
        $this->createTicket($this->createClient(), $this->createCategory());

        foreach ([
            ['sort' => 'password; DROP TABLE tickets', 'direction' => 'sideways'],
            ['sort' => ['x'], 'direction' => ['y']],
            ['per_page' => 'lots'],
            ['per_page' => 100000],
        ] as $query) {
            $this->actingAs($admin)->get(route('admin.tickets.index', $query))->assertOk();
        }

        $this->assertSame(['sort' => 'created', 'direction' => 'desc'], TicketListSort::resolve(request()->duplicate(['sort' => 'nope'])));
    }

    public function test_the_page_size_can_be_raised_and_falls_back_when_unsupported(): void
    {
        $admin = $this->admin();
        $client = $this->createClient();
        $category = $this->createCategory();
        for ($i = 0; $i < 20; $i++) {
            $this->createTicket($client, $category);
        }

        $this->assertCount(15, $this->listedNumbers($admin));
        $this->assertCount(20, $this->listedNumbers($admin, ['per_page' => 50]));
        $this->assertCount(15, $this->listedNumbers($admin, ['per_page' => 7]));
    }

    public function test_the_headings_are_sort_links_and_page_size_links_are_offered(): void
    {
        $admin = $this->admin();
        $this->createTicket($this->createClient(), $this->createCategory());

        $this->actingAs($admin)->get(route('admin.tickets.index', ['sort' => 'severity', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('sort=severity&amp;direction=desc', false)
            ->assertSee('Rows per page:')
            ->assertSee('per_page=50', false);
    }

    public function test_sorting_survives_the_live_refresh_partial_and_pagination(): void
    {
        $admin = $this->admin();
        $client = $this->createClient();
        $category = $this->createCategory();
        for ($i = 0; $i < 20; $i++) {
            $this->createTicket($client, $category, ['priority' => $i % 2 === 0 ? 'severity_1' : 'severity_3']);
        }

        $json = $this->actingAs($admin)->getJson(route('admin.tickets.index', ['sort' => 'severity', 'partial' => 1]))->assertOk()->json();

        $this->assertStringContainsString('sort=severity', $json['html']);
        $this->assertStringNotContainsString('partial=1', $json['html']);
    }

    // ---- Q-34 bulk actions ---------------------------------------------------------------

    public function test_the_bulk_buttons_are_grouped_and_labelled_as_acting_on_ticked_tickets(): void
    {
        $this->actingAs($this->admin())->get(route('admin.tickets.index'))
            ->assertOk()
            ->assertSee('role="group" aria-label="Actions for the tickets you tick in the list"', false)
            ->assertSee('Ticked tickets');
    }

    // ---- Q-13 resolved ticket banner --------------------------------------------------------

    public function test_a_resolved_unrated_ticket_shows_a_confirm_or_reopen_banner_with_the_close_date(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'resolved', 'resolved_at' => now()]);

        $this->actingAs($client)->get(route('client.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-resolved-banner', false)
            ->assertSee('Your ticket was resolved. Please confirm it is fixed, or reopen it.')
            ->assertSee('closes automatically on Oct 7, 2026')
            ->assertSee('id="ticket-actions"', false);
    }

    public function test_open_and_already_rated_tickets_show_no_banner(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $open = $this->createTicket($client, $category, ['status' => 'open']);
        $rated = $this->createTicket($client, $category, ['status' => 'resolved', 'resolved_at' => now(), 'satisfaction_rating' => 5, 'satisfaction_comment' => 'Great']);

        $this->actingAs($client)->get(route('client.tickets.show', $open))->assertDontSee('data-resolved-banner', false);
        $this->actingAs($client)->get(route('client.tickets.show', $rated))->assertDontSee('data-resolved-banner', false);
    }

    public function test_the_conversation_box_is_sized_to_its_content_with_a_maximum(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $html = $this->actingAs($client)->get(route('client.tickets.show', $ticket))->getContent();

        $this->assertDoesNotMatchRegularExpression('/[\s"]h-\[560px\]/', $html);
        $this->assertStringContainsString('max-h-[60vh] min-h-56', $html);
    }

    // ---- Q-32 wording ---------------------------------------------------------------------------

    public function test_message_counts_are_pluralised(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)->get(route('client.tickets.show', $ticket))->assertSee('1 message')->assertDontSee('1 messages');

        TicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $client->id, 'message' => 'Hello', 'is_internal' => false]);

        $this->actingAs($client)->get(route('client.tickets.show', $ticket))->assertSee('2 messages');
    }

    public function test_labels_use_one_form_each(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertSee('Create User')
            ->assertDontSee('Add User');
        $this->actingAs($admin)->get(route('admin.tickets.index'))
            ->assertSee('All Severities')
            ->assertSee('All Assigned Staff')
            ->assertSee('All Accounts')
            ->assertDontSee('All severities');
        $this->actingAs($this->createClient('Label Client', 'label-client@example.com'))->get(route('client.dashboard'))
            ->assertSee('New Ticket')
            ->assertDontSee('New ticket');
    }

    public function test_the_activity_label_for_in_progress_matches_the_status_label(): void
    {
        $ticket = new Ticket(['status' => 'in_progress']);

        $this->assertSame('In Progress', $ticket->activity_label);
    }

    public function test_a_password_confirmation_mismatch_uses_plain_wording(): void
    {
        $tech = $this->createAssignedSupportUser('Confirm Tech', 'confirm-tech@example.com', ['must_change_password' => true]);

        $this->actingAs($tech)->from(route('account.settings'))->put(route('account.settings.update'), [
            'name' => $tech->name,
            'current_password' => 'password123',
            'password' => 'BrandNewPass99',
            'password_confirmation' => 'DifferentPass99',
        ])->assertSessionHasErrors(['password' => 'The two password entries do not match. Please type it the same way twice.']);
    }

    // ---- Q-37 empty states -------------------------------------------------------------------------

    public function test_an_empty_knowledge_base_offers_the_first_article_to_staff(): void
    {
        $this->actingAs($this->admin())->get(route('admin.knowledge-base.index'))
            ->assertOk()
            ->assertSee('data-empty-state', false)
            ->assertSee('Write the first article')
            ->assertSee(route('admin.knowledge-base.create'), false);
    }

    public function test_a_search_with_no_matches_is_explained_and_can_be_reset(): void
    {
        $admin = $this->admin();
        KnowledgeArticle::create([
            'title' => 'Reset a password',
            'slug' => 'reset-a-password',
            'body' => 'Steps.',
            'category' => 'other',
            'is_published' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('admin.knowledge-base.index', ['search' => 'zzz-nothing']))
            ->assertOk()
            ->assertSee('No articles match those filters.')
            ->assertSee('Show all articles')
            ->assertDontSee('Write the first article');
    }

    public function test_an_empty_client_knowledge_base_points_to_creating_a_ticket(): void
    {
        $this->actingAs($this->createClient())->get(route('client.knowledge-base.index'))
            ->assertOk()
            ->assertSee('No articles have been published yet.')
            ->assertSee(route('client.tickets.create'), false);
    }
}
