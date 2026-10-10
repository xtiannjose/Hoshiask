<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_replies', 'attachments')) {
                $table->dropColumn('attachments');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_replies', 'attachments')) {
                $table->json('attachments')->nullable()->after('is_internal');
            }
        });
    }
};
