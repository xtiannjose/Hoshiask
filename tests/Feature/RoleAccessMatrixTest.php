<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Every role against the routes that change data, export reports or open admin pages. Who may use each route is
 * written out here by hand (not read from the route), so removing or widening a role rule on a route fails this test.
 * A role that is not listed must get 403, and a guest must be sent to sign in. For routes that are safe to call, a
 * listed role must not be refused with 403 either.
 */
class RoleAccessMatrixTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private const ROLES = [User::ROLE_CLIENT, User::ROLE_TECHNICAL, User::ROLE_SUPER_USER, User::ROLE_ADMIN, User::ROLE_SHADOW];

    /** @var array<string, User> */
    private array $users = [];

    private Ticket $ticket;

    private KnowledgeArticle $article;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);

        foreach (self::ROLES as $role) {
            $this->users[$role] = $this->createUser("Matrix {$role}", "matrix-{$role}@example.com", $role);
        }

        $owner = $this->createClient('Matrix Owner', 'matrix-owner@example.com');
        $this->ticket = $this->createTicket($owner, $this->createCategory());
        $this->article = KnowledgeArticle::create([
            'created_by_user_id' => $this->users[User::ROLE_ADMIN]->id,
            'category' => 'faq',
            'title' => 'Matrix article',
            'slug' => 'matrix-article',
            'body' => 'Body.',
            'is_published' => true,
            'published_at' => now(),
        ]);
        $this->department = Department::create(['name' => 'Matrix Department', 'slug' => 'matrix-department']);
    }

    /**
     * name => method, route name, which record the URL needs, the roles that may use it, whether it is safe to call as one of them
     * (nothing is destroyed and no policy narrows it further).
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: array<int, string>, 4: bool}>
     */
    public static function routeCases(): array
    {
        $adminLevel = [User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER];
        $systemAdmin = [User::ROLE_SHADOW, User::ROLE_ADMIN];
        $console = [User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER, User::ROLE_TECHNICAL];
        $client = [User::ROLE_CLIENT];

        return [
            'reports page' => ['GET', 'admin.reports.index', 'none', $adminLevel, true],
            'monthly report pdf' => ['GET', 'admin.reports.monthly.pdf', 'month', $adminLevel, true],
            'custom report csv' => ['GET', 'admin.reports.custom.csv', 'none', $adminLevel, true],
            'custom report pdf' => ['GET', 'admin.reports.custom.pdf', 'none', $adminLevel, true],
            'users list' => ['GET', 'admin.users.index', 'none', $adminLevel, true],
            'canned responses list' => ['GET', 'admin.canned-responses.index', 'none', $adminLevel, true],
            'department edit page' => ['GET', 'admin.departments.edit', 'department', $systemAdmin, true],
            // A policy narrows the article routes further than the role group (technicians cannot edit articles), so only the refusals are checked.
            'knowledge article edit page' => ['GET', 'admin.knowledge-base.edit', 'article', $console, false],
            'knowledge article update' => ['PUT', 'admin.knowledge-base.update', 'article', $console, false],
            'knowledge article delete' => ['DELETE', 'admin.knowledge-base.destroy', 'article', $console, false],
            'ticket department change' => ['POST', 'admin.tickets.department', 'ticket', $adminLevel, false],
            'ticket type change' => ['POST', 'admin.tickets.type', 'ticket', [User::ROLE_SUPER_USER], false],
            'ticket dates change' => ['POST', 'admin.tickets.dates', 'ticket', $adminLevel, false],
            'ticket delete' => ['DELETE', 'admin.tickets.destroy', 'ticket', $systemAdmin, false],
            'bulk ticket action' => ['POST', 'admin.tickets.bulk-action', 'none', $console, false],
            'user delete' => ['DELETE', 'admin.users.destroy', 'user', $adminLevel, false],
            'system logs lock' => ['POST', 'admin.system-logs.lock', 'none', [User::ROLE_SHADOW], false],
            'client ticket list' => ['GET', 'client.tickets.index', 'none', $client, true],
            'client ticket create page' => ['GET', 'client.tickets.create', 'none', $client, true],
            'client ticket store' => ['POST', 'client.tickets.store', 'none', $client, false],
        ];
    }

    /**
     * @param  array<int, string>  $allowed
     */
    #[DataProvider('routeCases')]
    public function test_a_role_the_route_does_not_allow_gets_403_and_a_guest_is_sent_to_sign_in(string $method, string $routeName, string $parameters, array $allowed, bool $safeToCall): void
    {
        $url = $this->url($routeName, $parameters);

        $this->call($method, $url)->assertRedirect(route('login'));

        foreach (self::ROLES as $role) {
            $response = $this->actingAs($this->users[$role])->call($method, $url);

            if (! in_array($role, $allowed, true)) {
                $response->assertForbidden("{$role} must not be allowed on {$method} {$routeName}.");

                continue;
            }

            if ($safeToCall) {
                $this->assertNotSame(403, $response->getStatusCode(), "{$role} is allowed on {$routeName} but got 403.");
            }
        }
    }

    private function url(string $routeName, string $parameters): string
    {
        return match ($parameters) {
            'ticket' => route($routeName, $this->ticket),
            'article' => route($routeName, $this->article),
            'department' => route($routeName, $this->department),
            'user' => route($routeName, $this->users[User::ROLE_CLIENT]),
            'month' => route($routeName, ['month' => now()->format('Y-m')]),
            default => route($routeName),
        };
    }
}
