<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildUsersTableForSqlite(emailNullable: true);

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('users')
            ->whereNull('email')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'email' => 'restored-user-'.$user->id.'@system.local',
                    ]);
            });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildUsersTableForSqlite(emailNullable: false);

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }

    private function rebuildUsersTableForSqlite(bool $emailNullable): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        DB::transaction(function () use ($emailNullable): void {
            DB::statement('
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    name VARCHAR NOT NULL,
                    username VARCHAR NULL UNIQUE,
                    email VARCHAR '.($emailNullable ? 'NULL' : 'NOT NULL').' UNIQUE,
                    phone VARCHAR NULL,
                    department VARCHAR NULL,
                    email_verified_at DATETIME NULL,
                    password VARCHAR NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    remember_token VARCHAR NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    role VARCHAR NOT NULL DEFAULT \'client\',
                    is_profile_locked TINYINT(1) NOT NULL DEFAULT 0,
                    client_notes TEXT NULL,
                    must_change_password TINYINT(1) NOT NULL DEFAULT 0
                )
            ');

            DB::statement('
                INSERT INTO users_new (
                    id, name, username, email, phone, department, email_verified_at, password,
                    is_active, remember_token, created_at, updated_at, role, is_profile_locked,
                    client_notes, must_change_password
                )
                SELECT
                    id, name, username, email, phone, department, email_verified_at, password,
                    is_active, remember_token, created_at, updated_at, role, is_profile_locked,
                    client_notes, must_change_password
                FROM users
            ');

            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
