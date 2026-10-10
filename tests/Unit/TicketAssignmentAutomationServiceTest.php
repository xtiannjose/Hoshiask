<?php

namespace Tests\Unit;

use App\Models\TicketAssignmentRule;
use App\Models\TicketAssignmentSetting;
use App\Services\Admin\TicketAssignmentAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class TicketAssignmentAutomationServiceTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_it_leaves_ticket_unassigned_when_no_rule_matches_category(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory('No Rule Category');
        $ticket = $this->createTicket($client, $category, ['assigned_to' => null]);

        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($ticket);

        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_it_does_not_override_an_existing_assignment(): void
    {
        $client = $this->createClient();
        $technician = $this->createAssignedSupportUser('Existing Assignee', 'existing-assignee@example.com');
        $otherTechnician = $this->createAssignedSupportUser('Rule Technician', 'rule-technician@example.com');
        $category = $this->createCategory('Already Assigned Category');
        TicketAssignmentRule::create(['category_id' => $category->id, 'user_id' => $otherTechnician->id, 'weight' => 100]);

        $ticket = $this->createTicket($client, $category, ['assigned_to' => $technician->id]);

        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($ticket);

        $this->assertSame($technician->id, $ticket->fresh()->assigned_to);
    }

    public function test_weighted_random_assigns_to_the_only_configured_technician_when_weight_is_full(): void
    {
        $client = $this->createClient();
        $technician = $this->createAssignedSupportUser('Solo Technician', 'solo-technician@example.com');
        $category = $this->createCategory('Solo Rule Category');
        TicketAssignmentRule::create(['category_id' => $category->id, 'user_id' => $technician->id, 'weight' => 100]);

        $ticket = $this->createTicket($client, $category, ['assigned_to' => null]);

        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($ticket);

        $this->assertSame($technician->id, $ticket->fresh()->assigned_to);
        $this->assertDatabaseHas('system_logs', [
            'event_type' => 'ticket.assignment.automated',
        ]);
    }

    public function test_rotating_counter_assigns_to_the_technician_furthest_below_their_target_share(): void
    {
        TicketAssignmentSetting::current()->update([
            'distribution_method' => TicketAssignmentSetting::METHOD_ROTATING_COUNTER,
        ]);

        $client = $this->createClient();
        $overservedTechnician = $this->createAssignedSupportUser('Overserved Technician', 'overserved-technician@example.com');
        $underservedTechnician = $this->createAssignedSupportUser('Underserved Technician', 'underserved-technician@example.com');
        $category = $this->createCategory('Rotating Counter Category');

        TicketAssignmentRule::create(['category_id' => $category->id, 'user_id' => $overservedTechnician->id, 'weight' => 50]);
        TicketAssignmentRule::create(['category_id' => $category->id, 'user_id' => $underservedTechnician->id, 'weight' => 50]);

        // Seed history heavily skewed toward the "overserved" technician.
        for ($i = 0; $i < 4; $i++) {
            $this->createTicket($client, $category, ['assigned_to' => $overservedTechnician->id]);
        }
        $this->createTicket($client, $category, ['assigned_to' => $underservedTechnician->id]);

        $newTicket = $this->createTicket($client, $category, ['assigned_to' => null]);

        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($newTicket);

        $this->assertSame($underservedTechnician->id, $newTicket->fresh()->assigned_to);
    }

    public function test_it_ignores_rules_pointing_at_deactivated_technicians(): void
    {
        $client = $this->createClient();
        $deactivatedTechnician = $this->createAssignedSupportUser('Deactivated Technician', 'deactivated-technician@example.com', ['is_active' => false]);
        $category = $this->createCategory('Deactivated Rule Category');
        TicketAssignmentRule::create(['category_id' => $category->id, 'user_id' => $deactivatedTechnician->id, 'weight' => 100]);

        $ticket = $this->createTicket($client, $category, ['assigned_to' => null]);

        app(TicketAssignmentAutomationService::class)->autoAssignIfUnassigned($ticket);

        $this->assertNull($ticket->fresh()->assigned_to);
    }
}
