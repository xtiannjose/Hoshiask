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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
            $table->unique('username', 'users_username_unique');
        });

        $this->backfillUsernames();
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }

    private function backfillUsernames(): void
    {
        $users = DB::table('users')
            ->select(['id', 'name', 'username'])
            ->orderBy('id')
            ->get();

        foreach ($users as $user) {
            $baseUsername = Str::of((string) $user->username)->trim()->lower()->value();
            if ($baseUsername === '') {
                $baseUsername = Str::of((string) $user->name)
                    ->ascii()
                    ->lower()
                    ->replaceMatches('/[^a-z0-9]+/', '.')
                    ->trim('.')
                    ->value();
            }

            if ($baseUsername === '') {
                $baseUsername = 'user';
            }

            $baseUsername = mb_substr($baseUsername, 0, 45);
            $candidate = $baseUsername;
            $suffix = 1;

            while (
                DB::table('users')
                    ->where('username', $candidate)
                    ->where('id', '!=', $user->id)
                    ->exists()
            ) {
                $suffix++;
                $candidate = mb_substr($baseUsername, 0, 40).'.'.$suffix;
            }

            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $candidate]);
        }
    }
};
