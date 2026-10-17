<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two columns for the retention clean-up (`php artisan hoshiask:enforce-retention`):
 *
 * - anonymized_at: when a ticket's personal details were removed. A ticket with a date here is skipped from then on.
 * - retention_hold: a legal hold. A held ticket is never cleaned up until the hold is released.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'anonymized_at')) {
                $table->timestamp('anonymized_at')->nullable()->index();
            }

            if (! Schema::hasColumn('tickets', 'retention_hold')) {
                $table->boolean('retention_hold')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'anonymized_at')) {
                $table->dropIndex(['anonymized_at']);
                $table->dropColumn('anonymized_at');
            }

            if (Schema::hasColumn('tickets', 'retention_hold')) {
                $table->dropColumn('retention_hold');
            }
        });
    }
};
