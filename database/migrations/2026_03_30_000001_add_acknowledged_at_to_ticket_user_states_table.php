<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_user_states', function (Blueprint $table): void {
            $table->timestamp('acknowledged_at')->nullable()->after('last_seen_at');
            $table->index(['user_id', 'acknowledged_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_user_states', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'acknowledged_at']);
            $table->dropColumn('acknowledged_at');
        });
    }
};
