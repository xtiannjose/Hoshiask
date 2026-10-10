<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('escalated_to')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->foreignId('escalated_by')->nullable()->after('escalated_to')->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable()->after('escalated_by');
            $table->text('escalation_reason')->nullable()->after('escalated_at');
            $table->timestamp('escalation_resolved_at')->nullable()->after('escalation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('escalated_to');
            $table->dropConstrainedForeignId('escalated_by');
            $table->dropColumn(['escalated_at', 'escalation_reason', 'escalation_resolved_at']);
        });
    }
};
