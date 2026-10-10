<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Department;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\ManagedUserDeletionService;
use App\Services\TicketImport\ImportedTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * A ticket stores the department it came from (the "Field Office" in reports), so it stays right
 * when its owner is renamed, moved to another department, deleted, or was a placeholder account.
 */
class TicketDepartmentTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_a_new_ticket_takes_its_owners_department(): void
    {
        $client = $this->createUser('Juniper Client', 'juniper-client@example.com', User::ROLE_CLIENT, 'Juniper');

        $ticket = $this->createTicket($client, $this->createCategory());

        $this->assertSame('Juniper', $ticket->fresh()->department);
    }

    public function test_a_department_given_on_creation_is_kept(): void
    {
        $client = $this->createUser('Juniper Client', 'juniper-client2@example.com', User::ROLE_CLIENT, 'Juniper');

        $ticket = $this->createTicket($client, $this->createCategory(), ['department' => 'Alder']);

        $this->assertSame('Alder', $ticket->fresh()->department);
    }

    public function test_a_client_filing_through_the_form_gets_their_department(): void
    {
        config(['legal.require_acceptance' => false]);
        $client = $this->createUser('Ember Client', 'ember-client@example.com', User::ROLE_CLIENT, 'Ember');
        $category = $this->createCategory();

        $this->actingAs($client)->post(route('client.tickets.store'), [
            'name' => 'Ember Client',
            'contact_number' => '09189990001',
            'email' => 'ember-client@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig City',
            'subject' => 'Network outage',
            'description' => 'Need help.',
            'category_id' => $category->id,
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ])->assertRedirect();

        $this->assertSame('Ember', Ticket::query()->latest('id')->firstOrFail()->department);
    }

    public function test_the_department_survives_the_owner_moving_or_being_deleted(): void
    {
        $client = $this->createUser('Moving Client', 'moving-client@example.com', User::ROLE_CLIENT, 'Juniper');
        $ticket = $this->createTicket($client, $this->createCategory());

        $client->update(['department' => 'Alder']);
        $this->assertSame('Juniper', $ticket->fresh()->department, 'moving the owner does not move the ticket');

        app(ManagedUserDeletionService::class)->delete($client);

        $ticket = $ticket->fresh();
        $this->assertSame('Juniper', $ticket->department, 'the placeholder owner is in the support department, the ticket is not');
        $this->assertSame('Hoshishi', $ticket->user->department);
    }

    public function test_renaming_a_department_renames_it_on_tickets_without_counting_as_activity(): void
    {
        $admin = $this->createUser('Admin User', 'admin-rename-dept@example.com', User::ROLE_ADMIN);
        $client = $this->createUser('Birch Client', 'birch-client@example.com', User::ROLE_CLIENT, 'Birch');
        $other = $this->createUser('Alder Client', 'alder-client@example.com', User::ROLE_CLIENT, 'Alder');
        $category = $this->createCategory();
        $ticket = $this->createTicket($client, $category);
        $otherTicket = $this->createTicket($other, $category);
        $touched = Carbon::parse('2026-01-02 03:04:05');
        DB::table('tickets')->update(['updated_at' => $touched]);

        $this->actingAs($admin)->put(route('admin.departments.update', Department::where('name', 'Birch')->firstOrFail()), [
            'name' => 'Birch Region 5',
        ])->assertRedirect(route('admin.departments.index'));

        $this->assertSame('Birch Region 5', $ticket->fresh()->department);
        $this->assertSame('Birch Region 5', $client->fresh()->department);
        $this->assertSame('Alder', $otherTicket->fresh()->department);
        $this->assertTrue($touched->equalTo($ticket->fresh()->updated_at), 'a rename is not activity on the ticket');
    }

    public function test_the_custom_report_groups_and_filters_by_the_ticket_department(): void
    {
        $admin = $this->createUser('Admin User', 'admin-dept-report@example.com', User::ROLE_ADMIN);
        $client = $this->createUser('Moved Client', 'moved-client@example.com', User::ROLE_CLIENT, 'Juniper');
        $category = $this->createCategory();
        $this->createTicket($client, $category);
        $this->createTicket($client, $category);
        $client->update(['department' => 'Alder']);
        $this->createTicket($client, $category);
        $placeholderOwned = $this->createTicket($this->createClient(), $category, ['department' => 'Fjord']);

        $this->actingAs($admin)->get(route('admin.reports.custom.index', ['group_by' => 'department']))
            ->assertOk()
            ->assertViewHas('summaryRows', function ($rows) {
                $counts = $rows->pluck('count', 'label')->all();

                return $counts === ['Juniper' => 2, 'Alder' => 1, 'Fjord' => 1];
            });

        $this->actingAs($admin)->get(route('admin.reports.custom.index', ['layout' => 'detailed', 'department' => 'Fjord']))
            ->assertOk()
            ->assertSee($placeholderOwned->ticket_number)
            ->assertViewHas('detailedRows', fn ($rows) => $rows->total() === 1);

        $csv = $this->actingAs($admin)->get(route('admin.reports.custom.csv', ['layout' => 'detailed', 'department' => 'Fjord']))->streamedContent();
        $this->assertStringContainsString('Fjord', $csv);
        $this->assertStringNotContainsString('Juniper', $csv);
    }

    public function test_the_ticket_list_search_finds_tickets_by_department(): void
    {
        $admin = $this->createUser('Admin User', 'admin-dept-search@example.com', User::ROLE_ADMIN);
        $category = $this->createCategory();
        $this->createTicket($this->createClient(), $category, ['subject' => 'Printer jammed', 'department' => 'Alder']);
        $this->createTicket($this->createClient('Other Client', 'other-client@example.com'), $category, ['subject' => 'Router offline', 'department' => 'Juniper']);

        $this->actingAs($admin)->get(route('admin.tickets.index', ['tab' => 'all', 'search' => 'Alder']))
            ->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->pluck('subject')->all() === ['Printer jammed']);
    }

    public function test_an_admin_can_correct_the_department_and_it_is_logged(): void
    {
        $admin = $this->createUser('Admin User', 'admin-set-dept@example.com', User::ROLE_ADMIN);
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['department' => 'Hoshishi']);

        $this->actingAs($admin)->post(route('admin.tickets.department', $ticket), ['department' => 'Alder'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Ticket department updated successfully!');

        $this->assertSame('Alder', $ticket->fresh()->department);
        $log = SystemLog::query()->where('event_type', 'ticket.department.updated')->sole();
        $this->assertSame(['ticket_number' => $ticket->ticket_number, 'previous_department' => 'Hoshishi', 'new_department' => 'Alder'], $log->metadata);

        $this->actingAs($admin)->post(route('admin.tickets.department', $ticket), ['department' => 'Alder'])
            ->assertSessionHas('success', 'No changes were detected.');
        $this->assertSame(1, SystemLog::query()->where('event_type', 'ticket.department.updated')->count());
    }

    public function test_only_a_listed_department_can_be_chosen_and_only_by_admin_level_staff(): void
    {
        $admin = $this->createUser('Admin User', 'admin-bad-dept@example.com', User::ROLE_ADMIN);
        $technician = $this->createAssignedSupportUser();
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['department' => 'Hoshishi']);
        $ticket->assignedUsers()->sync([$technician->id]);
        $ticket->update(['assigned_to' => $technician->id]);

        $this->actingAs($admin)->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.department', $ticket), ['department' => 'No Such Office'])
            ->assertSessionHasErrors('department');

        $this->actingAs($technician)->post(route('admin.tickets.department', $ticket), ['department' => 'Alder'])->assertForbidden();
        $this->actingAs($client)->post(route('admin.tickets.department', $ticket), ['department' => 'Alder'])->assertForbidden();

        $this->assertSame('Hoshishi', $ticket->fresh()->department);
    }

    public function test_the_ticket_page_shows_the_field_office_and_only_admin_level_staff_get_the_form(): void
    {
        $admin = $this->createUser('Admin User', 'admin-show-dept@example.com', User::ROLE_ADMIN);
        $technician = $this->createAssignedSupportUser();
        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['department' => 'Juniper']);
        $ticket->assignedUsers()->sync([$technician->id]);
        $ticket->update(['assigned_to' => $technician->id]);

        $this->actingAs($admin)->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSeeInOrder(['Field Office', 'Juniper'])
            ->assertSee(route('admin.tickets.department', $ticket), false);

        $this->actingAs($technician)->get(route('admin.tickets.show', $ticket))
            ->assertOk()
            ->assertSeeInOrder(['Field Office', 'Juniper'])
            ->assertDontSee(route('admin.tickets.department', $ticket), false);
    }

    public function test_imported_tickets_under_the_support_team_placeholder_are_sorted_by_their_subject(): void
    {
        $import = app(ImportedTicketService::class);

        $this->assertSame('Alder', $import->applyImportMetadata(['subject' => 'Alder - Printer jammed'], 'Hoshishi')['department']);
        $this->assertSame('Juniper', $import->applyImportMetadata(['subject' => 'Juniper ANTIQUE - Starlink'], 'Hoshishi')['department']);
        $this->assertSame('Hoshishi', $import->applyImportMetadata(['subject' => 'PPA - No signal'], 'Hoshishi')['department'], 'an organisation that is not a department keeps the placeholder');
        $this->assertSame('Birch', $import->applyImportMetadata(['subject' => 'Alder - listed for another office'], 'Birch')['department'], 'a requester with a real department keeps it');
        $this->assertNull($import->applyImportMetadata(['subject' => 'No prefix here'], null)['department']);
    }

    public function test_the_import_command_saves_the_department(): void
    {
        $requester = $this->createClient('Placeholder Client', 'placeholder-client@example.com');
        Category::create(['name' => 'Other', 'description' => 'General', 'color' => '#6B7280', 'is_active' => true]);

        $importPath = storage_path('app/private/imports/legacy-departments.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'subject,description,created_at,category,priority,status',
            '"Fjord BARMM - Router request","Imported","2025-06-24 15:04:43","Other","high","open"',
            '"PPA - No signal","Imported","2025-06-25 15:04:43","Other","high","open"',
            '',
        ]));

        $this->artisan('tickets:import', [
            'paths' => ['legacy-departments.csv'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $this->assertSame(
            ['Fjord BARMM - Router request' => 'Fjord', 'PPA - No signal' => 'Hoshishi'],
            Ticket::query()->pluck('department', 'subject')->all()
        );

        File::delete($importPath);
    }

    public function test_the_migration_gives_existing_tickets_a_department(): void
    {
        $placeholder = $this->createClient('Placeholder', 'placeholder@example.com');
        $juniper = $this->createUser('Juniper Client', 'juniper-migration@example.com', User::ROLE_CLIENT, 'Juniper');
        $category = $this->createCategory();

        $imported = $this->createTicket($placeholder, $category, ['subject' => 'Alder - Printer jammed', 'is_imported' => true]);
        $unlisted = $this->createTicket($placeholder, $category, ['subject' => 'PPA - No signal', 'is_imported' => true]);
        $typed = $this->createTicket($placeholder, $category, ['subject' => 'Alder - typed by hand']);
        $own = $this->createTicket($juniper, $category, ['subject' => 'Alder - owner has a department', 'is_imported' => true]);
        DB::table('tickets')->update(['department' => null]);

        (require database_path('migrations/2026_10_07_100000_add_department_to_tickets_table.php'))->up();

        $this->assertSame('Alder', $imported->fresh()->department);
        $this->assertSame('Hoshishi', $unlisted->fresh()->department);
        $this->assertSame('Hoshishi', $typed->fresh()->department, 'only imported tickets are sorted by subject');
        $this->assertSame('Juniper', $own->fresh()->department, 'a real owner department wins over the subject');
    }

    public function test_the_ticket_list_can_be_filtered_by_field_office(): void
    {
        $admin = $this->createUser('Admin User', 'admin-dept-filter@example.com', User::ROLE_ADMIN);
        $category = $this->createCategory();
        $this->createTicket($this->createClient(), $category, ['subject' => 'Alder ticket', 'department' => 'Alder']);
        $this->createTicket($this->createClient('Other Client', 'dept-filter-other@example.com'), $category, ['subject' => 'Juniper ticket', 'department' => 'Juniper']);

        $subjects = fn (array $query) => $this->actingAs($admin)
            ->get(route('admin.tickets.index', ['tab' => 'all'] + $query))
            ->assertOk()
            ->viewData('tickets')->pluck('subject')->sort()->values()->all();

        $this->assertSame(['Alder ticket'], $subjects(['department' => 'Alder']));
        $this->assertSame(['Alder ticket', 'Juniper ticket'], $subjects(['department' => 'all']));
        $this->assertSame(['Alder ticket', 'Juniper ticket'], $subjects([]));
        $this->assertSame([], $subjects(['department' => 'No Such Office']));
    }

    public function test_the_filter_offers_every_department_and_keeps_the_choice(): void
    {
        $admin = $this->createUser('Admin User', 'admin-dept-select@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)->get(route('admin.tickets.index', ['tab' => 'all', 'department' => 'Alder']))
            ->assertOk()
            ->assertSee('All Field Offices')
            ->assertSee('<option value="Alder" selected>Alder</option>', false)
            ->assertSee('<option value="Juniper" >Juniper</option>', false);
    }

    public function test_the_ticket_export_has_a_field_office_column_and_follows_the_filter(): void
    {
        $admin = $this->createUser('Admin User', 'admin-dept-export@example.com', User::ROLE_ADMIN);
        $category = $this->createCategory();
        $this->createTicket($this->createClient(), $category, ['subject' => 'Alder ticket', 'department' => 'Alder']);
        $this->createTicket($this->createClient('Other Client', 'dept-export-other@example.com'), $category, ['subject' => 'Juniper ticket', 'department' => 'Juniper']);

        $csv = $this->actingAs($admin)->get(route('admin.tickets.export', ['tab' => 'all', 'department' => 'Alder']))->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\R/', trim($csv)) ?: []);

        $this->assertSame('Field Office', end($rows[0]));
        $this->assertCount(2, $rows, 'the header and the one Alder ticket');
        $this->assertSame(['Alder ticket', 'Alder'], [$rows[1][1], end($rows[1])]);
    }
}
