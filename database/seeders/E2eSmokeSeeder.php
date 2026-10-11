<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eSmokeSeeder extends Seeder
{
    public function run(): void
    {
        // The forms list departments and categories; a migration no longer inserts them.
        $this->call(StarterDataSeeder::class);

        $superUser = User::updateOrCreate(
            ['email' => 'e2e-super@example.com'],
            [
                'name' => 'E2E Super User',
                'phone' => '09170000999',
                'department' => 'Hoshishi',
                'role' => User::ROLE_SUPER_USER,
                'password' => Hash::make('Playwright123!'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $client = User::updateOrCreate(
            ['email' => 'e2e-client@example.com'],
            [
                'name' => 'E2E Client',
                'phone' => '09180000999',
                'department' => 'Hoshishi',
                'role' => User::ROLE_CLIENT,
                'password' => Hash::make('Playwright123!'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $category = Category::updateOrCreate(
            ['name' => 'E2E Category'],
            [
                'description' => 'Browser smoke coverage category',
                'color' => '#0f8d88',
                'is_active' => true,
            ]
        );

        $ticket = Ticket::updateOrCreate(
            ['ticket_number' => 'TK-E2E-SMOKE-001'],
            [
                'name' => 'E2E Requester',
                'contact_number' => '09190000999',
                'email' => 'e2e-requester@example.com',
                'province' => 'NCR',
                'municipality' => 'Pasig',
                'subject' => 'E2E smoke ticket',
                'description' => 'Seeded ticket for authenticated browser smoke coverage.',
                'priority' => 'medium',
                'status' => 'resolved',
                'resolved_at' => now()->subDay(),
                'closed_at' => null,
                'assigned_to' => $superUser->id,
                'assigned_at' => now()->subDays(2),
                'user_id' => $client->id,
                'category_id' => $category->id,
            ]
        );

        $ticket->assignedUsers()->sync([$superUser->id]);

        KnowledgeArticle::updateOrCreate(
            ['slug' => 'e2e-popup-article'],
            [
                'created_by_user_id' => $superUser->id,
                'category' => 'faq',
                'title' => 'E2E popup article',
                'body' => 'The seeded article has some text for the popup to show.',
                'is_published' => true,
                'published_at' => now(),
            ]
        );
    }
}
