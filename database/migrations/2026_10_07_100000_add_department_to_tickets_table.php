<?php

use App\Support\SubjectDepartment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A ticket remembers the department it came from.
 *
 * Reports used to read the department from the ticket's current owner. That broke whenever the
 * owner changed: the 600-odd legacy tickets all belong to one placeholder account in the support
 * department, and a deleted client's tickets move to a placeholder account too. The department is
 * now stored on the ticket itself and survives both.
 *
 * Existing tickets start with their owner's department. Imported tickets owned by the support
 * team's placeholder account are then sorted by the department named at the start of their
 * subject ("Alder - Printer jammed"); prefixes that name no known department stay as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'department')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('department')->nullable()->index();
            });
        }

        DB::table('tickets')->whereNull('department')->update([
            'department' => DB::table('users')
                ->select('department')
                ->whereColumn('users.id', 'tickets.user_id'),
        ]);

        $this->sortImportedTicketsBySubject();
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['department']);
            $table->dropColumn('department');
        });
    }

    private function sortImportedTicketsBySubject(): void
    {
        $names = DB::table('departments')->pluck('name')->all();
        if ($names === []) {
            return;
        }

        $support = trim((string) config('hoshiask.support_department', 'Hoshishi'));

        DB::table('tickets')
            ->where('is_imported', true)
            ->where('department', $support)
            ->orderBy('id')
            ->select(['id', 'subject'])
            ->chunkById(200, function ($tickets) use ($names, $support): void {
                foreach ($tickets as $ticket) {
                    $department = SubjectDepartment::match((string) $ticket->subject, $names);

                    if ($department !== null && $department !== $support) {
                        DB::table('tickets')->where('id', $ticket->id)->update(['department' => $department]);
                    }
                }
            });
    }
};
