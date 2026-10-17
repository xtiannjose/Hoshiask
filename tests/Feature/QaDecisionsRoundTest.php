<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use App\Rules\NotACommonPassword;
use App\Support\LoginThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The decisions made after the QA report: Q-12 (optional attachment), Q-14 (stars, comment only for
 * low ratings), Q-15 (password help line), Q-22 (severity wording), Q-29 (weak passwords),
 * Q-30 (new staff must change their password) and Q-42 (clients see "not found").
 */
class QaDecisionsRoundTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private function admin(): User
    {
        return $this->createUser('Admin', 'admin-decisions@example.com', User::ROLE_ADMIN);
    }

    // ---- Q-12 optional attachment ---------------------------------------------------------

    private function ticketPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'No File Client',
            'contact_number' => '09181234567',
            'email' => 'nofile@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Question without a screenshot',
            'description' => 'How do I reset my password?',
            'category_id' => $this->createCategory()->id,
            'ticket_consent' => '1',
        ], $overrides);
    }

    public function test_a_client_can_file_a_ticket_without_any_attachment(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->post(route('client.tickets.store'), $this->ticketPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $ticket = Ticket::query()->where('subject', 'Question without a screenshot')->firstOrFail();
        $this->assertSame(0, $ticket->attachments()->count());
    }

    public function test_attachments_are_still_accepted_and_the_form_calls_them_optional(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->post(route('client.tickets.store'), $this->ticketPayload([
            'subject' => 'With a screenshot',
            'attachments' => [UploadedFile::fake()->create('shot.txt', 4, 'text/plain')],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Ticket::query()->where('subject', 'With a screenshot')->firstOrFail()->attachments()->count());

        $this->actingAs($client)->get(route('client.tickets.create'))
            ->assertOk()
            ->assertSee('(Optional)')
            ->assertSee('Adding a screenshot helps us solve it faster')
            ->assertDontSee('Upload at least one file');
    }

    public function test_a_bad_file_is_still_rejected_when_one_is_sent(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->post(route('client.tickets.store'), $this->ticketPayload([
            'attachments' => [UploadedFile::fake()->create('virus.exe', 4, 'application/octet-stream')],
        ]))->assertSessionHasErrors('attachments.0');
    }

    // ---- Q-14 stars, comment only for low ratings ------------------------------------------

    private function resolvedTicket(User $client): Ticket
    {
        return $this->createTicket($client, $this->createCategory(), ['status' => 'resolved', 'resolved_at' => now()]);
    }

    public function test_a_good_rating_needs_no_comment_when_confirming_a_resolved_ticket(): void
    {
        $client = $this->createClient();
        $ticket = $this->resolvedTicket($client);

        $this->actingAs($client)->post(route('client.tickets.rate', $ticket), ['rating' => '4'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(4, $ticket->satisfaction_rating);
        $this->assertNull($ticket->satisfaction_comment);
    }

    public function test_resolving_an_open_ticket_with_five_stars_and_no_comment_works(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)->post(route('client.tickets.resolve', $ticket), [
            'resolve_confirmation' => '1',
            'rating' => '5',
            'comment' => '   ',
        ])->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertSame(5, $ticket->satisfaction_rating);
        $this->assertNull($ticket->satisfaction_comment, 'a blank comment is stored as nothing');
    }

    #[DataProvider('lowRatings')]
    public function test_one_or_two_stars_need_a_comment(string $rating): void
    {
        $client = $this->createClient();
        $ticket = $this->resolvedTicket($client);

        $this->actingAs($client)->from(route('client.tickets.show', $ticket))
            ->post(route('client.tickets.rate', $ticket), ['rating' => $rating, 'comment' => '  '])
            ->assertSessionHasErrors(['comment' => 'You gave 1 or 2 stars. Please tell us what went wrong so we can put it right.']);

        $this->assertNull($ticket->fresh()->satisfaction_rating);

        $this->actingAs($client)->post(route('client.tickets.rate', $ticket), ['rating' => $rating, 'comment' => ' Took three days. '])
            ->assertSessionHasNoErrors();

        $this->assertSame('Took three days.', $ticket->fresh()->satisfaction_comment);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lowRatings(): array
    {
        return ['one star' => ['1'], 'two stars' => ['2']];
    }

    public function test_a_rating_is_still_required_and_must_be_one_to_five(): void
    {
        $client = $this->createClient();
        $ticket = $this->resolvedTicket($client);

        $this->actingAs($client)->post(route('client.tickets.rate', $ticket), ['comment' => 'Great'])
            ->assertSessionHasErrors(['rating' => 'Please tap a star to rate our support.']);
        $this->actingAs($client)->post(route('client.tickets.rate', $ticket), ['rating' => '6'])
            ->assertSessionHasErrors('rating');
        $this->actingAs($client)->post(route('client.tickets.rate', $ticket), ['rating' => '0'])
            ->assertSessionHasErrors('rating');
    }

    public function test_the_rating_forms_use_tappable_stars_and_an_optional_comment(): void
    {
        $client = $this->createClient();
        $resolved = $this->resolvedTicket($client);
        $open = $this->createTicket($client, $this->createCategory('Other'), ['status' => 'open']);

        $sidebar = $this->actingAs($client)->get(route('client.tickets.show', $resolved))->assertOk();
        $sidebar->assertSee('data-rating-form', false)
            ->assertSee('data-star-rating', false)
            ->assertSee('id="sidebar-rating-5"', false)
            ->assertSee('id="sidebar-rating-1"', false)
            ->assertSee('Thanks for confirming. How did we do?')
            ->assertSee('(optional)')
            ->assertDontSee('This feedback is now required')
            ->assertDontSee('Select rating');
        $this->assertStringNotContainsString('<select name="rating"', $sidebar->getContent());

        $modal = $this->actingAs($client)->get(route('client.tickets.show', $open))->assertOk();
        $modal->assertSee('id="resolve-rating-5"', false)
            ->assertSee('Confirm Resolved')
            ->assertDontSee('Confirm Resolve and Submit Rating');
        $this->assertStringNotContainsString('id="resolve_rating"', $modal->getContent());
    }

    public function test_the_stars_are_real_radio_buttons_so_keyboard_and_screen_readers_work(): void
    {
        $html = view('components.ui.star-rating', ['idPrefix' => 'x', 'value' => 3])->render();

        $this->assertSame(5, substr_count($html, 'type="radio"'));
        $this->assertSame(1, substr_count($html, 'checked'));
        $this->assertStringContainsString('id="x-3"', $html);
        $this->assertStringContainsString('3 stars, Average', $html);
        $this->assertStringContainsString('1 star, Very poor', $html);
        $this->assertStringContainsString('<legend', $html);
    }

    // ---- Q-15 forgot-your-password line ---------------------------------------------------------

    public function test_the_login_page_tells_people_to_contact_the_administrator(): void
    {
        config(['hoshiask.password_help_contact' => null]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot your password? Contact your administrator.');
    }

    public function test_the_contact_is_shown_as_an_email_link_or_plain_text(): void
    {
        config(['hoshiask.password_help_contact' => 'itsupport@example.org']);
        $this->get(route('login'))
            ->assertSee('Contact your administrator at', false)
            ->assertSee('href="mailto:itsupport@example.org"', false);

        config(['hoshiask.password_help_contact' => 'Local 123 (IT desk)']);
        $page = $this->get(route('login'))->assertSee('Local 123 (IT desk)');
        // (The page's legal pop-up has its own mailto links; only this contact must not become one.)
        $this->assertStringNotContainsString('mailto:Local', $page->getContent());
    }

    public function test_the_contact_is_escaped(): void
    {
        config(['hoshiask.password_help_contact' => '<script>alert(1)</script>']);

        $this->get(route('login'))->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_lockout_message_says_how_to_get_help(): void
    {
        config(['hoshiask.password_help_contact' => 'itsupport@example.org']);
        $key = LoginThrottle::accountKey('locked.user', '127.0.0.1');
        for ($i = 0; $i < LoginThrottle::MAX_ATTEMPTS; $i++) {
            RateLimiter::hit($key, LoginThrottle::LOCKOUT_SECONDS);
        }

        $this->post(route('login.attempt'), ['login' => 'locked.user', 'password' => 'whatever'])
            ->assertSessionHasErrors(['login' => 'Too many sign-in attempts for this account. Try again in 15 minutes. Need help sooner? Contact your administrator at itsupport@example.org.']);
    }

    // ---- Q-22 severity wording --------------------------------------------------------------------

    public function test_a_ticket_without_a_severity_is_labelled_severity_not_set(): void
    {
        $ticket = new Ticket(['priority' => null]);

        $this->assertSame('Severity not set', $ticket->priority_label);
    }

    public function test_the_staff_list_and_filters_say_severity_not_set_and_never_pending_review(): void
    {
        $admin = $this->admin();
        $this->createTicket($this->createClient(), $this->createCategory(), ['priority' => null]);

        $this->actingAs($admin)->get(route('admin.tickets.index'))
            ->assertOk()
            ->assertSee('Severity not set')
            ->assertDontSee('Pending review');
    }

    // ---- Q-29 weak passwords --------------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function passwords(): array
    {
        return [
            'password' => ['password', true],
            'Password with capitals' => ['PassWord', true],
            'password plus digits' => ['password123', true],
            'password plus year and bang' => ['Password2026!', true],
            'digits ascending' => ['12345678', true],
            'digits ascending long' => ['23456789', true],
            'digits descending' => ['87654321', true],
            'one repeated digit' => ['11111111', true],
            'one repeated letter' => ['aaaaaaaa', true],
            'keyboard row' => ['qwertyuiop', true],
            'welcome plus digit' => ['Welcome1', true],
            'hoshiask based' => ['Hoshiask2026', true],
            'passw0rd leetspeak' => ['P@ssw0rd', true],
            'a phrase' => ['purple-giraffe-lamp', false],
            'a mixed password' => ['T7#kqLw9vR', false],
            'words with digits' => ['blue horse 42 river', false],
            'a long word that merely starts with a bad one' => ['passwordless-entry-code', false],
            'a name and a number' => ['Marites1990', false],
        ];
    }

    #[DataProvider('passwords')]
    public function test_common_passwords_are_recognised_and_good_ones_are_not(string $password, bool $isCommon): void
    {
        $this->assertSame($isCommon, NotACommonPassword::isCommon($password), $password);
    }

    public function test_creating_a_user_with_a_common_password_is_refused_with_a_plain_message(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'username' => 'weak.pass',
            'name' => 'Weak Pass',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors(['password' => 'That password is too easy to guess. Choose something less common, for example a short phrase of several words.']);

        $this->assertDatabaseMissing('users', ['username' => 'weak.pass']);
    }

    public function test_changing_a_password_in_account_settings_or_the_user_editor_also_refuses_common_ones(): void
    {
        $tech = $this->createAssignedSupportUser('Weak Tech', 'weak-tech@example.com');
        $admin = $this->admin();

        $this->actingAs($tech)->from(route('account.settings'))->put(route('account.settings.update'), [
            'name' => $tech->name,
            'current_password' => 'password123',
            'password' => 'qwertyuiop',
            'password_confirmation' => 'qwertyuiop',
        ])->assertSessionHasErrors('password');

        $this->actingAs($admin)->from(route('admin.users.edit', $tech))->put(route('admin.users.update', $tech), [
            'username' => $tech->username,
            'name' => $tech->name,
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_good_password_is_accepted_and_the_forms_state_the_rule(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'username' => 'good.pass',
            'name' => 'Good Pass',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'good.pass']);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertSee('Very common passwords such as')
            ->assertSee('Staff are asked to choose their own password when they first sign in.');
    }

    public function test_existing_passwords_keep_working(): void
    {
        $this->createClient('Old Client', 'old-client@example.com', ['username' => 'old.client']);

        // The seeded test password ("password123") is on the common list, yet signing in still works.
        $this->post(route('login.attempt'), ['login' => 'old.client', 'password' => 'password123'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    // ---- Q-30 new staff change their password ---------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function rolesAndForcedChange(): array
    {
        return [
            'technician' => [User::ROLE_TECHNICAL, true],
            'super user' => [User::ROLE_SUPER_USER, true],
            'admin' => [User::ROLE_ADMIN, true],
            'client' => [User::ROLE_CLIENT, false],
        ];
    }

    #[DataProvider('rolesAndForcedChange')]
    public function test_new_staff_accounts_must_change_the_password_but_clients_do_not(string $role, bool $forced): void
    {
        $shadow = $this->createUser('Shadow', 'shadow-decisions@example.com', User::ROLE_SHADOW);

        $this->actingAs($shadow)->post(route('admin.users.store'), [
            'username' => 'new.'.$role,
            'name' => 'New Person',
            'department' => 'Hoshishi',
            'role' => $role,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ])->assertSessionHasNoErrors();

        $this->assertSame($forced, (bool) User::query()->where('username', 'new.'.$role)->firstOrFail()->must_change_password);
    }

    public function test_a_newly_created_staff_member_is_sent_to_change_the_password_before_anything_else(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'username' => 'fresh.tech',
            'name' => 'Fresh Tech',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ])->assertSessionHasNoErrors();

        $fresh = User::query()->where('username', 'fresh.tech')->firstOrFail();

        $this->actingAs($fresh)->get(route('admin.dashboard'))->assertRedirect(route('account.settings'));

        // ...and they can change it, choosing a different one.
        $this->actingAs($fresh)->put(route('account.settings.update'), [
            'name' => $fresh->name,
            'current_password' => 'purple-giraffe-lamp',
            'password' => 'my-own-secret-phrase',
            'password_confirmation' => 'my-own-secret-phrase',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertFalse((bool) $fresh->fresh()->must_change_password);
    }

    // ---- Q-42 clients see "not found" ---------------------------------------------------------------------------

    public function test_a_client_gets_the_same_not_found_for_someone_elses_ticket_as_for_a_missing_one(): void
    {
        $owner = $this->createClient('Owner', 'owner-42@example.com');
        $other = $this->createClient('Other', 'other-42@example.com');
        $ticket = $this->createTicket($owner, $this->createCategory(), ['status' => 'open']);

        $someoneElses = $this->actingAs($other)->get(route('client.tickets.show', $ticket));
        $missing = $this->actingAs($other)->get(route('client.tickets.show', 999999));

        $someoneElses->assertNotFound();
        $missing->assertNotFound();
        $this->assertSame($missing->getStatusCode(), $someoneElses->getStatusCode());
        $someoneElses->assertDontSee($ticket->subject);
    }

    public function test_a_client_cannot_probe_other_ticket_actions_and_always_sees_not_found(): void
    {
        $owner = $this->createClient('Owner', 'owner-42b@example.com');
        $other = $this->createClient('Other', 'other-42b@example.com');
        $ticket = $this->createTicket($owner, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($other)->post(route('client.tickets.reply', $ticket), ['message' => 'hi'])->assertNotFound();
        $this->actingAs($other)->post(route('client.tickets.cancel', $ticket), ['cancel_reason' => 'x'])->assertNotFound();
        $this->actingAs($other)->post(route('client.notifications.seen', $ticket))->assertNotFound();
        $this->actingAs($other)->get(route('client.tickets.replies.feed', $ticket))->assertNotFound();
    }

    public function test_a_client_gets_not_found_for_a_file_on_someone_elses_ticket(): void
    {
        $owner = $this->createClient('Owner', 'owner-42c@example.com');
        $other = $this->createClient('Other', 'other-42c@example.com');
        $ticket = $this->createTicket($owner, $this->createCategory(), ['status' => 'open']);
        $attachment = $ticket->attachments()->create([
            'filename' => 'a.txt',
            'original_filename' => 'a.txt',
            'file_path' => 'attachments/a.txt',
            'mime_type' => 'text/plain',
            'file_size' => 5,
        ]);

        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->actingAs($other)->get(route('attachments.download', $attachment))->assertNotFound();
    }

    public function test_the_owner_can_still_open_their_own_ticket(): void
    {
        $owner = $this->createClient('Owner', 'owner-42d@example.com');
        $ticket = $this->createTicket($owner, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($owner)->get(route('client.tickets.show', $ticket))->assertOk()->assertSee($ticket->subject);
    }

    public function test_a_technician_still_gets_the_assigned_to_someone_else_page_for_staff_to_staff_requests(): void
    {
        $other = $this->createAssignedSupportUser('Other Tech', 'other-tech-42@example.com');
        $me = $this->createAssignedSupportUser('My Tech', 'my-tech-42@example.com');
        $requester = $this->createAssignedSupportUser('Requester Tech', 'requester-tech-42@example.com');
        $ticket = $this->createTicket($requester, $this->createCategory(), ['assigned_to' => $other->id, 'ticket_type' => 'internal']);
        $ticket->assignedUsers()->sync([$other->id]);

        $this->actingAs($me)->get(route('admin.tickets.show', $ticket))
            ->assertForbidden()
            ->assertSee('This ticket is assigned to someone else');
    }
}
