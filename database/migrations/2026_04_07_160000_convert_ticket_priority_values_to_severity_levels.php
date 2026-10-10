<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_priority_check');
        }

        DB::table('tickets')->whereIn('priority', ['urgent', 'high'])->update(['priority' => 'severity_1']);
        DB::table('tickets')->where('priority', 'medium')->update(['priority' => 'severity_2']);
        DB::table('tickets')->where('priority', 'low')->update(['priority' => 'severity_3']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
ALTER TABLE tickets
ADD CONSTRAINT tickets_priority_check CHECK (
    (priority IS NULL)
    OR ((priority)::text = ANY ((ARRAY[
        'severity_1'::character varying,
        'severity_2'::character varying,
        'severity_3'::character varying
    ])::text[]))
)
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_priority_check');
        }

        DB::table('tickets')->where('priority', 'severity_1')->update(['priority' => 'high']);
        DB::table('tickets')->where('priority', 'severity_2')->update(['priority' => 'medium']);
        DB::table('tickets')->where('priority', 'severity_3')->update(['priority' => 'low']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement(<<<'SQL'
ALTER TABLE tickets
ADD CONSTRAINT tickets_priority_check CHECK (
    (priority IS NULL)
    OR ((priority)::text = ANY ((ARRAY[
        'low'::character varying,
        'medium'::character varying,
        'high'::character varying,
        'urgent'::character varying
    ])::text[]))
)
SQL);
        }
    }
};
