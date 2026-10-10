<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        $this->createPostgresSearchIndexes();
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        $this->dropPostgresSearchIndexes();
    }

    private function createPostgresSearchIndexes(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (Throwable) {
            // Some managed Postgres providers do not allow extension installs.
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS tickets_subject_trgm_idx ON tickets USING gin (LOWER(subject) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS tickets_ticket_number_trgm_idx ON tickets USING gin (LOWER(ticket_number) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_name_trgm_idx ON users USING gin (LOWER(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_email_trgm_idx ON users USING gin (LOWER(email) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS categories_name_trgm_idx ON categories USING gin (LOWER(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS tickets_province_lower_idx ON tickets (LOWER(province))');
        DB::statement('CREATE INDEX IF NOT EXISTS tickets_municipality_lower_idx ON tickets (LOWER(municipality))');
    }

    private function dropPostgresSearchIndexes(): void
    {
        DB::statement('DROP INDEX IF EXISTS tickets_subject_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS tickets_ticket_number_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS users_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS users_email_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS categories_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS tickets_province_lower_idx');
        DB::statement('DROP INDEX IF EXISTS tickets_municipality_lower_idx');
    }
};
