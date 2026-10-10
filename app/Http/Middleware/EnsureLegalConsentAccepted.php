<?php

namespace App\Http\Middleware;

use App\Models\UserLegalConsent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalConsentAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (UserLegalConsent::hasCurrentConsentForUser($user)) {
            return $next($request);
        }

        $acceptanceUrl = route('legal.acceptance.show');
        // Only a normal page visit is worth returning to. A JSON poll or a POST has no page to show afterwards.
        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            $request->session()->put('legal_consent_intended', $request->fullUrl());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Legal consent acceptance is required before proceeding.',
                'redirect' => $acceptanceUrl,
            ], 409);
        }

        return redirect()->to($acceptanceUrl);
    }
}
