<?php

namespace Tests\Feature;

use App\Support\FontPreloads;
use Illuminate\Foundation\Vite as ViteService;
use Illuminate\Support\Facades\Vite;
use RuntimeException;
use Tests\TestCase;

/**
 * The fonts are preloaded so the first paint already uses them. Without it the text is drawn in a narrower
 * fallback font and re-flows (the page seems to grow) when the real font arrives.
 */
class FontPreloadsTest extends TestCase
{
    public function test_every_preloaded_font_is_declared_in_the_stylesheet(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        foreach (FontPreloads::FILES as $file) {
            $this->assertStringContainsString('@fontsource/'.$file, $css, "{$file} is preloaded but has no @font-face rule.");
        }
    }

    public function test_the_preloaded_faces_never_swap_fonts_in_the_middle_of_a_page_load(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        foreach (FontPreloads::FILES as $file) {
            $face = preg_match('/@font-face\s*\{[^}]*'.preg_quote('@fontsource/'.$file, '/').'[^}]*\}/', $css, $matches);
            $this->assertSame(1, $face, "No @font-face rule found for {$file}.");
            $this->assertStringContainsString('font-display: optional', $matches[0], "{$file} must use font-display: optional.");
        }
    }

    public function test_it_lists_the_url_of_each_font_the_build_knows_about(): void
    {
        $this->app->instance(ViteService::class, new class extends ViteService
        {
            public function asset($asset, $buildDirectory = null)
            {
                return 'https://cdn.example/'.basename($asset);
            }
        });

        $urls = FontPreloads::urls();

        $this->assertCount(count(FontPreloads::FILES), $urls);
        $this->assertContains('https://cdn.example/plus-jakarta-sans-latin-400-normal.woff2', $urls);
    }

    public function test_a_stale_build_skips_the_preload_instead_of_breaking_the_page(): void
    {
        Vite::swap(new class extends ViteService
        {
            public function asset($asset, $buildDirectory = null)
            {
                throw new RuntimeException('Unable to locate file in Vite manifest');
            }
        });

        $this->assertSame([], FontPreloads::urls());
    }
}
