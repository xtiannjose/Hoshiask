<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Some imported tickets were closed before they were logged (18 of 656 in the first data set: a
 * close date typed as 2015 for a 2025 ticket, a close time earlier than the log time on the same
 * day). Reports counted them as resolved in 0 minutes and the ticket page showed a close date
 * before the ticket existed. Such a date becomes the logged date.
 *
 * The old values are written to the audit trail in one entry so any of them can be restored by
 * hand. The comparison is done on parsed dates, not on the stored text, because SQLite keeps a
 * "T" in some values and a space in others.
 */
return new class extends Migration
{
    private const COLUMNS = ['resolved_at', 'closed_at', 'updated_at'];

    public function up(): void
    {
        $repaired = [];

        DB::table('tickets')
            ->select(['id', 'ticket_number', 'created_at', ...self::COLUMNS])
            ->whereNotNull('created_at')
            ->where(fn ($query) => $query->whereNotNull('resolved_at')->orWhereNotNull('closed_at'))
            ->orderBy('id')
            ->chunkById(500, function ($tickets) use (&$repaired): void {
                foreach ($tickets as $ticket) {
                    // A value that is not a date at all is left for someone to look at, not guessed.
                    $loggedAt = rescue(fn () => Carbon::parse($ticket->created_at), null, false);
                    if ($loggedAt === null) {
                        continue;
                    }

                    $changes = [];
                    $was = [];

                    foreach (self::COLUMNS as $column) {
                        $value = $ticket->{$column} === null ? null : rescue(fn () => Carbon::parse($ticket->{$column}), null, false);
                        if ($value !== null && $value->lt($loggedAt)) {
                            $changes[$column] = $ticket->created_at;
                            $was[$column] = (string) $ticket->{$column};
                        }
                    }

                    if ($changes !== []) {
                        DB::table('tickets')->where('id', $ticket->id)->update($changes);
                        $repaired[] = ['ticket_id' => (int) $ticket->id, 'ticket_number' => $ticket->ticket_number, 'was' => $was];
                    }
                }
            });

        if ($repaired !== [] && Schema::hasTable('system_logs')) {
            DB::table('system_logs')->insert([
                'category' => 'system',
                'event_type' => 'tickets.dates_repaired',
                'description' => count($repaired).' ticket(s) had a resolved, closed or updated date before the logged date; it was set to the logged date. The old values are in the details.',
                'metadata' => json_encode(['tickets' => $repaired], JSON_UNESCAPED_SLASHES),
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};
