<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ShadowSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            TicketSeeder::class,
        ]);
    }
}
