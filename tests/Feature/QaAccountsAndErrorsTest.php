<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class QaAccountsAndErrorsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private function technicianWithTemporaryPassword(bool $profileLocked): User
    {
        return $this->createAssignedSupportUser('Stuck Tech', 'stuck-tech@example.com', [
            'must_change_password' => true,
            'is_profile_locked' => $profileLocked,
        ]);
    }

    // ---- Q-03: forced password change on a locked profile ------------------

    public function test_a_locked_profile_with_a_temporary_password_can_still_set_a_new_password(): void
    {
        $tech = $this->technicianWithTemporaryPassword(profileLocked: true);

        $this->actingAs($tech)->get(route('account.settings'))
            ->assertOk()
            ->assertSee('you can still set your new password below')
            ->assertSee('Set New Password');

        $response = $this->actingAs($tech)->put(route('account.settings.update'), [
            'current_password' => 'password123',
            'password' => 'BrandNewPass99',
            'password_confirmation' => 'BrandNewPass99',
            // A tampered form must not change locked details.
            'name' => 'Changed Name',
            'phone' => '09999999999',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('success', 'Password changed. You can now use the system.');

        $tech->refresh();
        $this->assertTrue(Hash::check('BrandNewPass99', $tech->password));
        $this->assertFalse($tech->must_change_password);
        $this->assertSame('Stuck Tech', $tech->name);
        $this->assertSame('09170000000', $tech->phone);
        $this->assertTrue($tech->is_profile_locked);
    }

    public function test_a_locked_profile_without_a_temporary_password_stays_fully_locked(): void
    {
        $tech = $this->createAssignedSupportUser('Locked Tech', 'locked-tech@example.com', ['is_profile_locked' => true]);

        $this->actingAs($tech)->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Profile Locked')
            ->assertDontSee('Set New Password');

        $this->actingAs($tech)->put(route('account.settings.update'), [
            'current_password' => 'password123',
            'password' => 'BrandNewPass99',
            'password_confirmation' => 'BrandNewPass99',
        ])->assertRedirect(route('account.settings'))
            ->assertSessionHas('error');

        $this->assertTrue(Hash::check('password123', $tech->fresh()->password));
    }

    // ---- Q-04: the new password must differ from the temporary one ---------

    public function test_the_forced_change_refuses_the_same_password(): void
    {
        $tech = $this->technicianWithTemporaryPassword(profileLocked: false);

        $this->actingAs($tech)->from(route('account.settings'))->put(route('account.settings.update'), [
            'name' => $tech->name,
            'current_password' => 'password123',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['password' => 'Choose a new password that is different from your current one.']);

        $this->assertTrue($tech->fresh()->must_change_password);
    }

    public function test_an_unlocked_forced_change_lands_on_the_dashboard_with_a_confirmation(): void
    {
        $tech = $this->technicianWithTemporaryPassword(profileLocked: false);

        $this->actingAs($tech)->put(route('account.settings.update'), [
            'name' => $tech->name,
            'current_password' => 'password123',
            'password' => 'BrandNewPass99',
            'password_confirmation' => 'BrandNewPass99',
        ])->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('success', 'Password changed. You can now use the system.');
    }

    // ---- Q-05: session expiry is explained ---------------------------------

    public function test_a_signed_out_visit_after_signing_in_explains_that_the_session_expired(): void
    {
        $response = $this->withCookie(AuthController::SIGNED_IN_HINT_COOKIE, '1')->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
        $this->followRedirects($response)->assertSee('Your session expired. Please sign in again.');
    }

    public function test_a_first_time_visitor_is_not_told_their_session_expired(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
        $this->followRedirects($response)->assertDontSee('Your session expired');
    }

    public function test_signing_in_sets_the_hint_cookie_and_signing_out_clears_it(): void
    {
        $user = $this->createClient('Cookie Client', 'cookie-client@example.com', ['username' => 'cookie.client']);

        $login = $this->post(route('login'), ['login' => 'cookie.client', 'password' => 'password123']);
        $login->assertCookie(AuthController::SIGNED_IN_HINT_COOKIE);

        $logout = $this->actingAs($user)->post(route('logout'));
        $cookie = $logout->getCookie(AuthController::SIGNED_IN_HINT_COOKIE, false);
        $this->assertTrue($cookie === null || $cookie->isCleared());
    }

    // ---- Q-06: branded error pages -----------------------------------------

    public function test_a_missing_page_shows_a_branded_page_with_a_way_home_for_visitors(): void
    {
        $this->get('/no/such/page')
            ->assertNotFound()
            ->assertSee('We could not find that page')
            ->assertSee('Sign in');
    }

    public function test_a_missing_page_offers_the_dashboard_to_signed_in_users(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->get('/no/such/page')
            ->assertNotFound()
            ->assertSee('We could not find that page')
            ->assertSee('Go to my dashboard')
            ->assertSee(route('client.dashboard'), false);
    }

    public function test_staff_opening_a_ticket_assigned_to_someone_else_is_told_so(): void
    {
        $other = $this->createAssignedSupportUser('Other Tech', 'other-tech@example.com');
        $me = $this->createAssignedSupportUser('My Tech', 'my-tech@example.com');
        $requester = $this->createAssignedSupportUser('Requester Tech', 'requester-tech@example.com');
        // A staff-to-staff (internal) request stays limited to the people involved.
        $ticket = $this->createTicket($requester, $this->createCategory(), ['assigned_to' => $other->id, 'ticket_type' => 'internal']);
        $ticket->assignedUsers()->sync([$other->id]);

        $this->actingAs($me)->get(route('admin.tickets.show', $ticket))
            ->assertForbidden()
            ->assertSee('This ticket is assigned to someone else')
            ->assertSee('Go to my dashboard');
    }

    public function test_other_forbidden_pages_use_a_plain_explanation_not_the_raw_exception_text(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->get(route('admin.reports.index'))
            ->assertForbidden()
            ->assertSee('You cannot open this page')
            ->assertDontSee('This action is unauthorized');
    }

    public function test_error_pages_are_branded_for_the_session_expired_and_upload_too_large_cases(): void
    {
        $this->assertStringContainsString('Your session expired', view('errors.419')->render());
        $this->assertStringContainsString('That upload is too large', view('errors.413')->render());
        $this->assertStringContainsString('Something went wrong on our side', view('errors.500')->render());
    }

    // ---- login page ---------------------------------------------------------

    public function test_the_login_page_focuses_the_username_and_offers_show_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('autofocus', false)
            ->assertSee('data-login-show-password', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertDontSee('type="checkbox" data-login-show-password', false);
    }

    // ---- Q-28 / Q-38 --------------------------------------------------------

    public function test_a_username_with_spaces_gets_a_message_that_says_what_is_allowed(): void
    {
        $admin = $this->createUser('Admin', 'admin-usernames@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'username' => 'QA User Six',
            'name' => 'QA User Six',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => 'SomePass1234',
            'password_confirmation' => 'SomePass1234',
        ])->assertSessionHasErrors(['username' => 'Usernames can only use lowercase letters, numbers, dots, dashes and underscores (no spaces or capitals).']);
    }

    public function test_the_user_forms_state_the_username_rule_up_front(): void
    {
        $admin = $this->createUser('Admin', 'admin-username-help@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Lowercase letters, numbers, dots, dashes and underscores only. No spaces.');
    }

    public function test_the_users_list_explains_why_a_locked_account_cannot_be_deleted(): void
    {
        $admin = $this->createUser('Admin', 'admin-locked-list@example.com', User::ROLE_ADMIN);
        $this->createAssignedSupportUser('Locked Row', 'locked-row@example.com', ['is_profile_locked' => true]);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Delete unavailable (profile locked)');
    }

    // ---- consent page (Q-39) --------------------------------------------------

    public function test_the_consent_page_hides_menu_links_that_would_only_bounce_back(): void
    {
        config([
            'legal.require_acceptance' => true,
            'legal.terms_version' => '2026-02-25',
            'legal.privacy_version' => '2026-02-25',
            'legal.platform_consent_version' => '2026-02-25',
        ]);
        $client = $this->createClient('Consent Client', 'consent-menu@example.com');

        $this->actingAs($client)->get(route('legal.acceptance.show'))
            ->assertOk()
            ->assertSee('Accept the terms on this page to start using the hoshiask.')
            ->assertDontSee(route('client.tickets.create'), false)
            ->assertDontSee(route('client.tickets.index'), false)
            ->assertDontSee('Search my tickets');
    }

    // ---- Q-27: % and _ in a search are ordinary characters ---------------------

    public function test_a_percent_sign_in_the_ticket_search_matches_only_tickets_that_contain_it(): void
    {
        $admin = $this->createUser('Admin', 'admin-search@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $match = $this->createTicket($client, $category, ['subject' => 'Disk is 95% full']);
        $other = $this->createTicket($client, $category, ['subject' => 'Printer offline']);

        // Ticket numbers only appear in the list rows (subjects also show in the header notifications).
        $this->actingAs($admin)->get(route('admin.tickets.index', ['search' => '%']))
            ->assertOk()
            ->assertSee($match->ticket_number)
            ->assertDontSee($other->ticket_number);

        $this->actingAs($client)->get(route('client.tickets.index', ['search' => '%']))
            ->assertOk()
            ->assertSee($match->ticket_number)
            ->assertDontSee($other->ticket_number);
    }

    public function test_an_underscore_in_a_search_is_not_a_single_character_wildcard(): void
    {
        $admin = $this->createUser('Admin', 'admin-search-underscore@example.com', User::ROLE_ADMIN);
        $client = $this->createClient();
        $category = $this->createCategory();
        $match = $this->createTicket($client, $category, ['subject' => 'file_name problem']);
        $other = $this->createTicket($client, $category, ['subject' => 'fileXname problem']);

        $this->actingAs($admin)->get(route('admin.tickets.index', ['search' => 'file_name']))
            ->assertOk()
            ->assertSee($match->ticket_number)
            ->assertDontSee($other->ticket_number);
    }

    public function test_the_user_and_knowledge_base_searches_also_treat_percent_literally(): void
    {
        $admin = $this->createUser('Admin', 'admin-search-users@example.com', User::ROLE_ADMIN);
        $this->createClient('Fifty Percent', 'fifty@example.com', ['department' => 'Hoshishi']);

        $this->actingAs($admin)->get(route('admin.users.index', ['search' => '%']))
            ->assertOk()
            ->assertDontSee('Fifty Percent');

        KnowledgeArticle::create([
            'title' => 'Reset a password',
            'slug' => 'reset-a-password',
            'body' => 'Steps to reset.',
            'category' => 'other',
            'is_published' => true,
            'created_by_user_id' => $admin->id,
        ]);
        $this->assertSame(0, KnowledgeArticle::query()->search('%')->count());
        $this->assertSame(1, KnowledgeArticle::query()->search('reset')->count());
    }
}
