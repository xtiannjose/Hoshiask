<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The starter departments were inserted by 2026_04_20_100000_create_departments_table. On PostgreSQL
 * Laravel loads database/schema/pgsql-schema.sql (tables only) and skips every migration recorded in
 * it, so a fresh PostgreSQL install ended up with an empty departments table and every user form
 * rejected the department ("The selected department is invalid").
 *
 * This runs after the schema dump on every driver. It only seeds an EMPTY table, so it never re-adds
 * a department that an administrator deliberately deleted or renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments') || DB::table('departments')->exists()) {
            return;
        }

        $timestamp = now();
        $departments = [
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

        DB::table('departments')->insert(array_map(
            static fn (array $department): array => $department + ['created_at' => $timestamp, 'updated_at' => $timestamp],
            $departments
        ));
    }

    public function down(): void
    {
        // Data seed only: nothing to undo (the table itself belongs to the original migration).
    }
};
