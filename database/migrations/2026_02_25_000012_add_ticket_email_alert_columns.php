<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->timestamp('super_users_notified_new_at')->nullable()->after('satisfaction_comment');
            $table->timestamp('technical_user_notified_assignment_at')->nullable()->after('super_users_notified_new_at');
            $table->timestamp('super_users_notified_unchecked_at')->nullable()->after('technical_user_notified_assignment_at');
            $table->timestamp('super_users_notified_unassigned_sla_at')->nullable()->after('super_users_notified_unchecked_at');
            $table->timestamp('technical_user_notified_sla_at')->nullable()->after('super_users_notified_unassigned_sla_at');

            $table->index(['status', 'assigned_to']);
            $table->index(['created_at', 'super_users_notified_unchecked_at']);
            $table->index(['assigned_at', 'technical_user_notified_sla_at']);
        });

        DB::table('tickets')
            ->whereNotNull('assigned_to')
            ->whereNull('assigned_at')
            ->update([
                'assigned_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status', 'assigned_to']);
            $table->dropIndex(['created_at', 'super_users_notified_unchecked_at']);
            $table->dropIndex(['assigned_at', 'technical_user_notified_sla_at']);

            $table->dropColumn([
                'assigned_at',
                'super_users_notified_new_at',
                'technical_user_notified_assignment_at',
                'super_users_notified_unchecked_at',
                'super_users_notified_unassigned_sla_at',
                'technical_user_notified_sla_at',
            ]);
        });
    }
};
