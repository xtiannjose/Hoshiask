<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->timestamp('client_notified_reply_at')->nullable()->after('deleted_at');

            $table->index(['ticket_id', 'is_internal', 'client_notified_reply_at'], 'replies_client_notify_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropIndex('replies_client_notify_idx');
            $table->dropColumn('client_notified_reply_at');
        });
    }
};
