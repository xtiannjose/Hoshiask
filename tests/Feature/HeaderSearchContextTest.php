<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HeaderSearchContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_users_page_header_search_points_to_users_index(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Ana']));

        $response->assertOk();
        $response->assertSee('action="'.route('admin.users.index').'"', false);
        $response->assertSee('name="search"', false);
        $response->assertSee('value="Ana"', false);
    }

    public function test_admin_tickets_header_search_carries_non_search_query_params(): void
    {
        $superUser = $this->createSuperUser();

        $response = $this->actingAs($superUser)
            ->get(route('admin.tickets.index', [
                'tab' => 'history',
                'priority' => 'urgent',
                'search' => 'router',
            ]));

        $response->assertOk();
        $response->assertSee('action="'.route('admin.tickets.index').'"', false);
        $response->assertSee('name="priority" value="urgent"', false);
        $response->assertSee('name="tab" value="history"', false);
    }

    public function test_client_dashboard_header_search_points_to_client_tickets_index(): void
    {
        $client = $this->createClient();

        $response = $this->actingAs($client)
            ->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertSee('action="'.route('client.tickets.index').'"', false);
    }

    private function createSuperAdmin(): User
    {
        return User::create([
            'name' => 'Header Super Admin',
            'email' => 'header-super-admin@example.com',
            'phone' => '09140000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createSuperUser(): User
    {
        return User::create([
            'name' => 'Header Super User',
            'email' => 'header-super-user@example.com',
            'phone' => '09140000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createClient(): User
    {
        return User::create([
            'name' => 'Header Client',
            'email' => 'header-client@example.com',
            'phone' => '09140000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }
}
