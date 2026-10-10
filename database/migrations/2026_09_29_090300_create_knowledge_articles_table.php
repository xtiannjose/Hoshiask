<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('category')->default('faq');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('title');
            $table->index(['is_published', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
