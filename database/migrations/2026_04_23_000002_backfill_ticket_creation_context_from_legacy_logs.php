<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TICKET_TARGET_TYPE = 'App\\Models\\Ticket';

    private const SUPPORT_CREATE_EVENTS = [
        'ticket.created_by_support_user',
        'ticket.created_by_super_user',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'creation_source') || ! Schema::hasTable('system_logs')) {
            return;
        }

        DB::table('tickets')
            ->select(['id', 'user_id', 'ticket_type', 'created_by_user_id', 'creation_source'])
            ->whereNull('creation_source')
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
                    ->whereIn('event_type', array_merge(['ticket.created'], self::SUPPORT_CREATE_EVENTS))
                    ->orderBy('id')
                    ->get()
                    ->groupBy('target_id')
                    ->map(fn (Collection $logs) => $logs->first());

                foreach ($tickets as $ticket) {
                    $creationLog = $creationLogs->get((int) $ticket->id);
                    if (! $creationLog) {
                        continue;
                    }

                    $requesterRole = strtolower(trim((string) ($requesterRoles[(int) $ticket->user_id] ?? '')));
                    $creationSource = $creationLog->event_type === 'ticket.created'
                        ? 'client_self_service'
                        : ($requesterRole === 'client' ? 'staff_for_client' : 'staff_for_staff');

                    DB::table('tickets')
                        ->where('id', (int) $ticket->id)
                        ->update([
                            'created_by_user_id' => $creationLog->actor_user_id ? (int) $creationLog->actor_user_id : null,
                            'creation_source' => $creationSource,
                        ]);
                }
            });
    }

    public function down(): void {}
};
