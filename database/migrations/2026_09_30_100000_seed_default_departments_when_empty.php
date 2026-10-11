<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Kept so the recorded migration history still matches the files; it does nothing now.
 *
 * It used to insert the starter departments into an empty table, because a fresh PostgreSQL install loads
 * database/schema/pgsql-schema.sql (tables only), which skips every migration recorded in it, and the departments
 * went missing. The starter departments and categories are now added by Database\Seeders\StarterDataSeeder
 * (`php artisan migrate --seed`), which only fills an empty table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nothing: see the note above.
    }

    public function down(): void
    {
        // Nothing to undo.
    }
};
