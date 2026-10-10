<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemPerformanceGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_index_stays_within_query_budget(): void
    {
        [$adminUser, $technicalUser] = $this->createSupportUsers();
        $categories = $this->seedCategories();
        $this->seedTicketDataset($technicalUser, $categories, 24);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($adminUser)->get(route('admin.tickets.index'));

        $response->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            40,
            $queryCount,
            "Admin ticket index query budget exceeded. Queries executed: {$queryCount}"
        );
    }

    public function test_admin_reports_index_stays_within_query_budget(): void
    {
        [$adminUser, $technicalUser] = $this->createSupportUsers();
        $categories = $this->seedCategories();
        $this->seedTicketDataset($technicalUser, $categories, 28, true);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($adminUser)->get(route('admin.reports.index'));

        $response->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            38,
            $queryCount,
            "Admin reports index query budget exceeded. Queries executed: {$queryCount}"
        );
    }

    public function test_admin_users_index_stays_within_query_budget(): void
    {
        [$adminUser] = $this->createSupportUsers();

        for ($index = 1; $index <= 18; $index++) {
            User::create([
                'name' => "Managed User {$index}",
                'email' => "managed-user-{$index}@example.com",
                'phone' => '0916'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                'department' => $index % 2 === 0 ? 'Hoshishi' : 'Hoshishi',
                'role' => $index % 3 === 0 ? User::ROLE_CLIENT : User::ROLE_TECHNICAL,
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($adminUser)->get(route('admin.users.index'));

        $response->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            25,
            $queryCount,
            "Admin users index query budget exceeded. Queries executed: {$queryCount}"
        );
    }

    public function test_admin_ticket_show_stays_within_query_budget(): void
    {
        [$adminUser, $technicalUser] = $this->createSupportUsers();
        $categories = $this->seedCategories();
        $this->seedTicketDataset($technicalUser, $categories, 8, true);

        $ticket = Ticket::query()->latest('id')->firstOrFail();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($adminUser)->get(route('admin.tickets.show', $ticket));

        $response->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            22,
            $queryCount,
            "Admin ticket show query budget exceeded. Queries executed: {$queryCount}"
        );
    }

    public function test_client_ticket_index_stays_within_query_budget(): void
    {
        [$adminUser, $technicalUser] = $this->createSupportUsers();
        $categories = $this->seedCategories();
        $this->seedTicketDataset($technicalUser, $categories, 18);

        $client = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->orderBy('id')
            ->firstOrFail();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($client)->get(route('client.tickets.index'));

        $response->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            12,
            $queryCount,
            "Client ticket index query budget exceeded. Queries executed: {$queryCount}"
        );
    }

    private function createSupportUsers(): array
    {
        $adminUser = User::create([
            'name' => 'Performance Admin',
            'email' => 'performance-admin@example.com',
            'phone' => '09170000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technicalUser = User::create([
            'name' => 'Performance Technician',
            'email' => 'performance-tech@example.com',
            'phone' => '09170000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        return [$adminUser, $technicalUser];
    }

    private function seedCategories(): array
    {
        return [
            Category::create([
                'name' => 'Network',
                'description' => 'Network incidents',
                'color' => '#0f8d88',
                'is_active' => true,
            ]),
            Category::create([
                'name' => 'Access',
                'description' => 'Access incidents',
                'color' => '#ec6e4c',
                'is_active' => true,
            ]),
            Category::create([
                'name' => 'Hardware',
                'description' => 'Hardware incidents',
                'color' => '#1d4ed8',
                'is_active' => true,
            ]),
        ];
    }

    private function seedTicketDataset(User $technicalUser, array $categories, int $ticketCount, bool $includeResolved = false): void
    {
        for ($index = 1; $index <= $ticketCount; $index++) {
            $client = User::create([
                'name' => "Performance Client {$index}",
                'email' => "performance-client-{$index}@example.com",
                'phone' => '0918'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                'department' => 'Hoshishi',
                'role' => User::ROLE_CLIENT,
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]);

            $status = $includeResolved && $index % 4 === 0 ? 'resolved' : 'open';
            $ticket = Ticket::create([
                'name' => "Requester {$index}",
                'contact_number' => '0919'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                'email' => "requester-{$index}@example.com",
                'province' => $index % 2 === 0 ? 'NCR' : 'Laguna',
                'municipality' => $index % 2 === 0 ? 'Pasig' : 'Calamba',
                'subject' => "Performance test ticket {$index}",
                'description' => "Generated performance dataset ticket {$index}",
                'priority' => $index % 3 === 0 ? 'high' : 'medium',
                'status' => $status,
                'resolved_at' => $status === 'resolved' ? now()->subHours(2) : null,
                'assigned_to' => $technicalUser->id,
                'assigned_at' => now()->subHours(1),
                'user_id' => $client->id,
                'category_id' => $categories[$index % count($categories)]->id,
            ]);

            if ($index % 2 === 0) {
                TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $client->id,
                    'message' => "Reply for performance ticket {$index}",
                    'is_internal' => false,
                ]);
            }
        }
    }
}
