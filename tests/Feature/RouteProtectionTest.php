<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * Catches a new route that was added without the sign-in and role checks. It reads the route table, so it needs
 * no database: every route is either on the short list of public ones, or signed-in only on purpose, or carries
 * the sign-in, active-account and role middleware of its area.
 */
class RouteProtectionTest extends TestCase
{
    /** Pages anyone may open. The storage routes only exist while the private disk is served (see config/filesystems.php). */
    private const PUBLIC_ROUTES = [
        'home', 'health', 'login', 'login.attempt', 'logout', 'register', 'register.store',
        'legal.privacy', 'legal.terms', 'legal.ticket-consent',
        'storage.local', 'storage.local.upload',
    ];

    /** Signed-in people of any role; each of these checks the record or the user inside the controller or a policy. */
    private const SIGNED_IN_ONLY_ROUTES = [
        'attachments.download', 'legal.acceptance.show', 'legal.acceptance.store', 'locations.municipalities',
    ];

    /**
     * @return array<int, Route>
     */
    private function routes(): array
    {
        return array_values(array_filter(
            app('router')->getRoutes()->getRoutes(),
            fn (Route $route): bool => ! str_starts_with($route->uri(), '_') && ! str_starts_with($route->uri(), 'up')
        ));
    }

    private function describeRoute(Route $route): string
    {
        return implode('|', $route->methods()).' /'.$route->uri().' ('.($route->getName() ?? 'unnamed').')';
    }

    /**
     * @return array<int, string>
     */
    private function routesOpenToGuests(): array
    {
        $unprotected = [];

        foreach ($this->routes() as $route) {
            if (in_array($route->getName(), self::PUBLIC_ROUTES, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            if (! in_array('auth', $middleware, true) || ! in_array('active', $middleware, true)) {
                $unprotected[] = $this->describeRoute($route);
            }
        }

        return $unprotected;
    }

    /**
     * @return array<int, string>
     */
    private function routesUnderWithout(string $prefix, string $role): array
    {
        $missing = [];

        foreach ($this->routes() as $route) {
            if (str_starts_with($route->uri(), $prefix) && ! in_array($role, $route->gatherMiddleware(), true)) {
                $missing[] = $this->describeRoute($route);
            }
        }

        return $missing;
    }

    public function test_every_route_is_public_on_purpose_or_requires_a_signed_in_active_user(): void
    {
        $unprotected = $this->routesOpenToGuests();

        $this->assertSame([], $unprotected, "These routes are open to guests or inactive accounts. Add auth and active, or list them as public on purpose:\n".implode("\n", $unprotected));
    }

    public function test_every_staff_console_route_has_a_role_check(): void
    {
        $missing = $this->routesUnderWithout('admin/', 'role:ticket_console');

        $this->assertSame([], $missing, "Staff console routes without role:ticket_console:\n".implode("\n", $missing));
    }

    public function test_every_client_portal_route_is_limited_to_clients(): void
    {
        $missing = $this->routesUnderWithout('client/', 'role:client');

        $this->assertSame([], $missing, "Client portal routes without role:client:\n".implode("\n", $missing));
    }

    public function test_the_routes_that_only_check_for_a_signed_in_user_are_the_ones_listed(): void
    {
        $found = [];

        foreach ($this->routes() as $route) {
            $name = (string) $route->getName();
            if (in_array($name, self::PUBLIC_ROUTES, true)) {
                continue;
            }

            $hasRole = collect($route->gatherMiddleware())->contains(fn (string $middleware): bool => str_starts_with($middleware, 'role:'));
            if (! $hasRole) {
                $found[] = $name;
            }
        }

        sort($found);
        $expected = self::SIGNED_IN_ONLY_ROUTES;
        sort($expected);

        $this->assertSame($expected, $found, 'A route has no role check and is not on the signed-in-only list. Give it a role group, or list it with a reason.');
    }

    public function test_the_checks_do_catch_a_route_added_without_them(): void
    {
        RouteFacade::get('admin/oops-guard-test', fn () => 'unprotected')->name('oops.guard.test');
        RouteFacade::get('client/oops-guard-test', fn () => 'unprotected')->name('oops.guard.client');

        $this->assertTrue(collect($this->routesOpenToGuests())->contains(fn (string $line): bool => str_contains($line, 'oops.guard.test')));
        $this->assertTrue(collect($this->routesUnderWithout('admin/', 'role:ticket_console'))->contains(fn (string $line): bool => str_contains($line, 'oops.guard.test')));
        $this->assertTrue(collect($this->routesUnderWithout('client/', 'role:client'))->contains(fn (string $line): bool => str_contains($line, 'oops.guard.client')));
    }
}
