<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DefaultPasswordResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $staffDefaultPassword = DefaultPasswordResolver::staff();

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '09763621490',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make($staffDefaultPassword),
            'is_active' => true,
            'must_change_password' => true,
            'email_verified_at' => '2026-02-25 06:59:20',
        ]);

        $this->command?->info('Admin user synchronized from current database snapshot.');
    }
}
