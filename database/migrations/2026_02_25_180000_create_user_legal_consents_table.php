<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_legal_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('terms_version', 50);
            $table->string('privacy_version', 50);
            $table->string('platform_consent_version', 50);
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'terms_version', 'privacy_version', 'platform_consent_version'],
                'user_legal_consents_version_unique'
            );
            $table->index(['user_id', 'accepted_at'], 'user_legal_consents_user_accepted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_legal_consents');
    }
};
