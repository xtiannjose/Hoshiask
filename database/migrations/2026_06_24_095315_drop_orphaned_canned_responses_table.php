<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The canned-responses feature was removed without a reversing migration,
     * so the table may still exist on environments that ran the original
     * create migration before the feature was deleted.
     */
    public function up(): void
    {
        Schema::dropIfExists('canned_responses');
    }

    public function down(): void
    {
        // Intentionally irreversible: the feature and its model/controller
        // no longer exist in the codebase.
    }
};
