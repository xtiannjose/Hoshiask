<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Guards the things that keep a page from visibly resizing or jumping while it loads or is refreshed.
 */
class PageLoadStabilityTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_signed_in_pages_wait_until_the_whole_page_is_read_before_the_first_paint(): void
    {
        $admin = $this->createUser('Admin', 'admin-stability@example.com', User::ROLE_ADMIN);

        $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="expect" href="#page-end" blocking="render">', $html);
        $this->assertStringContainsString('<span id="page-end" hidden></span>', $html);
    }

    public function test_the_theme_toggle_icons_do_not_wait_for_the_script_to_appear(): void
    {
        foreach (['layouts/partials/app-header', 'auth/login', 'legal/public-layout'] as $view) {
            $contents = (string) file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertStringContainsString('theme-icon-sun', $contents, $view);
            $this->assertStringContainsString('theme-icon-moon', $contents, $view);
            $this->assertDoesNotMatchRegularExpression('/<svg[^>]*x-cloak[^>]*:class="darkMode/', $contents, "{$view}: the icon must follow the html theme class, not Alpine state.");
        }

        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('html.theme-dark .theme-icon-sun', $css);
        $this->assertStringContainsString('html.theme-dark .theme-icon-moon', $css);
    }

    public function test_a_refresh_skips_the_entrance_animation(): void
    {
        $script = (string) file_get_contents(public_path('js/theme-initializer.js'));
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("type !== 'navigate'", $script);
        $this->assertStringContainsString('no-enter-motion', $script);
        $this->assertStringContainsString('html.no-enter-motion .motion-page-enter', $css);
    }

    public function test_a_native_select_has_the_same_box_as_the_dropdown_that_replaces_it(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        // The styled dropdown is h-10 (2.5rem) with rounded-xl corners and px-3 text; the native select must match.
        $this->assertMatchesRegularExpression('/\.app-select-toggle\s*\{[^}]*h-10[^}]*rounded-xl[^}]*px-3/s', $css);
        $this->assertMatchesRegularExpression('/\nselect:not\(\.app-native-select-hidden\)[^{]*\{[^}]*height:\s*2\.5rem;[^}]*border-radius:\s*0\.75rem;/s', $css);
    }
}
