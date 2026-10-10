<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Hardware',
                'description' => 'Problems with computer hardware, peripherals, and equipment',
                'color' => '#EF4444',
                'is_active' => true,
            ],
            [
                'name' => 'Software',
                'description' => 'Software installation, updates, and application problems',
                'color' => '#3B82F6',
                'is_active' => true,
            ],
            [
                'name' => 'Network & Connectivity',
                'description' => 'Internet, WiFi, VPN and network related issues',
                'color' => '#10B981',
                'is_active' => true,
            ],
            [
                'name' => 'Account & Access',
                'description' => 'Login issues, password resets, and account permissions',
                'color' => '#8B5CF6',
                'is_active' => true,
            ],
            [
                'name' => 'Other',
                'description' => 'General inquiries and other support requests',
                'color' => '#6B7280',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                $category
            );
        }
    }
}
