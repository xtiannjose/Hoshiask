<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * The web fonts every page uses. They are listed in the page head so the browser starts downloading them
 * together with the stylesheet, instead of discovering them only after the first layout. Without this the
 * first paint uses a narrower fallback font and the text re-flows (the page "grows") when the real one arrives.
 *
 * The files must stay in step with the @font-face rules at the top of resources/css/app.css.
 */
class FontPreloads
{
    public const FILES = [
        'plus-jakarta-sans/files/plus-jakarta-sans-latin-400-normal.woff2',
        'plus-jakarta-sans/files/plus-jakarta-sans-latin-500-normal.woff2',
        'plus-jakarta-sans/files/plus-jakarta-sans-latin-600-normal.woff2',
        'plus-jakarta-sans/files/plus-jakarta-sans-latin-700-normal.woff2',
        'space-grotesk/files/space-grotesk-latin-600-normal.woff2',
    ];

    /**
     * @return list<string> URLs of the font files, skipping any the asset build does not know about
     *                      (a stale build must not take the page down just to skip an optimisation).
     */
    public static function urls(): array
    {
        $urls = [];

        foreach (self::FILES as $file) {
            try {
                $url = (string) Vite::asset('node_modules/@fontsource/'.$file);
            } catch (Throwable) {
                continue;
            }

            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }
}
