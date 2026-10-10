<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'tickets_status_updated_idx');
            $table->index(['assigned_to', 'status', 'updated_at'], 'tickets_assigned_status_updated_idx');
            $table->index(['user_id', 'updated_at'], 'tickets_user_updated_idx');
            $table->index(['priority', 'status'], 'tickets_priority_status_idx');
            $table->index(['created_at', 'status'], 'tickets_created_status_idx');
        });

        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->index(['ticket_id', 'is_internal', 'created_at'], 'ticket_replies_ticket_internal_created_idx');
            $table->index(['ticket_id', 'user_id', 'created_at'], 'ticket_replies_ticket_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_status_updated_idx');
            $table->dropIndex('tickets_assigned_status_updated_idx');
            $table->dropIndex('tickets_user_updated_idx');
            $table->dropIndex('tickets_priority_status_idx');
            $table->dropIndex('tickets_created_status_idx');
        });

        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropIndex('ticket_replies_ticket_internal_created_idx');
            $table->dropIndex('ticket_replies_ticket_user_created_idx');
        });
    }
};
