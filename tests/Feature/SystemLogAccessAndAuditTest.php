<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SystemLogAccessAndAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadow_must_unlock_system_logs_with_password(): void
    {
        $shadow = User::create([
            'name' => 'Shadow User',
            'email' => 'shadow-log-access@example.com',
            'phone' => '09123450001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $blockedResponse = $this->actingAs($shadow)->get(route('admin.system-logs.index'));
        $blockedResponse->assertRedirect(route('admin.system-logs.unlock.show'));

        $failedUnlock = $this->actingAs($shadow)
            ->from(route('admin.system-logs.unlock.show'))
            ->post(route('admin.system-logs.unlock.store'), [
                'password' => 'wrong-password',
            ]);
        $failedUnlock->assertRedirect(route('admin.system-logs.unlock.show'));
        $failedUnlock->assertSessionHasErrors('password');

        $unlockResponse = $this->actingAs($shadow)->post(route('admin.system-logs.unlock.store'), [
            'password' => 'ShadowPass123!',
        ]);
        $unlockResponse->assertRedirect(route('admin.system-logs.index'));

        $openResponse = $this->actingAs($shadow)->get(route('admin.system-logs.index'));
        $openResponse->assertOk();
    }

    public function test_shadow_can_export_audit_trail_as_csv(): void
    {
        $shadow = User::create([
            'name' => 'Export Shadow',
            'email' => 'export-shadow@example.com',
            'phone' => '09123450099',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $this->actingAs($shadow)->post(route('admin.system-logs.unlock.store'), [
            'password' => 'ShadowPass123!',
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.system-logs.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertDatabaseHas('system_logs', [
            'event_type' => 'system_logs.exported',
        ]);
    }

    public function test_audit_trail_export_escapes_spreadsheet_formula_values(): void
    {
        $shadow = User::create([
            'name' => 'Formula Export Shadow',
            'email' => 'formula-export-shadow@example.com',
            'phone' => '09123450098',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);
        $actorWithFormulaName = User::create([
            'name' => '=HYPERLINK("https://example.test")',
            'email' => 'formula-actor@example.com',
            'phone' => '09123450097',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('AdminPass123!'),
            'is_active' => true,
        ]);

        SystemLog::create([
            'actor_user_id' => $actorWithFormulaName->id,
            'category' => 'ticket',
            'event_type' => 'ticket.status.updated',
            'description' => 'Updated ticket status.',
            'metadata' => [],
            'occurred_at' => now(),
        ]);

        $this->actingAs($shadow)->post(route('admin.system-logs.unlock.store'), [
            'password' => 'ShadowPass123!',
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.system-logs.export'));
        $response->assertOk();

        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', preg_split('/\R/', trim($csv)) ?: []);

        $formulaCell = collect($rows)->first(fn ($row) => str_contains((string) ($row[1] ?? ''), 'HYPERLINK'));
        $this->assertNotNull($formulaCell);
        $this->assertStringStartsWith("'=", $formulaCell[1]);
    }

    public function test_audit_trail_export_is_not_capped_at_ten_thousand_rows(): void
    {
        $shadow = User::create([
            'name' => 'Chunked Export Shadow',
            'email' => 'chunked-export-shadow@example.com',
            'phone' => '09123450096',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $rowsAcrossMultipleChunks = 250;
        for ($i = 0; $i < $rowsAcrossMultipleChunks; $i++) {
            SystemLog::create([
                'category' => 'ticket',
                'event_type' => 'ticket.status.updated',
                'description' => "Chunk test row {$i}",
                'metadata' => [],
                'occurred_at' => now(),
            ]);
        }

        $this->actingAs($shadow)->post(route('admin.system-logs.unlock.store'), [
            'password' => 'ShadowPass123!',
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.system-logs.export'));
        $response->assertOk();

        $csv = $response->streamedContent();
        $matchedRows = substr_count($csv, 'Chunk test row');

        $this->assertSame($rowsAcrossMultipleChunks, $matchedRows);
    }

    public function test_non_shadow_users_cannot_access_system_logs(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-no-log-access@example.com',
            'phone' => '09123450002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('AdminPass123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.system-logs.unlock.show'));
        $response->assertForbidden();
    }

    public function test_account_settings_update_writes_system_log_without_password_details(): void
    {
        $shadow = User::create([
            'name' => 'Settings Shadow',
            'email' => 'settings-shadow@example.com',
            'phone' => '09123450003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->put(route('account.settings.update'), [
            'name' => 'Settings Shadow Updated',
            'email' => 'settings-shadow@example.com',
            'phone' => '09124440003',
            'department' => 'Hoshishi',
            'current_password' => 'ShadowPass123!',
            'password' => 'NewShadowPass123!',
            'password_confirmation' => 'NewShadowPass123!',
        ]);
        $response->assertRedirect(route('account.settings'));

        $log = SystemLog::query()
            ->where('event_type', 'account.settings.updated')
            ->where('target_id', $shadow->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('account', $log->category);
        $encodedMetadata = json_encode($log->metadata);
        $this->assertIsString($encodedMetadata);
        $this->assertStringNotContainsString('NewShadowPass123!', $encodedMetadata);
        $this->assertStringNotContainsString('"password"', strtolower($encodedMetadata));
        $this->assertStringNotContainsString('"current_password"', strtolower($encodedMetadata));
    }

    public function test_ticket_assignment_writes_system_log_entry(): void
    {
        $shadow = User::create([
            'name' => 'Ticket Shadow',
            'email' => 'ticket-shadow@example.com',
            'phone' => '09123450004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('ShadowPass123!'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Ticket Technical',
            'email' => 'ticket-technical@example.com',
            'phone' => '09123450005',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('TechPass123!'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Ticket Client',
            'email' => 'ticket-client@example.com',
            'phone' => '09123450006',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('ClientPass123!'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'System Log Category',
            'description' => 'Category for system log assignment test',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Requester',
            'contact_number' => '09123456789',
            'email' => 'requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Assignment system log check',
            'description' => 'Checking assignment logging.',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $client->id,
            'category_id' => $category->id,
        ]);

        $response = $this->actingAs($shadow)
            ->from(route('admin.tickets.show', $ticket))
            ->post(route('admin.tickets.assign', $ticket), [
                'assigned_to' => $technical->id,
            ]);
        $response->assertRedirect(route('admin.tickets.show', $ticket));

        $this->assertDatabaseHas('system_logs', [
            'event_type' => 'ticket.assignment.updated',
            'target_type' => Ticket::class,
            'target_id' => $ticket->id,
        ]);
    }
}
