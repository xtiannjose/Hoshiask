<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canned_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamps();

            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canned_responses');
    }
};
