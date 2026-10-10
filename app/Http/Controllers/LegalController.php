<?php

namespace App\Http\Controllers;

use App\Http\Requests\Legal\AcceptLegalConsentRequest;
use App\Models\User;
use App\Models\UserLegalConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegalController extends Controller
{
    public function terms()
    {
        return view('legal.terms');
    }

    public function privacy()
    {
        return view('legal.privacy');
    }

    public function ticketConsent()
    {
        return view('legal.ticket-consent');
    }

    public function showAcceptance(Request $request)
    {
        $user = $request->user();
        if ($user && UserLegalConsent::hasCurrentConsentForUser($user)) {
            return $this->redirectAfterAcceptance($request, $user);
        }

        return view('legal.acceptance');
    }

    public function accept(AcceptLegalConsentRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        UserLegalConsent::recordAcceptance($user, $request);

        return $this->redirectAfterAcceptance($request, $user)
            ->with('success', 'Legal consent recorded successfully.');
    }

    private function redirectAfterAcceptance(Request $request, User $user): RedirectResponse
    {
        $intendedUrl = (string) $request->session()->pull('legal_consent_intended', '');

        if ($this->isSafeInternalUrl($intendedUrl)) {
            return redirect()->to($intendedUrl);
        }

        return $user->canAccessAdminTickets()
            ? redirect('/admin/dashboard')
            : redirect('/client/dashboard');
    }

    private function isSafeInternalUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $parsedPath = (string) parse_url($url, PHP_URL_PATH);
        if ($parsedPath === '' || str_starts_with($parsedPath, '/legal/acceptance')) {
            return false;
        }

        $appHost = (string) parse_url(config('app.url'), PHP_URL_HOST);
        $urlHost = (string) parse_url($url, PHP_URL_HOST);

        return $urlHost === '' || $urlHost === $appHost;
    }
}
