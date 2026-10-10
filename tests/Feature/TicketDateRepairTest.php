<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SystemLog;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Old tracker sheets had close dates before the ticket was logged (a 2015 typo for 2025, or a
 * close time earlier than the log time on the same day). Neither the importer nor the stored
 * data may keep such a date.
 */
class TicketDateRepairTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_the_importer_never_stores_a_completion_date_before_the_logged_date(): void
    {
        $requester = $this->createClient('Placeholder Client', 'placeholder-dates@example.com');
        Category::create(['name' => 'Other', 'description' => 'General', 'color' => '#6B7280', 'is_active' => true]);

        $importPath = storage_path('app/private/imports/legacy-dates.csv');
        File::ensureDirectoryExists(dirname($importPath));
        File::put($importPath, implode(PHP_EOL, [
            'subject,description,created_at,resolved_at,closed_at,category,priority,status',
            '"Year typo","Imported","2025-09-24 10:00:00","2015-09-23 10:30:00","2015-09-23 10:30:00","Other","high","closed"',
            '"Same day, earlier time","Imported","2025-07-29 19:04:00","2025-07-29 17:50:00","2025-07-29 17:50:00","Other","high","closed"',
            '"Fine","Imported","2025-07-29 08:00:00","2025-07-29 17:50:00","2025-07-30 09:00:00","Other","high","closed"',
            '',
        ]));

        $this->artisan('tickets:import', [
            'paths' => ['legacy-dates.csv'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        foreach (['Year typo', 'Same day, earlier time'] as $subject) {
            $ticket = Ticket::query()->where('subject', $subject)->sole();
            $this->assertTrue($ticket->created_at->equalTo($ticket->resolved_at), $subject);
            $this->assertTrue($ticket->created_at->equalTo($ticket->closed_at), $subject);
            $this->assertFalse($ticket->updated_at->lt($ticket->created_at), $subject);
        }

        $fine = Ticket::query()->where('subject', 'Fine')->sole();
        $this->assertSame('2025-07-29 17:50:00', $fine->resolved_at->format('Y-m-d H:i:s'));
        $this->assertSame('2025-07-30 09:00:00', $fine->closed_at->format('Y-m-d H:i:s'));

        File::delete($importPath);
    }

    public function test_the_migration_repairs_stored_tickets_and_keeps_the_old_values_in_the_audit_trail(): void
    {
        $client = $this->createClient();
        $category = $this->createCategory();
        $yearTypo = $this->createTicket($client, $category, ['status' => 'closed']);
        $sameDay = $this->createTicket($client, $category, ['status' => 'closed']);
        $fine = $this->createTicket($client, $category, ['status' => 'closed']);
        $mixedFormats = $this->createTicket($client, $category, ['status' => 'closed']);
        $open = $this->createTicket($client, $category, ['status' => 'open']);

        DB::table('tickets')->where('id', $yearTypo->id)->update([
            'created_at' => '2025-09-24T10:00:00', 'updated_at' => '2015-09-23T10:30:00',
            'resolved_at' => '2015-09-23T10:30:00', 'closed_at' => '2015-09-23T10:30:00',
        ]);
        DB::table('tickets')->where('id', $sameDay->id)->update([
            'created_at' => '2025-07-29 19:04:00', 'updated_at' => '2025-07-29 17:50:00',
            'resolved_at' => '2025-07-29 17:50:00', 'closed_at' => '2025-07-29 17:50:00',
        ]);
        DB::table('tickets')->where('id', $fine->id)->update([
            'created_at' => '2025-07-29T08:00:00', 'updated_at' => '2025-07-30T09:00:00',
            'resolved_at' => '2025-07-29T17:50:00', 'closed_at' => '2025-07-30T09:00:00',
        ]);
        // Same day, in order, but one value uses "T" and the other a space: a text comparison says
        // "later" is smaller, so only parsing the dates gets this right.
        DB::table('tickets')->where('id', $mixedFormats->id)->update([
            'created_at' => '2025-08-01T08:00:00', 'updated_at' => '2025-08-01 15:00:00',
            'resolved_at' => '2025-08-01 15:00:00', 'closed_at' => '2025-08-01 15:00:00',
        ]);

        $migration = require database_path('migrations/2026_10_07_110000_fix_ticket_dates_that_precede_the_logged_date.php');
        $migration->up();

        // PostgreSQL returns every timestamp as "Y-m-d H:i:s" while SQLite hands back the stored text,
        // so dates are compared as moments, not as text.
        $moment = fn (?string $value): ?string => $value === null ? null : Carbon::parse($value)->format('Y-m-d H:i:s');

        $typo = DB::table('tickets')->where('id', $yearTypo->id)->first();
        $this->assertSame(['2025-09-24 10:00:00'], array_values(array_unique(array_map($moment, [$typo->created_at, $typo->resolved_at, $typo->closed_at, $typo->updated_at]))));

        $same = DB::table('tickets')->where('id', $sameDay->id)->first();
        $this->assertSame(['2025-07-29 19:04:00'], array_values(array_unique(array_map($moment, [$same->created_at, $same->resolved_at, $same->closed_at, $same->updated_at]))));

        $untouched = DB::table('tickets')->where('id', $fine->id)->first();
        $this->assertSame(['2025-07-29 17:50:00', '2025-07-30 09:00:00'], [$moment($untouched->resolved_at), $moment($untouched->closed_at)]);

        $mixed = DB::table('tickets')->where('id', $mixedFormats->id)->first();
        $this->assertSame('2025-08-01 15:00:00', $moment($mixed->resolved_at), 'a correct date in a different text format is left alone');

        $this->assertNull(DB::table('tickets')->where('id', $open->id)->value('resolved_at'));

        $log = SystemLog::query()->where('event_type', 'tickets.dates_repaired')->sole();
        $first = $log->metadata['tickets'][0];
        $this->assertSame([$yearTypo->id, $yearTypo->ticket_number], [$first['ticket_id'], $first['ticket_number']]);
        $this->assertSame(['resolved_at', 'closed_at', 'updated_at'], array_keys($first['was']));
        $this->assertSame(array_fill(0, 3, '2015-09-23 10:30:00'), array_map($moment, array_values($first['was'])), 'the old values are kept');
        $this->assertCount(2, $log->metadata['tickets']);

        $migration->up();
        $this->assertSame(1, SystemLog::query()->where('event_type', 'tickets.dates_repaired')->count(), 'running it again finds nothing and writes nothing');
    }

    public function test_a_stored_value_that_is_not_a_date_is_skipped_not_fatal(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Only SQLite stores text that is not a date in a date column.');
        }

        $ticket = $this->createTicket($this->createClient(), $this->createCategory(), ['status' => 'closed']);
        DB::table('tickets')->where('id', $ticket->id)->update(['created_at' => 'sometime in 2025', 'closed_at' => '2025-01-01 00:00:00']);

        (require database_path('migrations/2026_10_07_110000_fix_ticket_dates_that_precede_the_logged_date.php'))->up();

        $this->assertSame('2025-01-01 00:00:00', DB::table('tickets')->where('id', $ticket->id)->value('closed_at'));
        $this->assertSame(0, SystemLog::query()->where('event_type', 'tickets.dates_repaired')->count());
    }
}
