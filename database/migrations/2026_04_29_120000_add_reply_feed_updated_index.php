<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->index(['ticket_id', 'updated_at'], 'ticket_replies_ticket_updated_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            $table->dropIndex('ticket_replies_ticket_updated_idx');
        });
    }
};
