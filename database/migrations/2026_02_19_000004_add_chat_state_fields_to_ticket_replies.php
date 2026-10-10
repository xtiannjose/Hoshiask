<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_replies', 'reply_to_id')) {
                $table->foreignId('reply_to_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('ticket_replies')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ticket_replies', 'edited_at')) {
                $table->timestamp('edited_at')->nullable()->after('is_internal');
            }

            if (! Schema::hasColumn('ticket_replies', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('edited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_replies', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_replies', 'reply_to_id')) {
                $table->dropConstrainedForeignId('reply_to_id');
            }

            if (Schema::hasColumn('ticket_replies', 'edited_at')) {
                $table->dropColumn('edited_at');
            }

            if (Schema::hasColumn('ticket_replies', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
};
