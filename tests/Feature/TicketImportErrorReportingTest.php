<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TicketImportErrorReportingTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'error-reporting-test.csv';

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requester = User::create([
            'name' => 'Import Requester',
            'username' => 'import.requester',
            'email' => 'import-requester@example.com',
            'department' => 'Hoshishi',
            'phone' => '09170000098',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);
        Category::create(['name' => 'Other', 'description' => 'Other', 'color' => '#6B7280', 'is_active' => true]);

        // Rows 2 and 4 are fine; rows 3 and 5 each have a different problem.
        $path = storage_path('app/private/imports/'.self::FILE);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode(PHP_EOL, [
            'subject,created_at,priority,status',
            '"Good one","2025-06-24 15:04:43","high","open"',
            '"Bad priority","2025-06-24 16:00:00","urgentish","open"',
            '"Good two","2025-06-25 09:00:00","low","done"',
            '"Bad status","2025-06-25 10:00:00","low","archived"',
            '',
        ]));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('app/private/imports/'.self::FILE));

        parent::tearDown();
    }

    public function test_every_invalid_row_is_reported_in_one_run_and_nothing_is_saved(): void
    {
        $this->artisan('tickets:import', ['paths' => [self::FILE], '--default-user' => (string) $this->requester->id])
            ->expectsOutput('Row 3 has an unsupported priority: urgentish')
            ->expectsOutput('Row 5 has an unsupported status: archived')
            ->expectsOutput('Found 2 invalid row(s); nothing was imported. Fix them, or re-run with --skip-invalid to import the valid rows.')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_dry_run_lists_problems_and_counts_without_saving(): void
    {
        $this->artisan('tickets:import', ['paths' => [self::FILE], '--default-user' => (string) $this->requester->id, '--dry-run' => true])
            ->expectsOutput('Row 3 has an unsupported priority: urgentish')
            ->expectsOutput('Row 5 has an unsupported status: archived')
            ->expectsOutput('Dry run complete. rows=4 valid=2 invalid=2')
            ->assertFailed();

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_skip_invalid_saves_the_valid_rows_and_reports_the_rest(): void
    {
        $this->artisan('tickets:import', ['paths' => [self::FILE], '--default-user' => (string) $this->requester->id, '--skip-invalid' => true])
            ->expectsOutput('Row 3 has an unsupported priority: urgentish')
            ->expectsOutput('Import complete. created=2 updated=0 skipped=0 rows=4 invalid=2')
            ->assertSuccessful();

        $this->assertSame(['Good one', 'Good two'], Ticket::query()->orderBy('subject')->pluck('subject')->all());
        $this->assertSame('resolved', Ticket::query()->where('subject', 'Good two')->value('status'));
    }

    public function test_former_command_names_still_work(): void
    {
        $commands = Artisan::all();

        $this->assertSame('tickets:import', $commands['tickets:import-csv']->getName());
        $this->assertSame('tickets:import', $commands['tickets:import-xlsx']->getName());
    }

    public function test_xlsx_files_require_a_default_user_and_unknown_types_are_rejected(): void
    {
        $workbook = storage_path('app/private/imports/needs-default-user.xlsx');
        $notes = storage_path('app/private/imports/notes.pdf');
        File::put($workbook, 'placeholder');
        File::put($notes, 'placeholder');

        try {
            $this->artisan('tickets:import', ['paths' => ['needs-default-user.xlsx']])
                ->expectsOutput('Tracker XLSX files need --default-user because they do not include requester accounts.')
                ->assertFailed();

            $this->artisan('tickets:import', ['paths' => ['notes.pdf']])
                ->expectsOutput('Unsupported import file type (use .csv or .xlsx): notes.pdf')
                ->assertFailed();
        } finally {
            File::delete([$workbook, $notes]);
        }
    }
}
