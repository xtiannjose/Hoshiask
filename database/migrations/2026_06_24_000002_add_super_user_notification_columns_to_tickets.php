<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('super_users_notified_new_at')->nullable()->after('technical_user_notified_sla_at');
            $table->timestamp('super_users_notified_unchecked_at')->nullable()->after('super_users_notified_new_at');
            $table->timestamp('super_users_notified_unassigned_sla_at')->nullable()->after('super_users_notified_unchecked_at');

            $table->index(['status', 'super_users_notified_new_at'], 'tickets_status_super_new_idx');
            $table->index(
                ['status', 'assigned_to', 'super_users_notified_unassigned_sla_at'],
                'tickets_status_unassigned_super_sla_idx'
            );
            $table->index(
                ['status', 'super_users_notified_unchecked_at', 'created_at'],
                'tickets_status_super_unchecked_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_status_super_new_idx');
            $table->dropIndex('tickets_status_unassigned_super_sla_idx');
            $table->dropIndex('tickets_status_super_unchecked_idx');
            $table->dropColumn([
                'super_users_notified_new_at',
                'super_users_notified_unchecked_at',
                'super_users_notified_unassigned_sla_at',
            ]);
        });
    }
};
