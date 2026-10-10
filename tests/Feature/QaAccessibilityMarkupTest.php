<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class QaAccessibilityMarkupTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_signed_in_pages_offer_a_skip_link_to_a_main_region(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('<a href="#main-content" class="skip-link">Skip to main content</a>', false)
            ->assertSee('<main id="main-content" tabindex="-1"', false);
    }

    public function test_public_legal_pages_have_a_main_region_and_skip_link(): void
    {
        foreach ([route('legal.terms'), route('legal.privacy'), route('legal.ticket-consent')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('class="skip-link"', false)
                ->assertSee('<main id="main-content"', false);
        }
    }

    public function test_the_phone_menu_closes_with_escape(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->get(route('client.dashboard'))
            ->assertSee('@keydown.escape.window="closeSidebar"', false);
    }

    public function test_icon_only_links_and_bare_text_boxes_have_accessible_names(): void
    {
        $admin = $this->createUser('Admin', 'admin-a11y@example.com', User::ROLE_ADMIN);
        $managed = $this->createClient('Managed Person', 'managed-a11y@example.com');
        $ticket = $this->createTicket($managed, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($admin)->get(route('admin.users.show', $managed))
            ->assertSee('aria-label="Back to users"', false);
        $this->actingAs($admin)->get(route('admin.users.edit', $managed))
            ->assertSee('aria-label="Back to users"', false);
        $this->actingAs($admin)->get(route('admin.tickets.create'))
            ->assertSee('aria-label="Go to dashboard"', false);

        $this->actingAs($admin)->get(route('admin.tickets.show', $ticket))
            ->assertSee('aria-label="Your reply"', false)
            ->assertSee('aria-label="Reason for escalating this ticket"', false)
            ->assertSee('aria-label="Root cause notes (optional)"', false);

        $this->actingAs($managed)->get(route('client.tickets.show', $ticket))
            ->assertSee('aria-label="Your message"', false);
    }

    public function test_the_new_ticket_page_does_not_skip_a_heading_level(): void
    {
        $client = $this->createClient();

        $html = $this->actingAs($client)->get(route('client.tickets.create'))->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('<h3', $html);
        $this->assertStringContainsString('<h2 class="text-sm font-medium text-slate-900">Tips for better support</h2>', $html);
    }

    public function test_no_text_is_set_below_twelve_pixels_in_the_templates_that_were_flagged(): void
    {
        foreach ([
            resource_path('views/layouts/navigation.blade.php'),
            resource_path('views/admin/tickets/partials/results.blade.php'),
            resource_path('views/client/tickets/partials/results.blade.php'),
        ] as $file) {
            $this->assertStringNotContainsString('text-[11px]', (string) file_get_contents($file), $file);
        }
    }

    public function test_the_staff_ticket_table_lets_the_status_and_severity_pills_stay_on_one_line(): void
    {
        $admin = $this->createUser('Admin', 'admin-pills@example.com', User::ROLE_ADMIN);
        $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'in_progress']);

        $html = $this->actingAs($admin)->get(route('admin.tickets.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/inline-flex max-w-full whitespace-nowrap[^"]*status|whitespace-nowrap[^"]*rounded-lg[^"]*uppercase/', $html);
        $this->assertStringContainsString('<th class="w-[11%] px-3 py-4 text-center"', $html);
    }
}
