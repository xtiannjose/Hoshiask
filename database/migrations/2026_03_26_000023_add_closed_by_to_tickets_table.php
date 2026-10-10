<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('closed_by')
                ->nullable()
                ->after('closed_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('tickets')
            ->whereNotNull('closed_at')
            ->whereNull('closed_by')
            ->whereNotNull('assigned_to')
            ->update([
                'closed_by' => DB::raw('assigned_to'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
        });
    }
};
