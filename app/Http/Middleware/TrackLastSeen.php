<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps users.last_seen_at current so the shadow account can see when someone was last
 * active after their session record is gone.
 */
class TrackLastSeen
{
    /** Minimum gap between writes, so tracking costs at most one update a minute per user. */
    public const WRITE_INTERVAL_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track users something else already resolved (e.g. the auth middleware), so
        // public pages never trigger a remember-me sign-in just to record a visit.
        $guard = Auth::guard();
        $user = $guard->hasUser() ? $guard->user() : null;

        if ($user instanceof User) {
            $this->record($user);
        }

        return $response;
    }

    private function record(User $user): void
    {
        $now = now();

        if ($user->last_seen_at !== null && $user->last_seen_at->gt($now->copy()->subSeconds(self::WRITE_INTERVAL_SECONDS))) {
            return;
        }

        // Written directly so the account's updated_at is not touched.
        DB::table($user->getTable())
            ->where('id', $user->id)
            ->update(['last_seen_at' => $now]);

        $user->last_seen_at = $now;
        $user->syncOriginalAttribute('last_seen_at');
    }
}
