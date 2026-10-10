<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketSavedView;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketSavedViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_current_ticket_filters(): void
    {
        $supportUser = $this->createSupportUser('Saved View Owner', 'saved-view-owner@example.com');
        $relatedUser = $this->createSupportUser('Saved View Related User', 'saved-view-related@example.com');

        $response = $this->actingAs($supportUser)->post(route('admin.ticket-saved-views.store'), [
            'name' => 'Unassigned Severity 1',
            'filters' => [
                'tab' => 'tickets',
                'priority' => 'severity_1',
                'category_bucket' => 'hardware',
                'assigned_to' => '0',
                'related_user_id' => (string) $relatedUser->id,
                'page' => '4',
                'partial' => '1',
            ],
        ]);

        $response->assertRedirect(route('admin.tickets.index', [
            'tab' => 'tickets',
            'priority' => 'severity_1',
            'category_bucket' => 'hardware',
            'assigned_to' => '0',
            'related_user_id' => (string) $relatedUser->id,
        ]));
        $response->assertSessionHas('success');

        $savedView = TicketSavedView::query()->where('user_id', $supportUser->id)->firstOrFail();

        $this->assertSame('Unassigned Severity 1', $savedView->name);
        $this->assertSame('unassigned severity 1', $savedView->name_normalized);
        $this->assertSame([
            'tab' => 'tickets',
            'priority' => 'severity_1',
            'category_bucket' => 'hardware',
            'assigned_to' => '0',
            'related_user_id' => (string) $relatedUser->id,
        ], $savedView->filters);
    }

    public function test_saved_ticket_views_render_apply_links(): void
    {
        $supportUser = $this->createSupportUser('Saved View Reader', 'saved-view-reader@example.com');

        TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => 'Severity 1 Queue',
            'filters' => [
                'tab' => 'tickets',
                'priority' => 'severity_1',
                'assigned_to' => '0',
            ],
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
        ]));

        $response->assertOk();
        $response->assertSee('Severity 1 Queue');
        $response->assertSee('priority=severity_1', false);
        $response->assertSee('assigned_to=0', false);
    }

    public function test_matching_saved_ticket_view_is_marked_active(): void
    {
        $supportUser = $this->createSupportUser('Saved View Active Reader', 'saved-view-active@example.com');

        TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => 'Severity 1 Queue',
            'filters' => [
                'tab' => 'tickets',
                'priority' => 'severity_1',
            ],
        ]);
        TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => 'All Tickets',
            'filters' => [
                'tab' => 'tickets',
            ],
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'priority' => 'severity_1',
        ]));

        $response->assertOk();
        $response->assertSee('Severity 1 Queue');
        $response->assertSee('aria-current="true"', false);
        $response->assertSee('Active');
    }

    public function test_saved_ticket_view_names_update_case_insensitively_for_same_user(): void
    {
        $supportUser = $this->createSupportUser('Saved View Case Owner', 'saved-view-case@example.com');

        TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => 'Severity Queue',
            'filters' => [
                'tab' => 'tickets',
                'priority' => 'severity_1',
            ],
        ]);

        $response = $this->actingAs($supportUser)->post(route('admin.ticket-saved-views.store'), [
            'name' => 'severity queue',
            'filters' => [
                'tab' => 'history',
                'status' => 'closed',
            ],
        ]);

        $response->assertRedirect(route('admin.tickets.index', [
            'tab' => 'history',
            'status' => 'closed',
        ]));

        $this->assertSame(1, TicketSavedView::query()->where('user_id', $supportUser->id)->count());
        $savedView = TicketSavedView::query()->where('user_id', $supportUser->id)->firstOrFail();
        $this->assertSame('severity queue', $savedView->name);
        $this->assertSame('severity queue', $savedView->name_normalized);
        $this->assertSame([
            'tab' => 'history',
            'status' => 'closed',
        ], $savedView->filters);
    }

    public function test_saved_ticket_view_normalized_names_are_unique_per_user(): void
    {
        $supportUser = $this->createSupportUser('Saved View Unique Owner', 'saved-view-unique@example.com');

        TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => 'Severity Queue',
            'filters' => ['tab' => 'tickets'],
        ]);

        $this->expectException(QueryException::class);

        TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => ' severity   queue ',
            'filters' => ['tab' => 'history'],
        ]);
    }

    public function test_saved_ticket_view_filters_apply_to_ticket_results(): void
    {
        $supportUser = $this->createSupportUser('Saved View Filter User', 'saved-view-filter@example.com');
        $relatedUser = $this->createSupportUser('Saved View Related Filter User', 'saved-view-related-filter@example.com');
        $hardwareCategory = $this->createCategory('Hardware Support');
        $softwareCategory = $this->createCategory('Software Support');
        $client = $this->createClient();

        $matchingTicket = $this->createTicket($client, $hardwareCategory, 'Visible related hardware ticket', 'severity_1');
        $matchingTicket->assignedUsers()->syncWithoutDetaching([$relatedUser->id]);
        $wrongCategoryTicket = $this->createTicket($client, $softwareCategory, 'Hidden related software ticket', 'severity_1');
        $wrongCategoryTicket->assignedUsers()->syncWithoutDetaching([$relatedUser->id]);
        $wrongRelatedUserTicket = $this->createTicket($client, $hardwareCategory, 'Hidden unrelated hardware ticket', 'severity_1');

        $savedView = TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => 'Related Hardware Only',
            'filters' => [
                'tab' => 'tickets',
                'priority' => 'severity_1',
                'category_bucket' => 'hardware',
                'related_user_id' => (string) $relatedUser->id,
            ],
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', $savedView->filters));

        $response->assertOk();
        $response->assertSee(route('admin.tickets.show', $matchingTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $wrongCategoryTicket), false);
        $response->assertDontSee(route('admin.tickets.show', $wrongRelatedUserTicket), false);
    }

    public function test_admin_can_delete_their_own_saved_ticket_view(): void
    {
        $supportUser = $this->createSupportUser('Saved View Deleter', 'saved-view-deleter@example.com');
        $savedView = TicketSavedView::create([
            'user_id' => $supportUser->id,
            'name' => 'Old View',
            'filters' => ['tab' => 'tickets'],
        ]);

        $response = $this->actingAs($supportUser)->delete(route('admin.ticket-saved-views.destroy', $savedView));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('ticket_saved_views', ['id' => $savedView->id]);
    }

    public function test_admin_cannot_delete_another_users_saved_ticket_view(): void
    {
        $owner = $this->createSupportUser('Saved View Owner Two', 'saved-view-owner-two@example.com');
        $otherUser = $this->createSupportUser('Saved View Other User', 'saved-view-other@example.com');
        $savedView = TicketSavedView::create([
            'user_id' => $owner->id,
            'name' => 'Private View',
            'filters' => ['tab' => 'tickets'],
        ]);

        $response = $this->actingAs($otherUser)->delete(route('admin.ticket-saved-views.destroy', $savedView));

        $response->assertNotFound();
        $this->assertDatabaseHas('ticket_saved_views', ['id' => $savedView->id]);
    }

    private function createSupportUser(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09110000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createClient(): User
    {
        return User::create([
            'name' => 'Saved View Client',
            'email' => 'saved-view-client@example.com',
            'phone' => '09112223333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(string $name = 'Saved View Category'): Category
    {
        return Category::create([
            'name' => $name,
            'description' => 'Saved view checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }

    private function createTicket(User $client, Category $category, string $subject, string $priority): Ticket
    {
        return Ticket::create([
            'name' => 'Saved View Requester',
            'contact_number' => '09110000199',
            'email' => 'saved-view-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => $subject,
            'description' => 'Saved view filter check.',
            'priority' => $priority,
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
    }
}
