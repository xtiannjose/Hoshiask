<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kept so the recorded migration history still matches the files; it does nothing now. The Hardware and Software
 * categories are added with the other starter categories by Database\Seeders\StarterDataSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nothing: see the note above.
    }

    public function down(): void
    {
        DB::table('categories')
            ->whereIn('name', ['Hardware', 'Software'])
            ->delete();
    }
};
