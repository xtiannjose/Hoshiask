<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::transaction(function () {
            DB::statement('
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    name VARCHAR NOT NULL,
                    email VARCHAR NOT NULL UNIQUE,
                    phone VARCHAR NULL,
                    department VARCHAR NULL,
                    email_verified_at DATETIME NULL,
                    password VARCHAR NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    remember_token VARCHAR NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    role VARCHAR NOT NULL DEFAULT "client" CHECK (role IN ("client", "admin", "technician", "super_admin"))
                )
            ');

            DB::statement('
                INSERT INTO users_new (
                    id, name, email, phone, department, email_verified_at, password,
                    is_active, remember_token, created_at, updated_at, role
                )
                SELECT
                    id, name, email, phone, department, email_verified_at, password,
                    is_active, remember_token, created_at, updated_at,
                    CASE
                        WHEN role IN ("client", "admin", "technician", "super_admin") THEN role
                        WHEN role = "agent" THEN "admin"
                        ELSE "client"
                    END
                FROM users
            ');

            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        DB::transaction(function () {
            DB::statement('
                CREATE TABLE users_old (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    name VARCHAR NOT NULL,
                    email VARCHAR NOT NULL UNIQUE,
                    phone VARCHAR NULL,
                    department VARCHAR NULL,
                    email_verified_at DATETIME NULL,
                    password VARCHAR NOT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    remember_token VARCHAR NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    role VARCHAR NOT NULL DEFAULT "client" CHECK (role IN ("client", "admin", "super_admin"))
                )
            ');

            DB::statement('
                INSERT INTO users_old (
                    id, name, email, phone, department, email_verified_at, password,
                    is_active, remember_token, created_at, updated_at, role
                )
                SELECT
                    id, name, email, phone, department, email_verified_at, password,
                    is_active, remember_token, created_at, updated_at,
                    CASE
                        WHEN role = "technician" THEN "admin"
                        WHEN role IN ("client", "admin", "super_admin") THEN role
                        ELSE "client"
                    END
                FROM users
            ');

            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_old RENAME TO users');
        });

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
