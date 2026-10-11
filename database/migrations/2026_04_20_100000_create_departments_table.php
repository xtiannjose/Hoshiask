<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        // The starter departments are inserted by Database\Seeders\StarterDataSeeder, not here: a migration that
        // holds data cannot be folded into the PostgreSQL schema file.
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
