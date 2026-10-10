<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The staff list and dashboard heartbeat reads MAX(tickets.updated_at) every 10 seconds, and the reply
    // email digest scans ticket_replies by created_at every 5 minutes. Neither had an index to lead with.
    // ponytail: plain indexes so it runs on SQLite, PostgreSQL and MySQL; a PostgreSQL partial index on
    // ticket_replies(created_at) where client_notified_reply_at is null would be smaller if the table grows large.
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('updated_at', 'tickets_updated_at_idx');
        });

        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->index('created_at', 'ticket_replies_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_updated_at_idx');
        });

        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropIndex('ticket_replies_created_at_idx');
        });
    }
};
