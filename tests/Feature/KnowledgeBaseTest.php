<?php

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class KnowledgeBaseTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_admin_can_create_and_publish_an_article(): void
    {
        $admin = $this->createUser('Admin User', 'admin-kb-create@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)
            ->post(route('admin.knowledge-base.store'), [
                'category' => 'faq',
                'title' => 'How do I reset my password?',
                'body' => 'Go to account settings and click reset password.',
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.knowledge-base.index'));

        $article = KnowledgeArticle::query()->where('title', 'How do I reset my password?')->firstOrFail();
        $this->assertTrue($article->is_published);
        $this->assertSame('faq', $article->category);
        $this->assertNotNull($article->slug);
        $this->assertNotNull($article->published_at);
    }

    public function test_technician_cannot_create_an_article(): void
    {
        $technician = $this->createAssignedSupportUser();

        $this->actingAs($technician)
            ->post(route('admin.knowledge-base.store'), [
                'category' => 'faq',
                'title' => 'Should not be allowed',
                'body' => 'Body text.',
            ])
            ->assertForbidden();
    }

    public function test_technician_can_browse_published_articles(): void
    {
        $technician = $this->createAssignedSupportUser();
        $admin = $this->createUser('Admin Author', 'admin-kb-author@example.com', User::ROLE_ADMIN);
        KnowledgeArticle::create([
            'created_by_user_id' => $admin->id,
            'category' => 'policy',
            'title' => 'Visible Policy Article',
            'slug' => 'visible-policy-article',
            'body' => 'Policy content.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($technician)
            ->get(route('admin.knowledge-base.index'))
            ->assertOk()
            ->assertSee('Visible Policy Article');
    }

    public function test_technician_does_not_see_draft_articles(): void
    {
        $technician = $this->createAssignedSupportUser();
        $admin = $this->createUser('Admin Author', 'admin-kb-author2@example.com', User::ROLE_ADMIN);
        KnowledgeArticle::create([
            'created_by_user_id' => $admin->id,
            'category' => 'policy',
            'title' => 'Hidden Draft Article',
            'slug' => 'hidden-draft-article',
            'body' => 'Draft content.',
            'is_published' => false,
        ]);

        $this->actingAs($technician)
            ->get(route('admin.knowledge-base.index'))
            ->assertOk()
            ->assertDontSee('Hidden Draft Article');
    }

    public function test_client_can_browse_and_read_published_articles(): void
    {
        $client = $this->createClient();
        $admin = $this->createUser('Admin Author', 'admin-kb-author3@example.com', User::ROLE_ADMIN);
        $article = KnowledgeArticle::create([
            'created_by_user_id' => $admin->id,
            'category' => 'faq',
            'title' => 'Client Visible FAQ',
            'slug' => 'client-visible-faq',
            'body' => 'FAQ content for clients.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('client.knowledge-base.index'))
            ->assertOk()
            ->assertSee('Client Visible FAQ');

        $this->actingAs($client)
            ->get(route('client.knowledge-base.show', $article))
            ->assertOk()
            ->assertSee('FAQ content for clients.');
    }

    public function test_client_cannot_view_a_draft_article_directly(): void
    {
        $client = $this->createClient();
        $admin = $this->createUser('Admin Author', 'admin-kb-author4@example.com', User::ROLE_ADMIN);
        $draft = KnowledgeArticle::create([
            'created_by_user_id' => $admin->id,
            'category' => 'faq',
            'title' => 'Draft Not For Clients',
            'slug' => 'draft-not-for-clients',
            'body' => 'Draft content.',
            'is_published' => false,
        ]);

        $this->actingAs($client)
            ->get(route('client.knowledge-base.show', $draft))
            ->assertNotFound();
    }

    public function test_admin_can_update_and_delete_an_article(): void
    {
        $admin = $this->createUser('Admin User', 'admin-kb-edit@example.com', User::ROLE_ADMIN);
        $article = KnowledgeArticle::create([
            'created_by_user_id' => $admin->id,
            'category' => 'guide',
            'title' => 'Original Title',
            'slug' => 'original-title',
            'body' => 'Original body.',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.knowledge-base.update', $article), [
                'category' => 'guide',
                'title' => 'Updated Title',
                'body' => 'Updated body.',
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.knowledge-base.index'));

        $article->refresh();
        $this->assertSame('Updated Title', $article->title);
        $this->assertTrue($article->is_published);

        $this->actingAs($admin)
            ->delete(route('admin.knowledge-base.destroy', $article))
            ->assertRedirect(route('admin.knowledge-base.index'));

        $this->assertDatabaseMissing('knowledge_articles', ['id' => $article->id]);
    }
}
