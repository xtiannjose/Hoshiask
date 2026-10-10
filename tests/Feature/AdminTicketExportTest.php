<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminTicketExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ticket_export_uses_current_filters(): void
    {
        $supportUser = $this->createSupportUser('Export Admin', 'export-admin@example.com');
        $category = $this->createCategory();
        $client = $this->createClient();

        $matchingTicket = $this->createTicket($client, $category, 'Exported severity one ticket', 'severity_1');
        $otherTicket = $this->createTicket($client, $category, 'Skipped severity three ticket', 'severity_3');

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.export', [
            'tab' => 'tickets',
            'priority' => 'severity_1',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Ticket Number",Subject,Requester,Status,Severity', $csv);
        $this->assertStringContainsString($matchingTicket->ticket_number, $csv);
        $this->assertStringContainsString('Exported severity one ticket', $csv);
        $this->assertStringNotContainsString($otherTicket->ticket_number, $csv);
        $this->assertStringNotContainsString('Skipped severity three ticket', $csv);
    }

    public function test_export_lists_every_ticket_once_in_a_stable_order_when_many_share_a_timestamp(): void
    {
        $supportUser = $this->createSupportUser('Export Tie Admin', 'export-tie-admin@example.com');
        $category = $this->createCategory();
        $client = $this->createClient();

        // More than one 200-row page, all with the same created_at (what an import produces).
        $tickets = collect(range(1, 250))->map(fn (int $n) => $this->createTicket($client, $category, "Tie ticket {$n}", 'severity_2'));
        Ticket::query()->update(['created_at' => '2026-03-01 09:00:00']);

        $csv = $this->actingAs($supportUser)
            ->get(route('admin.tickets.export', ['tab' => 'tickets']))
            ->streamedContent();

        preg_match_all('/TK-[A-Z0-9]+/', $csv, $matches);
        $exported = $matches[0];

        $this->assertCount(250, $exported);
        $this->assertCount(250, array_unique($exported), 'A ticket was exported twice.');
        $this->assertSame(
            $tickets->sortByDesc('id')->pluck('ticket_number')->values()->all(),
            $exported,
            'Tickets with equal timestamps must come out newest id first.'
        );
    }

    public function test_technician_ticket_export_respects_ticket_scope(): void
    {
        $technician = $this->createTechnicalUser();
        $category = $this->createCategory();
        $client = $this->createClient();

        $assignedTicket = $this->createTicket($client, $category, 'Technician exported ticket', 'severity_2');
        $assignedTicket->assignedUsers()->syncWithoutDetaching([$technician->id]);
        $unassignedTicket = $this->createTicket($client, $category, 'Technician hidden ticket', 'severity_2');

        $response = $this->actingAs($technician)->get(route('admin.tickets.export', [
            'tab' => 'tickets',
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString($assignedTicket->ticket_number, $csv);
        $this->assertStringContainsString('Technician exported ticket', $csv);
        $this->assertStringNotContainsString($unassignedTicket->ticket_number, $csv);
        $this->assertStringNotContainsString('Technician hidden ticket', $csv);
    }

    public function test_admin_ticket_index_links_to_filtered_export(): void
    {
        $supportUser = $this->createSupportUser('Export Link Admin', 'export-link-admin@example.com');

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.index', [
            'tab' => 'tickets',
            'priority' => 'severity_1',
            'assigned_to' => '0',
        ]));

        $response->assertOk();
        $response->assertSee('Export CSV');
        $response->assertSee('priority=severity_1', false);
        $response->assertSee('assigned_to=0', false);
    }

    public function test_admin_ticket_export_escapes_spreadsheet_formula_values(): void
    {
        $supportUser = $this->createSupportUser('Export Formula Admin', 'export-formula-admin@example.com');
        $category = $this->createCategory();
        $client = $this->createClient();

        $ticket = $this->createTicket($client, $category, '=HYPERLINK("https://example.test")', 'severity_1');

        $response = $this->actingAs($supportUser)->get(route('admin.tickets.export', [
            'tab' => 'tickets',
            'search' => $ticket->ticket_number,
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\R/', trim($csv)) ?: []);

        $this->assertSame('\'=HYPERLINK("https://example.test")', $rows[1][1] ?? null);
    }

    private function createSupportUser(string $name, string $email): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => '09110000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createTechnicalUser(): User
    {
        return User::create([
            'name' => 'Export Technician',
            'email' => 'export-technician@example.com',
            'phone' => '09113334444',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createClient(): User
    {
        return User::create([
            'name' => 'Export Client',
            'email' => 'export-client@example.com',
            'phone' => '09112223333',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
    }

    private function createCategory(): Category
    {
        return Category::create([
            'name' => 'Export Category',
            'description' => 'Export checks',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);
    }

    private function createTicket(User $client, Category $category, string $subject, string $priority): Ticket
    {
        return Ticket::create([
            'name' => 'Export Requester',
            'contact_number' => '09110000200',
            'email' => 'export-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => $subject,
            'description' => 'Export filter check.',
            'priority' => $priority,
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);
    }
}
