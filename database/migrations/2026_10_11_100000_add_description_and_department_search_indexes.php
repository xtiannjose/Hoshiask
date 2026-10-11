<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The ticket search also looks in the description and the department. Without an index on them the search read
 * the whole table (PostgreSQL only; other databases return at once). The subject, ticket number and user indexes
 * were added in 2026_03_04_000019_add_search_indexes_for_ticket_lookup.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (Throwable) {
            // Some managed PostgreSQL providers do not allow extension installs.
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS tickets_description_trgm_idx ON tickets USING gin (LOWER(description) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS tickets_department_trgm_idx ON tickets USING gin (LOWER(department) gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS tickets_description_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS tickets_department_trgm_idx');
    }
};
