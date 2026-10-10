<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['ticket_type', 'status', 'created_at'], 'tickets_type_status_created_idx');
            $table->index(['category_id', 'status', 'created_at'], 'tickets_category_status_created_idx');
            $table->index(['created_by_user_id', 'created_at'], 'tickets_creator_created_idx');
            $table->index('province', 'tickets_province_idx');
            $table->index('municipality', 'tickets_municipality_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_type_status_created_idx');
            $table->dropIndex('tickets_category_status_created_idx');
            $table->dropIndex('tickets_creator_created_idx');
            $table->dropIndex('tickets_province_idx');
            $table->dropIndex('tickets_municipality_idx');
        });
    }
};
