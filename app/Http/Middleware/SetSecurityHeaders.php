<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // The one response our own pages put in a frame is the PDF or text attachment preview. It may be framed
        // by this site only; everything else refuses framing completely.
        $isAttachmentPreview = $request->routeIs('attachments.download') && $request->boolean('preview');

        $response->headers->set('X-Frame-Options', $isAttachmentPreview ? 'SAMEORIGIN' : 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        $cspEnabled = (bool) config('security.csp.enabled_in_production', true)
            && (app()->environment('production') || (bool) config('security.csp.force', false));

        // The preview carries only the framing rule: a fuller policy (object-src 'none') stops the browser's built-in
        // PDF viewer from drawing the file. It holds a PDF or plain text, which run nothing on the page.
        $cspDirectives = $this->compileCspDirectives(
            $isAttachmentPreview ? ['frame-ancestors' => ["'self'"]] : (array) config('security.csp.directives', [])
        );

        if ($cspEnabled) {
            $response->headers->set('Content-Security-Policy', $cspDirectives);
        } elseif (! app()->environment('production')) {
            $response->headers->set('Content-Security-Policy-Report-Only', $cspDirectives);
        }

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    private function compileCspDirectives(array $directives): string
    {
        $parts = [];
        foreach ($directives as $directive => $values) {
            $directiveName = trim((string) $directive);
            if ($directiveName === '') {
                continue;
            }

            $tokens = array_values(array_filter(
                array_map(static fn (mixed $value): string => trim((string) $value), (array) $values),
                static fn (string $value): bool => $value !== ''
            ));

            $parts[] = $directiveName.(empty($tokens) ? '' : ' '.implode(' ', $tokens));
        }

        return implode('; ', $parts).';';
    }
}
