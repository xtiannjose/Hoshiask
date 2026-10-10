<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
            $table->index(['user_id', 'ticket_id']);
        });

        DB::table('tickets')
            ->whereNotNull('assigned_to')
            ->select(['id', 'assigned_to', 'assigned_at', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($tickets): void {
                $rows = collect($tickets)
                    ->map(function (object $ticket): array {
                        $assignedAt = $ticket->assigned_at ?? $ticket->updated_at ?? $ticket->created_at ?? now();

                        return [
                            'ticket_id' => (int) $ticket->id,
                            'user_id' => (int) $ticket->assigned_to,
                            'created_at' => $assignedAt,
                            'updated_at' => $assignedAt,
                        ];
                    })
                    ->all();

                if ($rows !== []) {
                    DB::table('ticket_assignments')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};
