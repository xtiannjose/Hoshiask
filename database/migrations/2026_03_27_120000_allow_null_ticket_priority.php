<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('priority')->nullable()->default(null)->change();
            });

            return;
        }

        DB::statement('ALTER TABLE tickets ALTER COLUMN priority DROP DEFAULT');
        DB::statement('ALTER TABLE tickets ALTER COLUMN priority DROP NOT NULL');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('priority')->default('medium')->nullable(false)->change();
            });

            return;
        }

        DB::table('tickets')
            ->whereNull('priority')
            ->update(['priority' => 'medium']);

        DB::statement("ALTER TABLE tickets ALTER COLUMN priority SET DEFAULT 'medium'");
        DB::statement('ALTER TABLE tickets ALTER COLUMN priority SET NOT NULL');
    }
};
