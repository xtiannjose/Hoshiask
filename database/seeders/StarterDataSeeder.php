<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The departments and ticket categories a new installation starts with.
 *
 * They used to be inserted by migrations. A migration that holds data cannot be folded into
 * database/schema/pgsql-schema.sql (a schema dump holds tables only and records every migration as run, so a fresh
 * PostgreSQL database came up with no departments), so the data lives here and `migrate --seed` adds it.
 *
 * Each table is filled only while it is empty, so running this again never brings back a department or a category
 * that an administrator deleted or renamed, and never changes an existing installation.
 */
class StarterDataSeeder extends Seeder
{
    /** @var list<array{name: string, slug: string, logo_path: string}> */
    public const DEPARTMENTS = [
        ['name' => 'Hoshishi', 'slug' => 'hoshishi', 'logo_path' => 'images/Hoshishi Logo.png'],
        ['name' => 'Alder', 'slug' => 'alder', 'logo_path' => 'images/Alder Logo.png'],
        ['name' => 'Birch', 'slug' => 'birch', 'logo_path' => 'images/Birch Logo.png'],
        ['name' => 'Cedar', 'slug' => 'cedar', 'logo_path' => 'images/Cedar Logo.png'],
        ['name' => 'Dune', 'slug' => 'dune', 'logo_path' => 'images/Dune Logo.png'],
        ['name' => 'Ember', 'slug' => 'ember', 'logo_path' => 'images/Ember Logo.png'],
        ['name' => 'Orb', 'slug' => 'orb', 'logo_path' => 'images/Orb Logo.png'],
        ['name' => 'Orbit', 'slug' => 'orbit', 'logo_path' => 'images/Orbit Logo.png'],
        ['name' => 'Fjord', 'slug' => 'fjord', 'logo_path' => 'images/Fjord Logo.png'],
        ['name' => 'Glade', 'slug' => 'glade', 'logo_path' => 'images/Glade Logo.png'],
        ['name' => 'Harbor Town', 'slug' => 'harbor-town', 'logo_path' => 'images/Harbor Town Logo.png'],
        ['name' => 'Juniper', 'slug' => 'juniper', 'logo_path' => 'images/Juniper Logo.png'],
        ['name' => 'Others', 'slug' => 'others', 'logo_path' => 'images/Others Logo.png'],
    ];

    public function run(): void
    {
        if (! DB::table('departments')->exists()) {
            $timestamp = now();

            DB::table('departments')->insert(array_map(
                static fn (array $department): array => $department + ['created_at' => $timestamp, 'updated_at' => $timestamp],
                self::DEPARTMENTS
            ));
        }

        if (! Category::query()->exists()) {
            $this->call(CategorySeeder::class);
        }
    }
}
