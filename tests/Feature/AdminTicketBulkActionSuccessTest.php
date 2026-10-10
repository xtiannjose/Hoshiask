<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketBulkActionSuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_can_bulk_assign_tickets(): void
    {
        [$superUser, , $technical, $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'assign',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
                'assigned_to' => $technical->id,
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketOne->id,
            'assigned_to' => $technical->id,
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketTwo->id,
            'assigned_to' => $technical->id,
        ]);
    }

    public function test_super_user_can_bulk_assign_tickets_to_multiple_support_users(): void
    {
        [$superUser, , $technical, $ticketOne, $ticketTwo] = $this->seedBulkActionContext();
        $secondarySuperUser = User::create([
            'name' => 'Bulk Secondary Super User',
            'email' => 'bulk-secondary-super-user@example.com',
            'phone' => '09130000006',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'assign',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
                'assigned_to' => [$secondarySuperUser->id, $technical->id],
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame($secondarySuperUser->id, (int) $ticketOne->assigned_to);
        $this->assertSame($secondarySuperUser->id, (int) $ticketTwo->assigned_to);
        $this->assertEqualsCanonicalizing(
            [$secondarySuperUser->id, $technical->id],
            $ticketOne->assigned_user_ids
        );
        $this->assertEqualsCanonicalizing(
            [$secondarySuperUser->id, $technical->id],
            $ticketTwo->assigned_user_ids
        );
    }

    public function test_super_user_can_bulk_close_tickets_with_reason(): void
    {
        [$superUser, , , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();
        Ticket::query()->whereIn('id', [$ticketOne->id, $ticketTwo->id])->update([
            'status' => 'resolved',
            'resolved_at' => now()->subHours(26),
            'closed_at' => null,
        ]);

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'status',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
                'status' => 'closed',
                'close_reason' => 'Bulk closure validation reason.',
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame('closed', $ticketOne->status);
        $this->assertSame('closed', $ticketTwo->status);
        $this->assertNotNull($ticketOne->closed_at);
        $this->assertNotNull($ticketTwo->closed_at);
        $this->assertNotNull($ticketOne->resolved_at);
        $this->assertNotNull($ticketTwo->resolved_at);
    }

    public function test_super_user_can_bulk_update_ticket_priority(): void
    {
        [$superUser, , , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'priority',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
                'priority' => 'urgent',
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketOne->id,
            'priority' => 'severity_1',
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketTwo->id,
            'priority' => 'severity_1',
        ]);
    }

    public function test_super_user_can_bulk_merge_tickets(): void
    {
        [$superUser, , $technical, $ticketOne, $ticketTwo] = $this->seedBulkActionContext();
        $secondaryTechnical = User::create([
            'name' => 'Bulk Secondary Technical',
            'email' => 'bulk-secondary-technical@example.com',
            'phone' => '09130000005',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $ticketOne->update([
            'assigned_to' => $technical->id,
            'assigned_at' => now()->subHour(),
        ]);
        $ticketOne->assignedUsers()->sync([$technical->id]);

        $ticketTwo->update([
            'assigned_to' => $secondaryTechnical->id,
            'assigned_at' => now()->subHours(2),
        ]);
        $ticketTwo->assignedUsers()->sync([$secondaryTechnical->id]);

        $ticketOne->created_at = Carbon::now()->subMinutes(20);
        $ticketOne->save();
        $ticketTwo->created_at = Carbon::now()->subMinutes(10);
        $ticketTwo->save();

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'merge',
                'selected_ids' => [$ticketTwo->id, $ticketOne->id],
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticketOne));

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame('open', $ticketOne->status);
        $this->assertSame('closed', $ticketTwo->status);
        $this->assertNotNull($ticketTwo->closed_at);
        $this->assertNotNull($ticketTwo->resolved_at);
        $this->assertEqualsCanonicalizing(
            [$technical->id, $secondaryTechnical->id],
            $ticketOne->assigned_user_ids
        );

        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticketOne->id,
            'user_id' => $technical->id,
        ]);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticketOne->id,
            'user_id' => $secondaryTechnical->id,
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticketOne->id,
            'message' => "Merged ticket {$ticketTwo->ticket_number}: {$ticketTwo->subject}",
            'is_internal' => true,
        ]);

        $this->actingAs($secondaryTechnical)
            ->get(route('admin.tickets.show', $ticketOne))
            ->assertOk();
    }

    public function test_cross_owner_merge_is_blocked_without_confirmation(): void
    {
        [$superUser, , , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();
        $otherClient = User::create([
            'name' => 'Bulk Other Client',
            'email' => 'bulk-other-client@example.com',
            'phone' => '09130000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $ticketTwo->update(['user_id' => $otherClient->id]);

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'merge',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('error');

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame('open', $ticketOne->status);
        $this->assertSame('open', $ticketTwo->status);
        $this->assertDatabaseMissing('ticket_replies', [
            'ticket_id' => $ticketOne->id,
        ]);
        $this->assertSame(0, SystemLog::whereIn('event_type', ['ticket.bulk.merge', 'ticket.bulk.merge.cross_owner'])->count());
    }

    public function test_cross_owner_merge_succeeds_with_confirmation_and_logs_distinctly(): void
    {
        [$superUser, , , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();
        $otherClient = User::create([
            'name' => 'Bulk Other Client',
            'email' => 'bulk-other-client@example.com',
            'phone' => '09130000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $ticketTwo->update(['user_id' => $otherClient->id]);
        $ticketOne->created_at = Carbon::now()->subMinutes(20);
        $ticketOne->save();
        $ticketTwo->created_at = Carbon::now()->subMinutes(10);
        $ticketTwo->save();

        $response = $this->actingAs($superUser)
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'merge',
                'selected_ids' => [$ticketTwo->id, $ticketOne->id],
                'confirm_cross_owner_merge' => true,
            ]);

        $response->assertRedirect(route('admin.tickets.show', $ticketOne));
        $response->assertSessionHas('success');

        $ticketOne->refresh();
        $ticketTwo->refresh();

        $this->assertSame('open', $ticketOne->status);
        $this->assertSame('closed', $ticketTwo->status);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticketOne->id,
            'message' => "Merged ticket {$ticketTwo->ticket_number}: {$ticketTwo->subject}",
        ]);

        $log = SystemLog::where('event_type', 'ticket.bulk.merge.cross_owner')->first();
        $this->assertNotNull($log);
        $this->assertTrue($log->metadata['cross_owner_merge']);
        $this->assertSame($ticketOne->user_id, $log->metadata['primary_user_id']);
        $this->assertContains($otherClient->id, $log->metadata['merged_user_ids']);
        $this->assertSame(0, SystemLog::where('event_type', 'ticket.bulk.merge')->count());
    }

    public function test_super_admin_can_bulk_delete_tickets(): void
    {
        [, $superAdmin, , $ticketOne, $ticketTwo] = $this->seedBulkActionContext();

        $response = $this->actingAs($superAdmin)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'delete',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('tickets', ['id' => $ticketOne->id]);
        $this->assertDatabaseMissing('tickets', ['id' => $ticketTwo->id]);
    }

    private function seedBulkActionContext(): array
    {
        $superUser = User::create([
            'name' => 'Bulk Super User',
            'email' => 'bulk-super-user@example.com',
            'phone' => '09130000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $superAdmin = User::create([
            'name' => 'Bulk Super Admin',
            'email' => 'bulk-super-admin@example.com',
            'phone' => '09130000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Bulk Technical',
            'email' => 'bulk-technical@example.com',
            'phone' => '09130000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Bulk Client',
            'email' => 'bulk-client@example.com',
            'phone' => '09130000004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Bulk Category',
            'description' => 'Bulk action test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticketOne = Ticket::create([
            'name' => 'Bulk Requester One',
            'contact_number' => '09130000011',
            'email' => 'bulk-requester-one@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Bulk ticket one',
            'description' => 'Bulk ticket one description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $ticketTwo = Ticket::create([
            'name' => 'Bulk Requester Two',
            'contact_number' => '09130000012',
            'email' => 'bulk-requester-two@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Bulk ticket two',
            'description' => 'Bulk ticket two description',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$superUser, $superAdmin, $technical, $ticketOne, $ticketTwo];
    }
}
