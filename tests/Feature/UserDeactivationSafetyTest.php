<?php

namespace Tests\Feature;

use App\Models\CannedResponse;
use App\Models\Category;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserDeactivationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_user_route_cannot_delete_locked_user(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user-locked-delete@example.com',
            'phone' => '09110001111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $lockedClient = User::create([
            'name' => 'Locked Client',
            'email' => 'locked-client@example.com',
            'phone' => '09110002222',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
            'is_profile_locked' => true,
        ]);

        $response = $this->actingAs($superUser)
            ->delete(route('admin.users.destroy', $lockedClient));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('error', 'Locked users cannot be deleted. Unlock the profile first.');

        $this->assertDatabaseHas('users', [
            'id' => $lockedClient->id,
            'email' => 'locked-client@example.com',
        ]);
    }

    public function test_destroy_user_route_deletes_user_and_preserves_ticket_history(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'email' => 'super-user-preserve@example.com',
            'phone' => '09110001111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Preserve',
            'email' => 'client-preserve@example.com',
            'phone' => '09110002222',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Account',
            'description' => 'Account issue',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Client Preserve',
            'contact_number' => '09110002222',
            'email' => 'client-preserve@example.com',
            'province' => 'NCR',
            'municipality' => 'Quezon City',
            'subject' => 'Need account help',
            'description' => 'Issue details',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $client->id,
            'message' => 'Initial client update',
            'is_internal' => false,
        ]);

        $response = $this->actingAs($superUser)
            ->delete(route('admin.users.destroy', $client));

        $response->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $client->id,
        ]);

        $archivedClient = User::where('email', 'deleted.client@system.local')->firstOrFail();

        $this->assertFalse((bool) $archivedClient->is_active);
        $this->assertSame(User::ROLE_CLIENT, $archivedClient->role);

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'user_id' => $archivedClient->id,
        ]);

        $this->assertDatabaseHas('ticket_replies', [
            'id' => $reply->id,
            'ticket_id' => $ticket->id,
            'user_id' => $archivedClient->id,
        ]);
    }

    public function test_destroy_user_route_preserves_closed_by_history_for_deleted_support_user(): void
    {
        $shadow = User::create([
            'name' => 'Shadow User',
            'email' => 'shadow-preserve-closed-by@example.com',
            'phone' => '09110003333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Closer Technical',
            'email' => 'closer-technical@example.com',
            'phone' => '09110004444',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Closed By',
            'email' => 'client-closed-by@example.com',
            'phone' => '09110005555',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Closed By Preservation',
            'description' => 'Preserve closed_by history',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Closed Requester',
            'contact_number' => '09110006666',
            'email' => 'closed-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Closed ticket',
            'description' => 'Closed ticket details',
            'priority' => 'medium',
            'status' => 'closed',
            'user_id' => $client->id,
            'assigned_to' => $technical->id,
            'category_id' => $category->id,
            'resolved_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
            'closed_by' => $technical->id,
        ]);
        $ticket->assignedUsers()->sync([$technical->id]);

        $response = $this->actingAs($shadow)
            ->delete(route('admin.users.destroy', $technical));

        $response->assertRedirect(route('admin.users.index'));

        $archivedSupport = User::where('email', 'deleted.support@system.local')->firstOrFail();

        $ticket->refresh();
        $this->assertSame($archivedSupport->id, (int) $ticket->closed_by);
    }

    public function test_destroy_user_route_keeps_the_knowledge_articles_and_canned_responses_they_wrote(): void
    {
        $shadow = User::create([
            'name' => 'Shadow User',
            'email' => 'shadow-keeps-content@example.com',
            'phone' => '09110005555',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $author = User::create([
            'name' => 'Article Author',
            'email' => 'article-author@example.com',
            'phone' => '09110006666',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $article = KnowledgeArticle::create([
            'created_by_user_id' => $author->id,
            'category' => 'faq',
            'title' => 'How to reset a printer',
            'slug' => 'how-to-reset-a-printer',
            'body' => 'Turn it off and on again.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $cannedResponse = CannedResponse::create([
            'created_by_user_id' => $author->id,
            'title' => 'Thanks for waiting',
            'body' => 'Thank you for waiting.',
        ]);

        $this->actingAs($shadow)
            ->delete(route('admin.users.destroy', $author))
            ->assertRedirect(route('admin.users.index'));

        $this->assertNull(User::find($author->id));
        $archivedSupport = User::where('email', 'deleted.support@system.local')->firstOrFail();
        $this->assertSame($archivedSupport->id, (int) $article->fresh()?->created_by_user_id);
        $this->assertTrue((bool) $article->fresh()->is_published);
        $this->assertSame($archivedSupport->id, (int) $cannedResponse->fresh()?->created_by_user_id);
    }
}
