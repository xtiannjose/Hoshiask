<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(
                ['status', 'super_users_notified_unchecked_at', 'created_at'],
                'tickets_status_unchecked_created_idx'
            );
            $table->index(
                ['status', 'super_users_notified_unassigned_sla_at', 'assigned_to'],
                'tickets_status_unassigned_sla_idx'
            );
            $table->index(
                ['status', 'technical_user_notified_sla_at', 'assigned_at'],
                'tickets_status_support_sla_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_status_unchecked_created_idx');
            $table->dropIndex('tickets_status_unassigned_sla_idx');
            $table->dropIndex('tickets_status_support_sla_idx');
        });
    }
};
