<?php

namespace Tests\Traits;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

trait CreatesTestData
{
    protected function createUser(
        string $name,
        string $email,
        string $role,
        string $department = 'Hoshishi',
        array $overrides = [],
    ): User {
        return User::create(array_merge([
            'name' => $name,
            'email' => $email,
            'phone' => '09170000000',
            'department' => $department,
            'role' => $role,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ], $overrides));
    }

    protected function createClient(
        string $name = 'Test Client',
        string $email = 'test-client@example.com',
        array $overrides = [],
    ): User {
        return $this->createUser($name, $email, User::ROLE_CLIENT, 'Hoshishi', $overrides);
    }

    protected function createSupportUser(
        string $name = 'Support User',
        string $email = 'support-user@example.com',
        array $overrides = [],
    ): User {
        return $this->createUser($name, $email, User::ROLE_SUPER_USER, 'Hoshishi', $overrides);
    }

    protected function createAssignedSupportUser(
        string $name = 'Technical User',
        string $email = 'technical-user@example.com',
        array $overrides = [],
    ): User {
        return $this->createUser($name, $email, User::ROLE_TECHNICAL, 'Hoshishi', $overrides);
    }

    protected function createCategory(
        string $name = 'Test Category',
        array $overrides = [],
    ): Category {
        return Category::create(array_merge([
            'name' => $name,
            'description' => 'Test category',
            'color' => '#0f8d88',
            'is_active' => true,
        ], $overrides));
    }

    protected function createTicket(
        User $client,
        Category $category,
        array $overrides = [],
    ): Ticket {
        return Ticket::create(array_merge([
            'name' => 'Ticket Requester',
            'contact_number' => '09185551234',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Test ticket subject',
            'description' => 'Test ticket body',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ], $overrides));
    }
}
