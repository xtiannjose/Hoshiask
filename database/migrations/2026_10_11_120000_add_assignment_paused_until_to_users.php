<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // While in the future, the assignment rules skip this person (leave, a long absence). Staff can still
            // assign them by hand. Null means available.
            $table->timestamp('assignment_paused_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('assignment_paused_until');
        });
    }
};
