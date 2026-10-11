<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAssignmentRule;
use App\Models\TicketAssignmentSetting;
use App\Models\User;
use App\Services\Admin\TicketAssignmentAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AssignmentWorkloadTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $first;

    private User $second;

    private $category;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->first = $this->createAssignedSupportUser('Workload First', 'workload-first@example.com');
        $this->second = $this->createAssignedSupportUser('Workload Second', 'workload-second@example.com');
        $this->category = $this->createCategory('Workload Category');
        $this->client = $this->createClient('Workload Client', 'workload-client@example.com');

        TicketAssignmentSetting::current()->update(['distribution_method' => TicketAssignmentSetting::METHOD_LEAST_WORKLOAD]);
    }

    public function test_the_ticket_goes_to_whoever_has_the_fewest_open_and_in_progress_tickets(): void
    {
        $this->rules(first: 50, second: 50);
        $this->hold($this->first, 3, 'open');
        $this->hold($this->second, 1, 'in_progress');

        $this->assertSame($this->second->id, $this->assign());

        // Once the second has caught up, the first is the lighter again.
        $this->hold($this->second, 3, 'open');
        $this->assertSame($this->first->id, $this->assign());
    }

    public function test_pending_resolved_closed_and_imported_tickets_are_not_workload(): void
    {
        $this->rules(first: 50, second: 50);
        // The first holds 4 tickets that need no work from them now; the second holds one that does.
        $this->hold($this->first, 1, 'pending');
        $this->hold($this->first, 1, 'resolved');
        $this->hold($this->first, 1, 'closed');
        $this->hold($this->first, 1, 'open', ['is_imported' => true, 'creation_source' => Ticket::CREATION_SOURCE_IMPORTED]);
        $this->hold($this->second, 1, 'open');

        $this->assertSame($this->first->id, $this->assign());
    }

    public function test_a_ticket_shared_with_a_technician_counts_for_them(): void
    {
        $this->rules(first: 50, second: 50);
        $shared = $this->hold($this->first, 1, 'open')[0];
        $shared->assignedUsers()->syncWithoutDetaching([$this->second->id]);
        $this->hold($this->first, 1, 'open');

        // First holds 2, the second shares 1: the second is lighter.
        $this->assertSame($this->second->id, $this->assign());
    }

    public function test_the_weight_decides_how_much_work_each_one_carries(): void
    {
        // Weight 75 against 25: the first may hold three times as many tickets before they are level.
        $this->rules(first: 75, second: 25);

        $this->hold($this->first, 2, 'open');
        $this->hold($this->second, 1, 'open');
        $this->assertSame($this->first->id, $this->assign(), '2/75 is lighter than 1/25');

        $this->hold($this->first, 2, 'open');
        $this->assertSame($this->second->id, $this->assign(), '4/75 is heavier than 1/25');
    }

    public function test_an_even_load_goes_to_the_bigger_weight_and_then_the_lower_id(): void
    {
        $this->rules(first: 30, second: 70);
        $this->assertSame($this->second->id, $this->assign(), 'both hold nothing: the bigger weight');

        // The ticket just assigned would count as workload: start from nothing again.
        Ticket::query()->delete();
        TicketAssignmentRule::query()->delete();
        $this->rules(first: 50, second: 50);
        $this->assertSame(min($this->first->id, $this->second->id), $this->assign(), 'equal weight: the lower id');
    }

    public function test_someone_who_is_away_is_not_chosen_even_with_no_work(): void
    {
        $this->rules(first: 50, second: 50);
        $this->hold($this->second, 5, 'open');
        $this->first->forceFill(['assignment_paused_until' => now()->addDays(2)])->save();

        $this->assertSame($this->second->id, $this->assign());
    }

    public function test_an_admin_can_choose_the_method_and_the_page_offers_it(): void
    {
        $admin = $this->createUser('Workload Admin', 'workload-admin@example.com', User::ROLE_ADMIN);
        TicketAssignmentSetting::current()->update(['distribution_method' => TicketAssignmentSetting::METHOD_WEIGHTED_RANDOM]);

        $this->actingAs($admin)
            ->put(route('admin.assignment-rules.distribution-method'), ['distribution_method' => 'least_workload'])
            ->assertRedirect(route('admin.assignment-rules.index'));

        $this->assertSame(TicketAssignmentSetting::METHOD_LEAST_WORKLOAD, TicketAssignmentSetting::current()->distribution_method);
        $this->actingAs($admin)->get(route('admin.assignment-rules.index'))
            ->assertOk()
            ->assertSee('Least workload');
    }

    private function rules(int $first, int $second): void
    {
        TicketAssignmentRule::create(['category_id' => $this->category->id, 'user_id' => $this->first->id, 'weight' => $first]);
        TicketAssignmentRule::create(['category_id' => $this->category->id, 'user_id' => $this->second->id, 'weight' => $second]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<Ticket>
     */
    private function hold(User $technician, int $count, string $status, array $attributes = []): array
    {
        $tickets = [];
        for ($i = 0; $i < $count; $i++) {
            $ticket = $this->createTicket($this->client, $this->category, array_merge([
                'status' => $status,
                'assigned_to' => $technician->id,
                'assigned_at' => now(),
            ], $attributes));
            $ticket->assignedUsers()->syncWithoutDetaching([$technician->id]);
            $tickets[] = $ticket;
        }

        return $tickets;
    }

    private function assign(): ?int
    {
        $ticket = $this->createTicket($this->client, $this->category, ['status' => 'open']);
        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($ticket);

        return $ticket->fresh()->assigned_to;
    }
}
