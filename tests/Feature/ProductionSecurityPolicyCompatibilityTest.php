<?php

namespace Tests\Feature;

use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * In production the Content-Security-Policy is enforced (config/security.php): no inline style attributes,
 * no inline event handlers and no inline scripts. Locally the same policy only reports, so a violation looks
 * fine on a developer machine and then breaks on the live site. These tests catch that early.
 */
class ProductionSecurityPolicyCompatibilityTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /**
     * Blade views served as web pages. Emails and PDFs are not subject to the page policy and legitimately
     * use inline styles.
     *
     * @return array<string, string> path => contents
     */
    private function pageViews(): array
    {
        $views = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.blade.php') || str_contains($path, '/views/emails/') || str_contains($path, 'pdf')) {
                continue;
            }
            $views[substr($path, strlen(str_replace('\\', '/', resource_path('views'))) + 1)] = (string) file_get_contents($path);
        }

        return $views;
    }

    public function test_no_page_view_uses_an_inline_style_attribute(): void
    {
        $offenders = [];
        foreach ($this->pageViews() as $name => $contents) {
            if (preg_match('/<[a-z][^>]*\sstyle\s*=/i', $contents) === 1) {
                $offenders[] = $name;
            }
        }

        $this->assertSame([], $offenders, 'Inline style="" attributes are blocked by the production policy. Use classes, or set the property from a script. Found in: '.implode(', ', $offenders));
    }

    public function test_no_page_view_uses_an_inline_event_handler(): void
    {
        $offenders = [];
        foreach ($this->pageViews() as $name => $contents) {
            if (preg_match('/<[a-z][^>]*\son[a-z]+\s*=\s*["\']/i', $contents) === 1) {
                $offenders[] = $name;
            }
        }

        $this->assertSame([], $offenders, 'Inline onclick/onsubmit/... handlers are blocked by the production policy (the code never runs). Use a data attribute and a script. Found in: '.implode(', ', $offenders));
    }

    public function test_no_page_view_contains_an_inline_script_that_would_run(): void
    {
        $offenders = [];
        foreach ($this->pageViews() as $name => $contents) {
            if (preg_match_all('/<script\b([^>]*)>/i', $contents, $matches) === false) {
                continue;
            }
            foreach ($matches[1] as $attributes) {
                $isExternal = stripos($attributes, 'src=') !== false;
                $isData = stripos($attributes, 'application/json') !== false;
                if (! $isExternal && ! $isData) {
                    $offenders[] = $name;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($offenders)), 'Inline <script> code is blocked by the production policy.');
    }

    public function test_no_script_builds_inline_styles_or_handlers_as_html_strings(): void
    {
        $offenders = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'), RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.js') || str_ends_with($path, '.test.js')) {
                continue;
            }
            // Comments may describe the problem; only real code counts.
            $code = (string) preg_replace('#/\*.*?\*/|^\s*//[^\n]*$#sm', '', (string) file_get_contents($path));
            if (preg_match('/style=["\\\\]|\bon(click|change|submit|load|error)=["\\\\]|setAttribute\(\s*[\'"]style[\'"]/i', $code) === 1) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame([], $offenders, 'Scripts must not create inline style="" or on...="" markup (blocked in production): '.implode(', ', $offenders));
    }

    public function test_production_enforces_the_policy_and_other_environments_only_report(): void
    {
        $this->get(route('login'))->assertHeader('Content-Security-Policy-Report-Only');

        $this->app['env'] = 'production';
        $response = $this->get(route('login'));

        $this->assertStringContainsString("style-src-attr 'none'", (string) $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src-attr 'none'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_the_dashboard_chart_bars_get_their_height_from_a_class_not_a_style_or_a_script(): void
    {
        $admin = $this->createUser('Admin', 'admin-policy@example.com', User::ROLE_ADMIN);
        $this->createTicket($this->createClient(), $this->createCategory());

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        // Bars rise from the bottom of the chart and are sized by a h-[N%] class (no inline style, no script),
        // so they are already the right height at first paint.
        $this->assertMatchesRegularExpression('/<div class="group relative flex h-full flex-1 flex-col justify-end">\s*<div class="h-\[\d+%\] rounded-t/', $html);
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*h-\[\d+%\][^>]*style=/', $html);
        $this->assertStringContainsString('@source inline("h-[{4..100}%]")', (string) file_get_contents(resource_path('css/app.css')));
    }

    public function test_deleting_a_knowledge_article_asks_for_confirmation_in_the_shared_dialog_without_inline_handlers(): void
    {
        $admin = $this->createUser('Admin', 'admin-policy-kb@example.com', User::ROLE_ADMIN);
        KnowledgeArticle::create([
            'title' => 'Reset a password',
            'slug' => 'reset-a-password',
            'body' => 'Steps.',
            'category' => 'other',
            'is_published' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('admin.knowledge-base.index'))
            ->assertOk()
            ->assertSee('data-delete-confirm', false)
            ->assertSee('id="delete-confirm-modal"', false)
            ->assertDontSee('onsubmit=', false)
            ->assertDontSee('onclick=', false);
    }
}
