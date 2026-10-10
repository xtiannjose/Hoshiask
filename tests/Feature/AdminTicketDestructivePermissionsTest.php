<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketDestructivePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_technical_user_cannot_delete_ticket(): void
    {
        [$technical, $ticket] = $this->seedTechnicalAndTicket();

        $response = $this->actingAs($technical)
            ->delete(route('admin.tickets.destroy', $ticket));

        $response->assertForbidden();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_super_user_cannot_delete_ticket(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user-delete-check@example.com',
            'phone' => '09110005555',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        [$client, $category] = $this->seedClientAndCategory();

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09110006666',
            'email' => 'requester-super-user-check@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Super user delete check',
            'description' => 'Delete permission check',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($superUser)
            ->delete(route('admin.tickets.destroy', $ticket));

        $response->assertForbidden();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_technical_user_cannot_run_destructive_bulk_actions(): void
    {
        [$technical, $ticketOne] = $this->seedTechnicalAndTicket();

        $ticketTwo = Ticket::create([
            'name' => 'Requester Two',
            'contact_number' => '09110003333',
            'email' => 'requester-two@example.com',
            'province' => 'NCR',
            'municipality' => 'Taguig',
            'subject' => 'Second ticket',
            'description' => 'Second issue',
            'priority' => 'low',
            'status' => 'open',
            'user_id' => $ticketOne->user_id,
            'category_id' => $ticketOne->category_id,
        ]);

        $response = $this->actingAs($technical)
            ->from(route('admin.tickets.index'))
            ->post(route('admin.tickets.bulk-action'), [
                'action' => 'merge',
                'selected_ids' => [$ticketOne->id, $ticketTwo->id],
            ]);

        $response->assertRedirect(route('admin.tickets.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketOne->id,
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticketTwo->id,
            'status' => 'open',
        ]);
    }

    private function seedTechnicalAndTicket(): array
    {
        $technical = User::create([
            'name' => 'Tech User',
            'email' => 'tech-user@example.com',
            'phone' => '09110001111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        [$client, $category] = $this->seedClientAndCategory();

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09110004444',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Permission test ticket',
            'description' => 'Permission test',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$technical, $ticket];
    }

    private function seedClientAndCategory(): array
    {
        $client = User::create([
            'name' => 'Client User',
            'email' => 'client-ticket-perm@example.com',
            'phone' => '09110002222',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'General',
            'description' => 'General support',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        return [$client, $category];
    }
}
