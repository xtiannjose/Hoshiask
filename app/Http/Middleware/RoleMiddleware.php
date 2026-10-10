<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Named role groups usable in routes as `role:<group>` (mixable with single roles),
     * so route access follows the role lists defined on User instead of repeating them.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_GROUPS = [
        'ticket_console' => User::TICKET_CONSOLE_ROLES,
        'admin_level' => User::ADMIN_LEVEL_ROLES,
        'ticket_creator' => User::TICKET_CREATOR_ROLES,
        'system_admin' => User::SYSTEM_ADMIN_ROLES,
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (empty($roles)) {
            return $next($request);
        }

        $normalizedCurrentRole = User::normalizeRole($user->role);

        if (in_array($normalizedCurrentRole, self::expandRoles($roles), true)) {
            return $next($request);
        }

        abort(403, 'Access denied. Insufficient permissions.');
    }

    /**
     * @param  array<int, string>  $roles
     * @return list<string>
     */
    public static function expandRoles(array $roles): array
    {
        $expanded = [];
        foreach ($roles as $role) {
            $normalizedRole = User::normalizeRole($role);
            array_push($expanded, ...(self::ROLE_GROUPS[$normalizedRole] ?? [$normalizedRole]));
        }

        return array_values(array_unique($expanded));
    }
}
