<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessAdminTickets() || ! $user->mustChangePassword()) {
            return $next($request);
        }

        return redirect()
            ->route('account.settings')
            ->with('error', 'Please change your temporary password before continuing.');
    }
}
