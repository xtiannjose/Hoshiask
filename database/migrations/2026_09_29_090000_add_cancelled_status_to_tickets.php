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
                $table->string('status')->default('open')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        DB::statement(<<<'SQL'
ALTER TABLE tickets
ADD CONSTRAINT tickets_status_check CHECK (
    (status)::text = ANY ((ARRAY[
        'open'::character varying,
        'in_progress'::character varying,
        'pending'::character varying,
        'resolved'::character varying,
        'closed'::character varying,
        'cancelled'::character varying
    ])::text[])
)
SQL);
    }

    public function down(): void
    {
        DB::table('tickets')->where('status', 'cancelled')->update(['status' => 'open']);

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('tickets', function (Blueprint $table) {
                $table->string('status')->default('open')->change();
            });

            return;
        }

        DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
        DB::statement(<<<'SQL'
ALTER TABLE tickets
ADD CONSTRAINT tickets_status_check CHECK (
    (status)::text = ANY ((ARRAY[
        'open'::character varying,
        'in_progress'::character varying,
        'pending'::character varying,
        'resolved'::character varying,
        'closed'::character varying
    ])::text[])
)
SQL);
    }
};
