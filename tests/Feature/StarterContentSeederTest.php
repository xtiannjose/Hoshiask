<?php

namespace Tests\Feature;

use App\Enums\KnowledgeArticleCategory;
use App\Models\CannedResponse;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\TicketStatusWorkflowService;
use App\Support\UploadLimits;
use Database\Seeders\StarterContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class StarterContentSeederTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private function admin(): User
    {
        return $this->createUser('Admin', 'admin-starter@example.com', User::ROLE_ADMIN);
    }

    public function test_it_adds_seven_canned_responses_and_seven_published_articles(): void
    {
        $admin = $this->admin();

        $this->seed(StarterContentSeeder::class);

        $this->assertSame(7, CannedResponse::count());
        $this->assertSame(7, KnowledgeArticle::count());
        $this->assertSame(7, KnowledgeArticle::published()->count());
        $this->assertSame(0, CannedResponse::where('created_by_user_id', '!=', $admin->id)->count());
        $this->assertSame(0, KnowledgeArticle::where('created_by_user_id', '!=', $admin->id)->count());
    }

    /**
     * The entries state facts about the app. Tie the ones that can be checked to the code, so changing a rule
     * (or the wording of a screen) without updating the starter content fails here instead of misleading people.
     */
    public function test_the_content_matches_how_the_app_really_behaves(): void
    {
        $seeder = new StarterContentSeeder;
        $articles = collect($seeder->knowledgeArticles())->keyBy('title');
        $canned = $seeder->cannedResponses();

        // Clients cannot open Account Settings, so nothing aimed at them may send them there.
        $client = $this->createClient();
        $this->actingAs($client)->get(route('account.settings'))->assertForbidden();
        $this->assertStringNotContainsString('Account Settings', $canned['Password reset completed']);
        foreach ($articles as $title => $article) {
            if ($title !== 'Keeping your account secure') {
                $this->assertStringNotContainsString('Account Settings', $article['body'], $title);
            }
        }
        $this->assertStringContainsString('Staff accounts:', $articles['Keeping your account secure']['body']);

        // The client sidebar calls the ticket list "My Tickets".
        $navigation = (string) file_get_contents(resource_path('views/layouts/navigation.blade.php'));
        $this->assertStringContainsString("'label' => 'My Tickets'", $navigation);
        foreach ($articles as $title => $article) {
            $this->assertStringNotContainsString('"Tickets" in the sidebar', $article['body'], $title);
        }

        // Numbers: attachment size, automatic close, reminder, reopen window.
        $this->assertStringContainsString((UploadLimits::APP_MAX_KB / 1024).' MB', $articles['Which files can I attach to a ticket, and how big can they be?']['body']);
        $closing = $articles['How resolved tickets are confirmed and closed']['body'];
        $this->assertStringContainsString('after '.Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS.' days', $closing);
        $this->assertStringContainsString(Ticket::AUTO_CLOSE_WARNING_DAYS_BEFORE.' days before', $closing);
        $this->assertStringContainsString('after '.Ticket::AUTO_CLOSE_RESOLVED_AFTER_DAYS.' days', $canned['Issue resolved: please confirm']);
        $reopenWindow = (new \ReflectionClassConstant(TicketStatusWorkflowService::class, 'CLOSED_REVERT_WINDOW_DAYS'))->getValue();
        $this->assertStringContainsString("within {$reopenWindow} days", $closing);
    }

    public function test_every_entry_fits_the_limits_the_forms_enforce(): void
    {
        $this->admin();

        $this->seed(StarterContentSeeder::class);

        foreach (CannedResponse::all() as $response) {
            $this->assertLessThanOrEqual(255, mb_strlen($response->title), $response->title);
            $this->assertLessThanOrEqual(5000, mb_strlen($response->body), $response->title);
            $this->assertStringNotContainsString('[', $response->body, "{$response->title} must be ready to send, without placeholders to fill in.");
        }

        foreach (KnowledgeArticle::all() as $article) {
            $this->assertLessThanOrEqual(255, mb_strlen($article->title), $article->title);
            $this->assertLessThanOrEqual(20000, mb_strlen($article->body), $article->title);
            $this->assertContains($article->category, KnowledgeArticleCategory::values(), $article->title);
            $this->assertNotSame('', $article->slug);
        }

        $this->assertSame(KnowledgeArticle::count(), KnowledgeArticle::distinct()->count('slug'));
    }

    public function test_running_it_again_adds_nothing_and_keeps_staff_edits(): void
    {
        $this->admin();
        $this->seed(StarterContentSeeder::class);

        CannedResponse::where('title', 'Ticket received')->update(['body' => 'Our own wording.']);
        KnowledgeArticle::where('title', 'How to submit a support ticket')->update(['body' => 'Rewritten by staff.', 'is_published' => false]);

        $this->seed(StarterContentSeeder::class);

        $this->assertSame(7, CannedResponse::count());
        $this->assertSame(7, KnowledgeArticle::count());
        $this->assertSame('Our own wording.', CannedResponse::where('title', 'Ticket received')->value('body'));
        $article = KnowledgeArticle::where('title', 'How to submit a support ticket')->first();
        $this->assertSame('Rewritten by staff.', $article->body);
        $this->assertFalse($article->is_published);
    }

    public function test_it_only_adds_what_is_missing(): void
    {
        $admin = $this->admin();
        CannedResponse::create(['created_by_user_id' => $admin->id, 'title' => 'Ticket received', 'body' => 'Existing.']);

        $this->seed(StarterContentSeeder::class);

        $this->assertSame(7, CannedResponse::count());
        $this->assertSame('Existing.', CannedResponse::where('title', 'Ticket received')->value('body'));
    }

    public function test_without_an_admin_it_adds_nothing_and_does_not_fail(): void
    {
        $this->createUser('Tech', 'tech-starter@example.com', User::ROLE_TECHNICAL);
        $this->createUser('Inactive admin', 'inactive-admin-starter@example.com', User::ROLE_ADMIN, 'Hoshishi', ['is_active' => false]);

        $this->seed(StarterContentSeeder::class);

        $this->assertSame(0, CannedResponse::count());
        $this->assertSame(0, KnowledgeArticle::count());
    }

    public function test_a_super_user_is_used_when_there_is_no_admin(): void
    {
        $superUser = $this->createSupportUser();

        $this->seed(StarterContentSeeder::class);

        $this->assertSame(7, CannedResponse::where('created_by_user_id', $superUser->id)->count());
    }

    public function test_clients_can_read_the_articles_and_staff_can_pick_the_responses(): void
    {
        $admin = $this->admin();
        $client = $this->createClient();
        $this->seed(StarterContentSeeder::class);

        $this->actingAs($client)->get(route('client.knowledge-base.index'))
            ->assertOk()
            ->assertSee('How to submit a support ticket')
            ->assertSee('What do the ticket statuses mean?');

        $article = KnowledgeArticle::where('title', 'How resolved tickets are confirmed and closed')->firstOrFail();
        $this->actingAs($client)->get(route('client.knowledge-base.show', $article))
            ->assertOk()
            ->assertSee('closed automatically after 7 days');

        $this->actingAs($admin)->getJson(route('admin.canned-responses.list'))
            ->assertOk()
            ->assertJsonCount(7)
            ->assertJsonFragment(['title' => 'Password reset completed']);
    }
}
