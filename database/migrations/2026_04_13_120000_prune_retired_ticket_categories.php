<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $otherCategory = DB::table('categories')
            ->where('name', 'Other')
            ->first();

        if (! $otherCategory) {
            $otherId = DB::table('categories')->insertGetId([
                'name' => 'Other',
                'description' => 'General inquiries and other support requests',
                'color' => '#6B7280',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $otherId = (int) $otherCategory->id;
        }

        $retiredIds = DB::table('categories')
            ->whereIn('name', [
                'Printer & Scanning',
                'Printer and Scanning',
                'Email & Communication',
                'Mobile Device',
                'Security',
                'Training & Documentation',
            ])
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id !== $otherId)
            ->values();

        if ($retiredIds->isEmpty()) {
            return;
        }

        DB::table('tickets')
            ->whereIn('category_id', $retiredIds)
            ->update([
                'category_id' => $otherId,
                'updated_at' => now(),
            ]);

        DB::table('categories')
            ->whereIn('id', $retiredIds)
            ->delete();
    }

    public function down(): void
    {
        $categories = [
            [
                'name' => 'Email & Communication',
                'description' => 'Email setup, calendar, and communication tools',
                'color' => '#F59E0B',
            ],
            [
                'name' => 'Printer & Scanning',
                'description' => 'Printer setup, printing issues, and scanning problems',
                'color' => '#EC4899',
            ],
            [
                'name' => 'Mobile Device',
                'description' => 'Smartphone, tablet, and mobile app support',
                'color' => '#06B6D4',
            ],
            [
                'name' => 'Training & Documentation',
                'description' => 'Training requests and documentation needs',
                'color' => '#84CC16',
            ],
            [
                'name' => 'Security',
                'description' => 'Security concerns, virus removal, and data protection',
                'color' => '#DC2626',
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'color' => $category['color'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
};
