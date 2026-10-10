<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegacyTicketImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_command_preserves_source_created_at_and_uses_default_user(): void
    {
        $superUser = User::create([
            'name' => 'Import Reviewing Super User',
            'username' => 'import.review.super',
            'email' => 'import-review-super@example.com',
            'department' => 'Hoshishi',
            'phone' => '09170000009',
            'role' => User::ROLE_SUPER_USER,
            'password' => 'password',
            'is_active' => true,
        ]);
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import',
            'email' => 'legacy-import@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234567',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);
        $assignedUser = User::create([
            'name' => 'CSV Import Tech',
            'username' => 'csv.import.tech',
            'email' => 'csv-import-tech@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171230001',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'password',
            'is_active' => true,
        ]);

        $importPath = storage_path('app/private/imports/legacy-created-at.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'subject,description,created_at,category,priority,status,name,contact_number,email,province,municipality,assigned_to_email',
            '"Hoshishi Branch - Re-activation of Starlink","Imported from legacy sheet","2025-06-24 15:04:43","Other","high","open","Requester Snapshot","09998887777","requester@example.com","Agusan del Norte","Butuan City","csv-import-tech@example.com"',
            '',
        ]));

        $this->artisan('tickets:import', [
            'paths' => ['legacy-created-at.csv'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $ticket = Ticket::query()->sole();
        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $superUser->id)
            ->sole();
        $expectedCreatedAt = Carbon::parse('2025-06-24 15:04:43', 'Asia/Manila')->utc();

        $this->assertTrue($ticket->created_at?->equalTo($expectedCreatedAt));
        $this->assertTrue($ticket->updated_at?->equalTo($expectedCreatedAt));
        $this->assertTrue($ticket->assigned_at?->equalTo($expectedCreatedAt));
        $this->assertSame($requester->id, $ticket->user_id);
        $this->assertSame($assignedUser->id, $ticket->assigned_to);
        $this->assertTrue($ticket->isImported());
        $this->assertSame('Requester Snapshot', $ticket->name);
        $this->assertSame('Hoshishi Branch - Re-activation of Starlink', $ticket->subject);
        $this->assertTrue($state->acknowledged_at?->equalTo($expectedCreatedAt));
    }

    public function test_import_command_rejects_files_without_created_at_column(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import',
            'email' => 'legacy-import@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234567',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $importPath = storage_path('app/private/imports/missing-created-at.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'subject,description,category',
            '"Missing date column","This import should fail","Other"',
            '',
        ]));

        $this->artisan('tickets:import', [
            'paths' => ['missing-created-at.csv'],
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Import file must include a created_at column so historical ticket dates are preserved.')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_command_skips_existing_ticket_numbers_without_update_flag(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import',
            'email' => 'legacy-import@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234567',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        $category = Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $existingTicket = new Ticket;
        $existingTicket->timestamps = false;
        $existingTicket->forceFill([
            'ticket_number' => 'TK-LEGACY-0001',
            'name' => 'Existing Snapshot',
            'contact_number' => '09170000000',
            'email' => 'existing@example.com',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig',
            'subject' => 'Existing imported ticket',
            'description' => 'Existing description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $requester->id,
            'category_id' => $category->id,
            'created_at' => Carbon::parse('2025-06-01 08:00:00', 'Asia/Manila')->utc(),
            'updated_at' => Carbon::parse('2025-06-01 08:00:00', 'Asia/Manila')->utc(),
        ]);
        $existingTicket->save();

        $importPath = storage_path('app/private/imports/skip-existing.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'ticket_number,subject,description,created_at,category',
            '"TK-LEGACY-0001","Updated subject that should be skipped","Updated description","2025-06-24 15:04:43","Other"',
            '',
        ]));

        $this->artisan('tickets:import', [
            'paths' => ['skip-existing.csv'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $existingTicket->refresh();

        $this->assertSame('Existing imported ticket', $existingTicket->subject);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_import_command_skips_duplicate_ticket_numbers_within_the_same_file(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import.duplicate',
            'email' => 'legacy-import-duplicate@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234570',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $importPath = storage_path('app/private/imports/duplicate-ticket-number.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'ticket_number,subject,description,created_at,category',
            '"TK-LEGACY-2001","First import row","Created first","2025-06-24 15:04:43","Other"',
            '"TK-LEGACY-2001","Duplicate import row","Should be skipped","2025-06-24 16:04:43","Other"',
            '',
        ]));

        $this->artisan('tickets:import', [
            'paths' => ['duplicate-ticket-number.csv'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $ticket = Ticket::query()->sole();

        $this->assertSame('TK-LEGACY-2001', $ticket->ticket_number);
        $this->assertSame('First import row', $ticket->subject);
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_import_command_update_existing_resyncs_assignment_pivot_to_latest_assignee(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import Reassign User',
            'username' => 'legacy.import.reassign',
            'email' => 'legacy-import-reassign@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234571',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);
        $oldTechnical = User::create([
            'name' => 'Legacy Old Tech',
            'username' => 'legacy.old.tech',
            'email' => 'legacy-old-tech@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171230011',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'password',
            'is_active' => true,
        ]);
        $newTechnical = User::create([
            'name' => 'Legacy New Tech',
            'username' => 'legacy.new.tech',
            'email' => 'legacy-new-tech@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171230012',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'password',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'ticket_number' => 'TK-LEGACY-3001',
            'name' => 'Legacy Requester',
            'contact_number' => '09170000088',
            'email' => 'legacy-requester@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Legacy assigned ticket',
            'description' => 'Existing legacy assignment.',
            'priority' => 'medium',
            'status' => 'open',
            'assigned_to' => $oldTechnical->id,
            'assigned_at' => Carbon::parse('2025-06-24 15:04:43', 'Asia/Manila')->utc(),
            'user_id' => $requester->id,
            'category_id' => Category::query()->where('name', 'Other')->value('id'),
            'created_at' => Carbon::parse('2025-06-24 15:04:43', 'Asia/Manila')->utc(),
            'updated_at' => Carbon::parse('2025-06-24 15:04:43', 'Asia/Manila')->utc(),
        ]);
        $ticket->assignedUsers()->sync([$oldTechnical->id]);

        $importPath = storage_path('app/private/imports/reassign-existing.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'ticket_number,subject,description,created_at,category,assigned_to_email',
            '"TK-LEGACY-3001","Reassigned imported ticket","Updated assignment from import","2025-06-24 16:04:43","Other","legacy-new-tech@example.com"',
            '',
        ]));

        $this->artisan('tickets:import', [
            'paths' => ['reassign-existing.csv'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
            '--update-existing' => true,
        ])->assertSuccessful();

        $ticket->refresh();

        $this->assertSame($newTechnical->id, (int) $ticket->assigned_to);
        $this->assertEqualsCanonicalizing([$newTechnical->id], $ticket->assigned_user_ids);
        $this->assertDatabaseMissing('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'user_id' => $oldTechnical->id,
        ]);
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $ticket->id,
            'user_id' => $newTechnical->id,
        ]);
    }
}
