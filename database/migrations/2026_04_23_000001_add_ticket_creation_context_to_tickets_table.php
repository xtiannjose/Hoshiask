<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TICKET_TARGET_TYPE = 'App\\Models\\Ticket';

    private const EVENT_CLIENT_CREATED = 'ticket.created';

    private const EVENT_SUPPORT_CREATED = 'ticket.created_by_support_user';

    private const EVENT_LEGACY_SUPPORT_CREATED = 'ticket.created_by_super_user';

    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('creation_source', 32)
                ->nullable()
                ->after('ticket_type');
            $table->index('creation_source');
        });

        DB::table('tickets')
            ->where('is_imported', true)
            ->update([
                'creation_source' => 'imported',
            ]);

        if (! Schema::hasTable('system_logs')) {
            return;
        }

        DB::table('tickets')
            ->select(['id', 'user_id', 'ticket_type', 'is_imported'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $tickets): void {
                $ticketIds = $tickets->pluck('id')->map(fn ($id) => (int) $id)->all();
                $requesterIds = $tickets->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->all();

                $requesterRoles = DB::table('users')
                    ->whereIn('id', $requesterIds)
                    ->pluck('role', 'id');

                $creationLogs = DB::table('system_logs')
                    ->select(['target_id', 'event_type', 'actor_user_id'])
                    ->where('target_type', self::TICKET_TARGET_TYPE)
                    ->whereIn('target_id', $ticketIds)
                    ->whereIn('event_type', [
                        self::EVENT_CLIENT_CREATED,
                        self::EVENT_SUPPORT_CREATED,
                        self::EVENT_LEGACY_SUPPORT_CREATED,
                    ])
                    ->orderBy('id')
                    ->get()
                    ->groupBy('target_id')
                    ->map(fn (Collection $logs) => $logs->first());

                foreach ($tickets as $ticket) {
                    if ((bool) ($ticket->is_imported ?? false)) {
                        continue;
                    }

                    $creationLog = $creationLogs->get((int) $ticket->id);
                    if (! $creationLog) {
                        continue;
                    }

                    $requesterRole = strtolower(trim((string) ($requesterRoles[(int) $ticket->user_id] ?? '')));
                    $creationSource = $this->resolvedCreationSource(
                        (string) $creationLog->event_type,
                        $requesterRole
                    );

                    if ($creationSource === null) {
                        continue;
                    }

                    DB::table('tickets')
                        ->where('id', (int) $ticket->id)
                        ->update([
                            'created_by_user_id' => $creationLog->actor_user_id ? (int) $creationLog->actor_user_id : null,
                            'creation_source' => $creationSource,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_creation_source_index');
            $table->dropColumn('creation_source');
            $table->dropConstrainedForeignId('created_by_user_id');
        });
    }

    private function resolvedCreationSource(string $eventType, string $requesterRole): ?string
    {
        if ($eventType === self::EVENT_CLIENT_CREATED) {
            return 'client_self_service';
        }

        if (! in_array($eventType, [self::EVENT_SUPPORT_CREATED, self::EVENT_LEGACY_SUPPORT_CREATED], true)) {
            return null;
        }

        return $requesterRole === 'client'
            ? 'staff_for_client'
            : 'staff_for_staff';
    }
};
