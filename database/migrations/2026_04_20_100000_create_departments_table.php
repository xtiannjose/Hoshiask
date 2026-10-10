<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

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

        foreach ($departments as &$department) {
            $department['created_at'] = $timestamp;
            $department['updated_at'] = $timestamp;
        }

        DB::table('departments')->insert($departments);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
