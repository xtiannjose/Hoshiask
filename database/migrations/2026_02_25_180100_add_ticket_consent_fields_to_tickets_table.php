<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('consent_accepted_at')->nullable()->after('satisfaction_comment');
            $table->string('consent_version', 50)->nullable()->after('consent_accepted_at');
            $table->string('consent_ip_address', 45)->nullable()->after('consent_version');
            $table->text('consent_user_agent')->nullable()->after('consent_ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'consent_accepted_at',
                'consent_version',
                'consent_ip_address',
                'consent_user_agent',
            ]);
        });
    }
};
