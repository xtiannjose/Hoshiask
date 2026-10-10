<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Named indexes added in 2026_04_22_000101
            $table->dropIndex('tickets_status_unchecked_created_idx');
            $table->dropIndex('tickets_status_unassigned_sla_idx');
            // Auto-named index added in 2026_02_25_000012
            $table->dropIndex(['created_at', 'super_users_notified_unchecked_at']);
            $table->dropColumn([
                'super_users_notified_new_at',
                'super_users_notified_unchecked_at',
                'super_users_notified_unassigned_sla_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('super_users_notified_new_at')->nullable();
            $table->timestamp('super_users_notified_unchecked_at')->nullable();
            $table->timestamp('super_users_notified_unassigned_sla_at')->nullable();
            $table->index(['created_at', 'super_users_notified_unchecked_at']);
            $table->index(
                ['status', 'super_users_notified_unchecked_at', 'created_at'],
                'tickets_status_unchecked_created_idx'
            );
            $table->index(
                ['status', 'super_users_notified_unassigned_sla_at', 'assigned_to'],
                'tickets_status_unassigned_sla_idx'
            );
        });
    }
};
