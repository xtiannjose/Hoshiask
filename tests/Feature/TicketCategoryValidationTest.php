<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TicketCategoryValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
    }

    public function test_client_ticket_submission_rejects_inactive_category(): void
    {
        $client = $this->createUser('Inactive Category Client', 'inactive-category-client@example.com', User::ROLE_CLIENT, 'Juniper');
        $inactiveCategory = $this->createCategory(isActive: false);

        $response = $this->actingAs($client)
            ->from(route('client.tickets.create'))
            ->post(route('client.tickets.store'), [
                'name' => 'Inactive Category Client',
                'contact_number' => '09170010000',
                'email' => 'inactive-category-client@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Inactive category client ticket',
                'description' => 'The category should not be accepted.',
                'category_id' => $inactiveCategory->id,
                'ticket_consent' => '1',
                'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
            ]);

        $response->assertRedirect(route('client.tickets.create'));
        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_admin_ticket_creation_rejects_inactive_category(): void
    {
        $superUser = $this->createUser('Inactive Category Super User', 'inactive-category-super@example.com', User::ROLE_SUPER_USER);
        $client = $this->createUser('Inactive Category Requester', 'inactive-category-requester@example.com', User::ROLE_CLIENT, 'Juniper');
        $inactiveCategory = $this->createCategory(isActive: false);

        $response = $this->actingAs($superUser)
            ->from(route('admin.tickets.create'))
            ->post(route('admin.tickets.store'), [
                'user_id' => $client->id,
                'name' => 'Inactive Category Requester',
                'contact_number' => '09170010001',
                'email' => 'inactive-category-requester@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'Inactive category admin ticket',
                'description' => 'The category should not be accepted.',
                'category_id' => $inactiveCategory->id,
                'ticket_type' => 'external',
            ]);

        $response->assertRedirect(route('admin.tickets.create'));
        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('tickets', 0);
    }

    private function createUser(string $name, string $email, string $role, string $department = 'Hoshishi'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09179990000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(bool $isActive): Category
    {
        return Category::create([
            'name' => $isActive ? 'Active Validation Category' : 'Inactive Validation Category',
            'description' => 'Ticket category validation fixture',
            'color' => '#0f8d88',
            'is_active' => $isActive,
        ]);
    }
}
