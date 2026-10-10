<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\DefaultPasswordResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ShadowSeeder extends Seeder
{
    public function run(): void
    {
        $shadowPassword = DefaultPasswordResolver::shadow();

        User::updateOrCreate(['email' => 'shadow@example.com'], [
            'name' => 'Shadow',
            'email' => 'shadow@example.com',
            'phone' => '+1234567899',
            'department' => 'Administration',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make($shadowPassword),
            'is_active' => true,
            'must_change_password' => false,
            'email_verified_at' => '2026-02-25 06:59:18',
        ]);

        $this->command?->info('Shadow user synchronized from current database snapshot.');
    }
}
