<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAssignmentRule;
use App\Models\TicketAssignmentSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class AdminTicketAssignmentRuleManagementTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_admin_can_create_and_delete_an_assignment_rule(): void
    {
        $admin = $this->createUser('Rule Admin', 'rule-admin@example.com', User::ROLE_ADMIN);
        $technician = $this->createAssignedSupportUser('Rule Technician', 'rule-technician-mgmt@example.com');
        $category = $this->createCategory('Rule Management Category');

        $storeResponse = $this->actingAs($admin)->post(route('admin.assignment-rules.store'), [
            'category_id' => $category->id,
            'user_id' => $technician->id,
            'weight' => 70,
        ]);

        $storeResponse->assertRedirect(route('admin.assignment-rules.index'));
        $this->assertDatabaseHas('ticket_assignment_rules', [
            'category_id' => $category->id,
            'user_id' => $technician->id,
            'weight' => 70,
        ]);

        $rule = TicketAssignmentRule::query()->where('category_id', $category->id)->firstOrFail();

        $destroyResponse = $this->actingAs($admin)->delete(route('admin.assignment-rules.destroy', $rule));

        $destroyResponse->assertRedirect(route('admin.assignment-rules.index'));
        $this->assertDatabaseMissing('ticket_assignment_rules', ['id' => $rule->id]);
    }

    public function test_duplicate_rule_for_the_same_category_and_technician_is_rejected(): void
    {
        $admin = $this->createUser('Duplicate Rule Admin', 'duplicate-rule-admin@example.com', User::ROLE_ADMIN);
        $technician = $this->createAssignedSupportUser('Duplicate Rule Technician', 'duplicate-rule-technician@example.com');
        $category = $this->createCategory('Duplicate Rule Category');
        TicketAssignmentRule::create(['category_id' => $category->id, 'user_id' => $technician->id, 'weight' => 50]);

        $response = $this->actingAs($admin)->post(route('admin.assignment-rules.store'), [
            'category_id' => $category->id,
            'user_id' => $technician->id,
            'weight' => 30,
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertSame(1, TicketAssignmentRule::query()->where('category_id', $category->id)->count());
    }

    public function test_admin_can_update_the_distribution_method(): void
    {
        $admin = $this->createUser('Method Admin', 'method-admin@example.com', User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->put(route('admin.assignment-rules.distribution-method'), [
            'distribution_method' => TicketAssignmentSetting::METHOD_ROTATING_COUNTER,
        ]);

        $response->assertRedirect(route('admin.assignment-rules.index'));
        $this->assertSame(
            TicketAssignmentSetting::METHOD_ROTATING_COUNTER,
            TicketAssignmentSetting::current()->distribution_method
        );
    }

    public function test_super_user_cannot_access_assignment_rules_page(): void
    {
        $superUser = $this->createUser('Rule Super User', 'rule-super-user@example.com', User::ROLE_SUPER_USER);

        $response = $this->actingAs($superUser)->get(route('admin.assignment-rules.index'));

        $response->assertForbidden();
    }

    public function test_technician_cannot_access_assignment_rules_page(): void
    {
        $technician = $this->createAssignedSupportUser('Blocked Technician', 'blocked-technician@example.com');

        $response = $this->actingAs($technician)->get(route('admin.assignment-rules.index'));

        $response->assertForbidden();
    }

    public function test_new_client_ticket_is_auto_assigned_according_to_configured_rule(): void
    {
        config(['legal.require_acceptance' => false]);

        $client = $this->createClient('Auto Assign Client', 'auto-assign-client@example.com');
        $technician = $this->createAssignedSupportUser('Auto Assign Technician', 'auto-assign-technician@example.com');
        $category = $this->createCategory('Starlink');
        TicketAssignmentRule::create(['category_id' => $category->id, 'user_id' => $technician->id, 'weight' => 100]);

        $response = $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Auto Assign Client',
            'contact_number' => '09189990099',
            'email' => 'auto-assign-client@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Starlink dish not connecting',
            'description' => 'The dish lost signal overnight.',
            'category_id' => $category->id,
            'priority' => 'high',
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ]);

        $response->assertRedirect();

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        $this->assertSame($technician->id, $ticket->assigned_to);
    }
}
