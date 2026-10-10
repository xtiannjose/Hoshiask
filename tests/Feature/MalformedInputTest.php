<?php

namespace Tests\Feature;

use App\Http\Middleware\DropMalformedArrayInput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Typing `?search[]=x` into the address bar, or posting a list where a name or password belongs,
 * used to turn the page into a 500 error. Bad input must be ignored or refused, never crash the page.
 */
class MalformedInputTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_list_pages_ignore_array_valued_query_parameters(): void
    {
        $admin = $this->createUser('Admin User', 'admin-malformed@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $this->createTicket($client, $this->createCategory(), ['subject' => 'Still listed']);

        foreach ([
            route('admin.tickets.index', ['search' => ['x'], 'priority' => ['x'], 'status' => ['x'], 'tab' => ['a' => 'b']]),
            route('admin.users.index', ['search' => ['x'], 'role' => ['x']]),
            route('admin.reports.index', ['month' => ['x'], 'search' => ['x']]),
            route('admin.reports.custom.index', ['group_by' => ['x'], 'department' => ['x'], 'created_from' => ['x'], 'layout' => ['x']]),
            route('admin.knowledge-base.index', ['category' => ['x'], 'search' => ['x']]),
            route('admin.dashboard', ['search' => ['x']]),
            // A list is fine in a form, never in a link.
            route('admin.tickets.index', ['assigned_to' => ['1'], 'selected_ids' => ['1']]),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->actingAs($client)->get(route('client.tickets.index', ['search' => ['x'], 'status' => ['x']]))->assertOk();
        $this->actingAs($client)->get(route('client.dashboard', ['search' => ['x']]))->assertOk();
    }

    public function test_an_ignored_array_parameter_does_not_filter_the_list(): void
    {
        $admin = $this->createUser('Admin User', 'admin-malformed-filter@example.com', User::ROLE_ADMIN);
        $this->createTicket($this->createClient(), $this->createCategory(), ['subject' => 'Visible despite junk']);

        $this->actingAs($admin)
            ->get(route('admin.tickets.index', ['search' => ['x'], 'tab' => 'all']))
            ->assertOk()
            ->assertSee('Visible despite junk');
    }

    public function test_login_refuses_a_list_in_place_of_a_password(): void
    {
        User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan-array-password@example.com',
            'phone' => '09120001111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->from(route('login'))
            ->post(route('login'), ['login' => 'juan-array-password@example.com', 'password' => ['password123']])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('password');

        $this->postJson(route('login'), ['login' => ['x'], 'password' => 'y'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('login');

        $this->assertGuest();
    }

    public function test_forms_answer_with_a_validation_error_when_a_text_field_arrives_as_a_list(): void
    {
        $admin = $this->createUser('Admin User', 'admin-malformed-forms@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $this->createCategory();

        $this->actingAs($admin)->from(route('admin.departments.index'))
            ->post(route('admin.departments.store'), ['name' => ['x'], 'logo' => ['x']])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->from(route('account.settings'))
            ->put(route('account.settings.update'), ['name' => ['x'], 'email' => ['x'], 'phone' => ['x'], 'username' => ['x']])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->from(route('admin.users.create'))
            ->post(route('admin.users.store'), ['name' => ['x'], 'email' => ['x'], 'phone' => ['x'], 'username' => ['x']])
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->from(route('admin.tickets.create'))
            ->post(route('admin.tickets.store'), ['contact_number' => ['x'], 'email' => ['x'], 'subject' => ['x']])
            ->assertSessionHasErrors('subject');

        $this->actingAs($admin)
            ->post(route('admin.tickets.bulk-action'), ['action' => 'assign', 'selected_ids' => [['x']], 'assigned_to' => [['x']]])
            ->assertSessionHasErrors('selected_ids');

        $this->actingAs($client)->from(route('client.tickets.create'))
            ->post(route('client.tickets.store'), ['name' => ['x'], 'contact_number' => ['x'], 'email' => ['x'], 'subject' => ['x']])
            ->assertSessionHasErrors('subject');
    }

    public function test_the_filter_keeps_plain_values_and_real_lists(): void
    {
        $next = fn (Request $request): Response => new Response('ok');
        $middleware = new DropMalformedArrayInput;

        $get = Request::create('/x?keep=1&blank=&junk[]=2&nested[a][b]=3&assigned_to[]=1', 'GET');
        $middleware->handle($get, $next);
        $this->assertSame(['keep' => '1', 'blank' => ''], $get->query->all());

        $post = Request::create('/x', 'POST', [
            'name' => ['x'],
            'subject' => 'kept',
            'assigned_to' => ['1', '2'],
            'selected_ids' => ['3'],
            'filters' => ['tab' => 'all', 'status' => 'open'],
        ]);
        $middleware->handle($post, $next);
        $this->assertSame([
            'subject' => 'kept',
            'assigned_to' => ['1', '2'],
            'selected_ids' => ['3'],
            'filters' => ['tab' => 'all', 'status' => 'open'],
        ], $post->request->all());

        $nested = Request::create('/x', 'POST', ['assigned_to' => [['deeper']], 'filters' => ['tab' => ['deeper']]]);
        $middleware->handle($nested, $next);
        $this->assertSame([], $nested->request->all(), 'a list may not contain further lists');
    }

    public function test_the_filter_also_cleans_json_bodies(): void
    {
        $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['message' => 'hi', 'activity_at' => ['x']]));

        (new DropMalformedArrayInput)->handle($request, fn (Request $request): Response => new Response('ok'));

        $this->assertSame(['message' => 'hi'], $request->json()->all());
    }
}
