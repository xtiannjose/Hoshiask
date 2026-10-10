<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('temporary_password');
            $table->timestamp('expires_at');
            $table->timestamp('revealed_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'consumed_at'], 'credential_handoffs_expiry_consumed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_handoffs');
    }
};
