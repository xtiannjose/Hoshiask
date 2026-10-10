<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Bookkeeping stamps so each client-facing email is sent at most once per
            // ticket lifecycle (they are cleared again when a ticket is reopened,
            // except "received").
            $table->timestamp('client_notified_received_at')->nullable();
            $table->timestamp('client_notified_resolved_at')->nullable();
            $table->timestamp('client_notified_auto_close_warning_at')->nullable();
            $table->timestamp('client_notified_closed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'client_notified_received_at',
                'client_notified_resolved_at',
                'client_notified_auto_close_warning_at',
                'client_notified_closed_at',
            ]);
        });
    }
};
