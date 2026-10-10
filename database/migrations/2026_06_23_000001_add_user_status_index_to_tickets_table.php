<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Composite index for client-scoped status queries (WHERE user_id = X AND status = Y)
            // The existing tickets_user_updated_idx only covers (user_id, updated_at)
            // and cannot be used efficiently for status-based filtering.
            $table->index(['user_id', 'status'], 'tickets_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_user_status_idx');
        });
    }
};
