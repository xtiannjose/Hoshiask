<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Password lock for the shadow account's monitoring tools (system logs, online users and
 * sign-in activity). One unlock covers all of them for a limited time.
 */
class EnsureSystemLogsUnlocked
{
    public const SESSION_KEY = 'system_logs_unlocked_until';

    public function handle(Request $request, Closure $next): Response
    {
        if (self::isUnlocked($request)) {
            return $next($request);
        }

        $request->session()->forget(self::SESSION_KEY);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Monitoring tools are locked. Enter your shadow account password to continue.',
            ], 423);
        }

        // Send the shadow back to where they were headed once they unlock. The system log
        // viewer is the default destination, so it needs no return address.
        $returnTo = $request->isMethod('GET') && ! $request->routeIs('admin.system-logs.index')
            ? self::safeReturnPath($request->getRequestUri())
            : null;

        return redirect()->route('admin.system-logs.unlock.show', array_filter(['return_to' => $returnTo]))
            ->with('error', 'Enter your shadow account password to access monitoring tools.');
    }

    public static function isUnlocked(Request $request): bool
    {
        $unlockedUntil = $request->session()->get(self::SESSION_KEY);

        try {
            $unlockedUntilAt = is_string($unlockedUntil) ? Carbon::parse($unlockedUntil) : null;
        } catch (\Throwable) {
            $unlockedUntilAt = null;
        }

        return $unlockedUntilAt !== null && now()->lessThan($unlockedUntilAt);
    }

    /**
     * Accept only same-site admin paths, so the unlock form cannot be used as an open redirect.
     */
    public static function safeReturnPath(mixed $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);

        if (! str_starts_with($path, '/admin/') || str_contains($path, '\\') || str_starts_with($path, '/admin/system-logs/unlock')) {
            return null;
        }

        return $path;
    }
}
