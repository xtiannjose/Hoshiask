<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'user_id']);
            $table->index(['user_id', 'last_seen_at']);
            $table->index(['user_id', 'dismissed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_user_states');
    }
};
