<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status');
            $table->index('priority');
            $table->index('created_at');
            $table->index(['status', 'priority', 'created_at']);
        });

        if (Schema::hasColumn('ticket_replies', 'attachments')) {
            Schema::table('ticket_replies', function (Blueprint $table) {
                $table->dropColumn('attachments');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ticket_replies', 'attachments')) {
            Schema::table('ticket_replies', function (Blueprint $table) {
                $table->json('attachments')->nullable()->after('is_internal');
            });
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_status_index');
            $table->dropIndex('tickets_priority_index');
            $table->dropIndex('tickets_created_at_index');
            $table->dropIndex('tickets_status_priority_created_at_index');
        });
    }
};
