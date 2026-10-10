<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Admin\UserDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * The requester picker on the new-ticket form lists the first accounts and searches the rest on the server, so a
 * long client list is neither cut off nor sent to the browser whole.
 */
class TicketRequesterAccountSearchTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    private function search(User $staff, array $query)
    {
        return $this->actingAs($staff)->getJson(route('admin.tickets.requester-accounts', $query));
    }

    public function test_search_returns_matching_active_clients_only(): void
    {
        $staff = $this->createSupportUser();
        $this->createClient('Ana Reyes', 'ana.reyes@example.com');
        $this->createClient('Ben Cruz', 'ben.cruz@example.com');
        $this->createClient('Ana Inactive', 'ana.inactive@example.com', ['is_active' => false]);
        $this->createAssignedSupportUser('Ana Technician', 'ana.technician@example.com');

        $response = $this->search($staff, ['group' => 'client', 'q' => 'ana']);

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $this->assertSame(['Ana Reyes'], $response->json('accounts.*.label'));
        $response->assertJsonPath('accounts.0.group', 'client');
        $response->assertJsonPath('accounts.0.email', 'ana.reyes@example.com');
        $response->assertJsonPath('accounts.0.role', null);
    }

    public function test_search_matches_email_department_and_phone_ignoring_case(): void
    {
        $staff = $this->createSupportUser();
        $this->createClient('Maria Santos', 'MSANTOS@Example.com');
        $this->createUser('Jose Lim', 'jose.lim@example.com', User::ROLE_CLIENT, 'Glade', ['phone' => '09175550123']);
        $this->createClient('Someone Else', 'someone.else@example.com');

        $this->assertSame(['Maria Santos'], $this->search($staff, ['group' => 'client', 'q' => 'msantos@EXAMPLE'])->json('accounts.*.label'));
        $this->assertSame(['Jose Lim'], $this->search($staff, ['group' => 'client', 'q' => 'GLAD'])->json('accounts.*.label'));
        $this->assertSame(['Jose Lim'], $this->search($staff, ['group' => 'client', 'q' => '5550123'])->json('accounts.*.label'));
    }

    public function test_a_percent_sign_is_matched_literally(): void
    {
        $staff = $this->createSupportUser();
        $this->createClient('100% Office', 'percent@example.com');
        $this->createClient('1000 Office', 'thousand@example.com');

        $response = $this->search($staff, ['group' => 'client', 'q' => '%']);

        $this->assertSame(['100% Office'], $response->json('accounts.*.label'));
        $response->assertJsonPath('total', 1);
    }

    public function test_results_are_capped_and_the_total_counts_every_match(): void
    {
        $staff = $this->createSupportUser();
        $limit = UserDirectoryService::REQUESTER_ACCOUNT_LIMIT;

        foreach (range(1, $limit + 10) as $number) {
            $this->createClient(sprintf('Capped Client %03d', $number), "capped-{$number}@example.com");
        }

        $response = $this->search($staff, ['group' => 'client', 'q' => 'capped']);

        $response->assertJsonCount($limit, 'accounts');
        $response->assertJsonPath('total', $limit + 10);
        $labels = $response->json('accounts.*.label');
        $this->assertSame('Capped Client 001', $labels[0]);
        $this->assertSame(sprintf('Capped Client %03d', $limit), $labels[$limit - 1]);
    }

    public function test_an_empty_search_lists_the_first_accounts_by_name(): void
    {
        $staff = $this->createSupportUser();
        $this->createClient('Zed Client', 'zed@example.com');
        $this->createClient('Abe Client', 'abe@example.com');

        $response = $this->search($staff, ['group' => 'client']);

        $this->assertSame(['Abe Client', 'Zed Client'], $response->json('accounts.*.label'));
        $response->assertJsonPath('total', 2);
    }

    public function test_the_support_group_lists_active_assignable_staff_only(): void
    {
        $staff = $this->createSupportUser('Sam Super', 'sam.super@example.com');
        $this->createUser('Ada Admin', 'ada.admin@example.com', User::ROLE_ADMIN);
        $this->createAssignedSupportUser('Tom Technician', 'tom.technician@example.com');
        $this->createUser('Hidden Shadow', 'hidden.shadow@example.com', User::ROLE_SHADOW);
        $this->createUser('Gone Technician', 'gone.technician@example.com', User::ROLE_TECHNICAL, 'Hoshishi', ['is_active' => false]);
        $this->createClient('Some Client', 'some.client@example.com');

        $response = $this->search($staff, ['group' => 'support']);

        $this->assertSame(['Ada Admin', 'Sam Super', 'Tom Technician'], $response->json('accounts.*.label'));
        $this->assertSame(
            [User::ROLE_ADMIN, User::ROLE_SUPER_USER, User::ROLE_TECHNICAL],
            $response->json('accounts.*.role')
        );
    }

    public function test_a_technician_who_can_create_tickets_may_search_and_a_client_may_not(): void
    {
        $technician = $this->createAssignedSupportUser();
        $client = $this->createClient();

        $this->search($technician, ['group' => 'client'])->assertOk();
        $this->search($client, ['group' => 'client'])->assertForbidden();
    }

    public function test_the_new_ticket_page_lists_the_first_accounts_and_the_one_chosen_before_an_error(): void
    {
        $staff = $this->createSupportUser();
        $limit = UserDirectoryService::REQUESTER_ACCOUNT_LIMIT;

        foreach (range(1, $limit + 5) as $number) {
            $this->createClient(sprintf('Listed Client %03d', $number), "listed-{$number}@example.com");
        }
        $late = $this->createClient('Listed Client 999 Late', 'late@example.com');

        $page = $this->actingAs($staff)
            ->withSession(['_old_input' => ['user_id' => (string) $late->id]])
            ->get(route('admin.tickets.create'));

        $page->assertOk();
        $page->assertSee(sprintf('Listed Client %03d', $limit), false);
        $page->assertDontSee(sprintf('Listed Client %03d', $limit + 1), false);
        $page->assertSee('Listed Client 999 Late', false);
        $page->assertSee('data-requester-search', false);
        $page->assertSee('aria-live="polite" data-requester-account-help', false);
        $page->assertSee('Search accounts by name, email, department or phone', false);
        $page->assertDontSee('email, office or phone', false);
        $page->assertSee(route('admin.tickets.requester-accounts'), false);
        $page->assertSee('"client":'.($limit + 6), false);
    }

    public function test_the_signed_in_staff_member_is_always_listed_for_staff_tickets(): void
    {
        $limit = UserDirectoryService::REQUESTER_ACCOUNT_LIMIT;

        foreach (range(1, $limit + 3) as $number) {
            $this->createAssignedSupportUser(sprintf('Aaron Staff %03d', $number), "aaron-staff-{$number}@example.com");
        }
        $staff = $this->createSupportUser('Zoe Last Alphabetically', 'zoe.last@example.com');

        $page = $this->actingAs($staff)->get(route('admin.tickets.create'));

        $page->assertOk();
        $page->assertSee('Zoe Last Alphabetically', false);
    }
}
