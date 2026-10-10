<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModalConfirmationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_show_revert_modal_requires_checkbox_before_submit(): void
    {
        [$supportUser, $ticket] = $this->createSupportUserAndTicket();

        $ticket->update([
            'status' => 'closed',
            'resolved_at' => Carbon::now()->subHour(),
            'closed_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('id="revert_confirm" type="checkbox"', false);
        $response->assertSee('id="revert_submit" type="submit" class="btn-primary disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:brightness-100" disabled', false);
    }

    public function test_admin_ticket_index_bulk_delete_modal_requires_checkbox_before_submit(): void
    {
        [$supportUser] = $this->createSupportUserAndTicket();

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee('id="bulk-delete-confirm-checkbox" type="checkbox"', false);
        $response->assertSee('id="bulk-delete-confirm-submit" type="button" class="btn-danger disabled:cursor-not-allowed disabled:opacity-60" disabled', false);
    }

    public function test_admin_ticket_index_single_delete_modal_requires_checkbox_before_submit(): void
    {
        [$supportUser] = $this->createSupportUserAndTicket();

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index'));

        $response->assertOk();
        $response->assertSee('id="delete-confirm-checkbox" type="checkbox"', false);
        $response->assertSee('id="delete-confirm-submit" type="submit" class="btn-danger disabled:cursor-not-allowed disabled:opacity-60" disabled', false);
    }

    public function test_admin_users_index_status_and_delete_modals_require_checkbox_before_submit(): void
    {
        config(['legal.require_acceptance' => false]);

        $supportUser = User::create([
            'name' => 'Users Modal Admin',
            'email' => 'users-modal-admin@example.com',
            'phone' => '09124440001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Managed Client',
            'email' => 'users-modal-client@example.com',
            'phone' => '09124440002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($supportUser)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee('id="statusConfirmCheckbox" type="checkbox"', false);
        $response->assertSee('id="confirmStatusChange" type="button" class="btn-primary w-full disabled:cursor-not-allowed disabled:opacity-60" disabled', false);
        $response->assertSee('id="deleteConfirmCheckbox" type="checkbox" required aria-required="true" class="mr-2 ticket-checkbox"', false);
        $response->assertSee('id="confirmDelete" disabled class="btn-danger w-full disabled:cursor-not-allowed disabled:opacity-60"', false);
    }

    private function createSupportUserAndTicket(): array
    {
        config(['legal.require_acceptance' => false]);

        $supportUser = User::create([
            'name' => 'Modal Support User',
            'email' => 'modal-support@example.com',
            'phone' => '09113330001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Modal Client',
            'email' => 'modal-client@example.com',
            'phone' => '09113330002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Modal Category',
            'description' => 'Modal checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Modal Requester',
            'contact_number' => '09113330003',
            'email' => 'modal-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Modal confirmation test ticket',
            'description' => 'Checking modal confirmation controls.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        return [$supportUser, $ticket];
    }
}
