<?php

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The Knowledge Base list opens an article in a popup. The rows must stay real links to the article page (so the
 * page still works without JavaScript or with "open in new tab"), and each article's content must be available to
 * the popup without a second request.
 */
class KnowledgeBasePopupTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private function article(User $author, array $overrides = []): KnowledgeArticle
    {
        static $counter = 0;
        $counter++;

        return KnowledgeArticle::create(array_merge([
            'created_by_user_id' => $author->id,
            'category' => 'faq',
            'title' => "Popup article {$counter}",
            'slug' => "popup-article-{$counter}",
            'body' => "Body of article {$counter}.",
            'is_published' => true,
            'published_at' => now(),
        ], $overrides));
    }

    public function test_client_rows_are_links_to_the_article_and_carry_the_popup_content(): void
    {
        $author = $this->createUser('Author', 'popup-author@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $article = $this->article($author, ['title' => 'Reset my router', 'body' => 'Unplug it for ten seconds.']);

        $response = $this->actingAs($client)->get(route('client.knowledge-base.index'))->assertOk();

        $response->assertSee('href="'.route('client.knowledge-base.show', $article).'"', false);
        $response->assertSee('data-kb-article="kb-article-'.$article->id.'"', false);
        $response->assertSee('<template id="kb-article-'.$article->id.'">', false);
        $response->assertSee('Unplug it for ten seconds.');
        $response->assertSee('id="kb-article-modal"', false);
        $response->assertSee('data-modal-overlay', false);
        $response->assertSee('data-modal-close', false);
        $response->assertSee('data-knowledge-base-page', false);
        // Staff-only details never reach a client, not even inside the hidden popup content.
        $response->assertDontSee('By Author');
        $response->assertDontSee('Edit Article');
    }

    public function test_a_client_never_receives_the_content_of_a_draft(): void
    {
        $author = $this->createUser('Author', 'popup-author2@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $this->article($author, ['title' => 'Public one']);
        $draft = $this->article($author, ['title' => 'Secret draft', 'body' => 'Not ready yet.', 'is_published' => false, 'published_at' => null]);

        $response = $this->actingAs($client)->get(route('client.knowledge-base.index'))->assertOk();

        $response->assertSee('Public one');
        $response->assertDontSee('Secret draft');
        $response->assertDontSee('Not ready yet.');
        $response->assertDontSee('kb-article-'.$draft->id, false);
    }

    public function test_article_text_is_escaped_inside_the_popup_content(): void
    {
        $author = $this->createUser('Author', 'popup-author3@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $this->article($author, [
            'title' => '<script>alert(1)</script>',
            'body' => '</template><img src=x onerror=alert(1)>',
        ]);

        $html = $this->actingAs($client)->get(route('client.knowledge-base.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('</template><img', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;/template&gt;&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_admin_rows_stretch_the_title_link_over_the_row_and_keep_edit_and_delete_clickable(): void
    {
        $admin = $this->createUser('Admin', 'popup-admin@example.com', User::ROLE_ADMIN);
        $article = $this->article($admin);

        $response = $this->actingAs($admin)->get(route('admin.knowledge-base.index'))->assertOk();

        $response->assertSee('data-kb-article="kb-article-'.$article->id.'"', false);
        $response->assertSee('after:absolute after:inset-0', false);
        $response->assertSee('relative z-10 flex shrink-0 items-center gap-1', false);
        $response->assertSee('data-confirm="Delete this article? This cannot be undone."', false);
        // Managers get the author line, a Draft marker where it applies, and an Edit button in the popup.
        $response->assertSee('By Admin');
        $response->assertSee('Edit Article');
    }

    public function test_a_technician_sees_published_articles_in_the_popup_without_an_edit_button(): void
    {
        $author = $this->createUser('Author', 'popup-author4@example.com', User::ROLE_ADMIN);
        $technician = $this->createUser('Tech', 'popup-tech@example.com', User::ROLE_TECHNICAL);
        $article = $this->article($author);
        $this->article($author, ['title' => 'Draft for admins', 'is_published' => false, 'published_at' => null]);

        $response = $this->actingAs($technician)->get(route('admin.knowledge-base.index'))->assertOk();

        $response->assertSee('kb-article-'.$article->id, false);
        $response->assertDontSee('Draft for admins');
        $response->assertDontSee('Edit Article');
    }

    public function test_the_full_article_pages_still_show_the_same_details(): void
    {
        $admin = $this->createUser('Admin', 'popup-admin2@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $draft = $this->article($admin, ['title' => 'Draft page', 'body' => 'Draft body.', 'is_published' => false, 'published_at' => null]);
        $published = $this->article($admin, ['title' => 'Published page', 'body' => 'Published body.']);

        $this->actingAs($admin)->get(route('admin.knowledge-base.show', $draft))
            ->assertOk()->assertSee('Draft')->assertSee('By Admin')->assertSee('Edit Article')->assertSee('Draft body.');

        $this->actingAs($client)->get(route('client.knowledge-base.show', $published))
            ->assertOk()->assertSee('Published page')->assertSee('Published body.')
            ->assertDontSee('By Admin')->assertDontSee('Edit Article');
    }

    public function test_dark_mode_keeps_the_page_visible_behind_popups_and_lists_readable(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // Popups wrap their content in min-h-dvh, which dark mode would otherwise paint solid.
        $this->assertStringContainsString('.theme-dark .app-modal-root .min-h-dvh', $css);
        // Row dividers and row hover colours need a dark version too.
        $this->assertStringContainsString('.theme-dark .divide-slate-100 > :not(:last-child)', $css);
        $this->assertStringContainsString('.theme-dark .hover\:bg-slate-50:hover', $css);
    }
}
