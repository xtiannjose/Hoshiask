<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('categories')->where('name', 'Hardware')->exists()) {
            DB::table('categories')->insert([
                'name' => 'Hardware',
                'description' => 'Problems with computer hardware, peripherals, and equipment',
                'color' => '#EF4444',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('categories')->where('name', 'Software')->exists()) {
            DB::table('categories')->insert([
                'name' => 'Software',
                'description' => 'Software installation, updates, and application problems',
                'color' => '#3B82F6',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('categories')
            ->whereIn('name', ['Hardware', 'Software'])
            ->delete();
    }
};
