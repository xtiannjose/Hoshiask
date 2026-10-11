<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAssignmentRule;
use App\Models\TicketAssignmentSetting;
use App\Models\User;
use App\Services\Admin\TicketAssignmentAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AssignmentAvailabilityTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private User $away;

    private User $present;

    private $category;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 12, 9, 0, 0));

        $this->away = $this->createAssignedSupportUser('Away Technician', 'away-technician@example.com');
        $this->present = $this->createAssignedSupportUser('Present Technician', 'present-technician@example.com');
        $this->category = $this->createCategory('Availability Category');

        // The away person has the bigger share, so they would win every time if they were not skipped.
        TicketAssignmentRule::create(['category_id' => $this->category->id, 'user_id' => $this->away->id, 'weight' => 90]);
        TicketAssignmentRule::create(['category_id' => $this->category->id, 'user_id' => $this->present->id, 'weight' => 10]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_technician_who_is_away_is_skipped_by_both_distribution_methods(): void
    {
        $this->away->forceFill(['assignment_paused_until' => now()->addDays(3)])->save();

        foreach ([TicketAssignmentSetting::METHOD_ROTATING_COUNTER, TicketAssignmentSetting::METHOD_WEIGHTED_RANDOM] as $method) {
            TicketAssignmentSetting::current()->update(['distribution_method' => $method]);

            for ($i = 0; $i < 4; $i++) {
                $ticket = $this->unassignedTicket();
                app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($ticket);

                $this->assertSame($this->present->id, $ticket->fresh()->assigned_to, $method);
            }
        }
    }

    public function test_the_technician_is_assigned_again_once_the_day_they_are_back_arrives(): void
    {
        $this->away->forceFill(['assignment_paused_until' => Carbon::create(2026, 10, 14)])->save();
        TicketAssignmentSetting::current()->update(['distribution_method' => TicketAssignmentSetting::METHOD_ROTATING_COUNTER]);

        $first = $this->unassignedTicket();
        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($first);
        $this->assertSame($this->present->id, $first->fresh()->assigned_to);

        Carbon::setTestNow(Carbon::create(2026, 10, 14, 0, 0, 0));
        $second = $this->unassignedTicket();
        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($second);
        $this->assertSame($this->away->id, $second->fresh()->assigned_to);
    }

    public function test_when_everyone_in_the_category_is_away_the_ticket_stays_unassigned(): void
    {
        $this->away->forceFill(['assignment_paused_until' => now()->addDays(3)])->save();
        $this->present->forceFill(['assignment_paused_until' => now()->addDays(3)])->save();

        $ticket = $this->unassignedTicket();
        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($ticket);

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_an_admin_can_set_and_clear_the_day_a_technician_is_back(): void
    {
        $admin = $this->createUser('Availability Admin', 'availability-admin@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->put(route('admin.assignment-rules.availability', $this->away), ['back_on' => '2026-10-20'])
            ->assertRedirect(route('admin.assignment-rules.index'));
        $this->assertSame('2026-10-20 00:00:00', $this->away->fresh()->assignment_paused_until->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('system_logs', ['event_type' => 'ticket_assignment_rule.availability_updated']);

        $this->actingAs($admin)->get(route('admin.assignment-rules.index'))
            ->assertOk()
            ->assertSee('Away until Oct 20, 2026');

        $this->actingAs($admin)
            ->put(route('admin.assignment-rules.availability', $this->away), ['back_on' => ''])
            ->assertRedirect(route('admin.assignment-rules.index'));
        $this->assertNull($this->away->fresh()->assignment_paused_until);
    }

    public function test_a_day_in_the_past_or_text_is_refused_and_a_super_user_cannot_change_it(): void
    {
        $admin = $this->createUser('Availability Admin Two', 'availability-admin-two@example.com', User::ROLE_ADMIN);
        $superUser = $this->createUser('Availability Super', 'availability-super@example.com', User::ROLE_SUPER_USER);

        foreach (['2026-10-10', 'next week', '2026-10-12'] as $bad) {
            $this->actingAs($admin)
                ->put(route('admin.assignment-rules.availability', $this->away), ['back_on' => $bad])
                ->assertSessionHasErrors('back_on');
        }
        $this->assertNull($this->away->fresh()->assignment_paused_until);

        $this->actingAs($superUser)
            ->put(route('admin.assignment-rules.availability', $this->away), ['back_on' => '2026-10-20'])
            ->assertForbidden();
    }

    public function test_a_client_account_cannot_be_given_an_availability(): void
    {
        $admin = $this->createUser('Availability Admin Three', 'availability-admin-three@example.com', User::ROLE_ADMIN);
        $client = $this->createClient('Availability Client', 'availability-client@example.com');

        $this->actingAs($admin)
            ->put(route('admin.assignment-rules.availability', $client), ['back_on' => '2026-10-20'])
            ->assertNotFound();
    }

    private function unassignedTicket(): Ticket
    {
        return $this->createTicket($this->createClient('Client '.uniqid(), uniqid('c').'@example.com'), $this->category);
    }
}
