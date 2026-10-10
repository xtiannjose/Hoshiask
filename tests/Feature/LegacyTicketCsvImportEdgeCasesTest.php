<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LegacyTicketCsvImportEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_rejects_unsupported_priority_value(): void
    {
        $requester = $this->createRequester();
        $this->createOtherCategory();

        $this->writeImportCsv('bad-priority.csv', [
            'subject,description,created_at,category,priority',
            '"Bad priority","Body","2025-06-24 15:04:43","Other","urgentish"',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['bad-priority.csv'],
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Row 2 has an unsupported priority: urgentish')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_rejects_unsupported_status_value(): void
    {
        $requester = $this->createRequester();
        $this->createOtherCategory();

        $this->writeImportCsv('bad-status.csv', [
            'subject,description,created_at,category,status',
            '"Bad status","Body","2025-06-24 15:04:43","Other","archived"',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['bad-status.csv'],
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Row 2 has an unsupported status: archived')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_rejects_invalid_created_at_value(): void
    {
        $requester = $this->createRequester();
        $this->createOtherCategory();

        $this->writeImportCsv('bad-date.csv', [
            'subject,description,created_at,category',
            '"Bad date","Body","not-a-date","Other"',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['bad-date.csv'],
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Row 2 has an invalid created_at value: not-a-date')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_rejects_unknown_category_reference(): void
    {
        $requester = $this->createRequester();

        $this->writeImportCsv('bad-category.csv', [
            'subject,description,created_at,category',
            '"Unknown category","Body","2025-06-24 15:04:43","Nonexistent Category"',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['bad-category.csv'],
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Row 2 references an unknown category: Nonexistent Category')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_rejects_row_with_no_resolvable_requester_and_no_default_user(): void
    {
        $this->createOtherCategory();

        $this->writeImportCsv('no-requester.csv', [
            'subject,description,created_at,category',
            '"No requester","Body","2025-06-24 15:04:43","Other"',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['no-requester.csv'],
        ])
            ->expectsOutput('Row 2 could not resolve a requester. Provide user_id, user_email, or user_username columns, or pass --default-user.')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_rejects_files_with_duplicate_header_columns(): void
    {
        $requester = $this->createRequester();
        $this->createOtherCategory();

        $this->writeImportCsv('duplicate-column.csv', [
            'subject,description,created_at,category,category',
            '"Duplicate column","Body","2025-06-24 15:04:43","Other","Other"',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['duplicate-column.csv'],
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Duplicate import column detected: category')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_import_skips_blank_rows_without_counting_them(): void
    {
        $requester = $this->createRequester();
        $this->createOtherCategory();

        $this->writeImportCsv('blank-rows.csv', [
            'subject,description,created_at,category',
            '"First ticket","Body","2025-06-24 15:04:43","Other"',
            ',,,',
            '',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['blank-rows.csv'],
            '--default-user' => (string) $requester->id,
        ])
            ->expectsOutput('Import complete. created=1 updated=0 skipped=0 rows=1')
            ->assertSuccessful();

        $this->assertDatabaseCount('tickets', 1);
    }

    private function createRequester(): User
    {
        return User::create([
            'name' => 'Edge Case Requester',
            'username' => 'edge.case.requester',
            'email' => 'edge-case-requester@example.com',
            'department' => 'Hoshishi',
            'phone' => '09170000099',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    private function createOtherCategory(): Category
    {
        return Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function writeImportCsv(string $filename, array $lines): void
    {
        $importPath = storage_path('app/private/imports/'.$filename);
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [...$lines, '']));
    }
}
