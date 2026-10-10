<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignment_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('weight');
            $table->timestamps();

            $table->unique(['category_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignment_rules');
    }
};
