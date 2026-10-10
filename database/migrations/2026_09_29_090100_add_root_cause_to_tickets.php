<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('root_cause_category')->nullable()->after('satisfaction_comment');
            $table->text('root_cause_notes')->nullable()->after('root_cause_category');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['root_cause_category', 'root_cause_notes']);
        });
    }
};
