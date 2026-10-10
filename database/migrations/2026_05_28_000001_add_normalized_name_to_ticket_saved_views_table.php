<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_saved_views') || Schema::hasColumn('ticket_saved_views', 'name_normalized')) {
            return;
        }

        Schema::table('ticket_saved_views', function (Blueprint $table) {
            $table->string('name_normalized', 80)->nullable()->after('name');
        });

        $seenNames = [];

        DB::table('ticket_saved_views')
            ->select(['id', 'user_id', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($savedViews) use (&$seenNames): void {
                foreach ($savedViews as $savedView) {
                    $baseName = Str::limit(Str::lower(Str::squish((string) $savedView->name)), 80, '');
                    $baseName = $baseName !== '' ? $baseName : 'saved-view-'.$savedView->id;
                    $seenKey = $savedView->user_id.'|'.$baseName;
                    $normalizedName = isset($seenNames[$seenKey])
                        ? Str::limit($baseName.'-'.$savedView->id, 80, '')
                        : $baseName;
                    $seenNames[$seenKey] = true;

                    DB::table('ticket_saved_views')
                        ->where('id', $savedView->id)
                        ->update(['name_normalized' => $normalizedName]);
                }
            });

        Schema::table('ticket_saved_views', function (Blueprint $table) {
            $table->unique(['user_id', 'name_normalized'], 'ticket_saved_views_user_normalized_name_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket_saved_views') || ! Schema::hasColumn('ticket_saved_views', 'name_normalized')) {
            return;
        }

        Schema::table('ticket_saved_views', function (Blueprint $table) {
            $table->dropUnique('ticket_saved_views_user_normalized_name_unique');
            $table->dropColumn('name_normalized');
        });
    }
};
