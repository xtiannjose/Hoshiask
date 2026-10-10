<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteUsersTableForCurrentRoles();

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client', 'admin', 'agent', 'super_admin') NOT NULL DEFAULT 'client'");
            DB::table('users')->where('role', 'agent')->update(['role' => 'admin']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client', 'admin', 'super_admin') NOT NULL DEFAULT 'client'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::table('users')->where('role', 'agent')->update(['role' => 'admin']);
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['client'::text, 'admin'::text, 'super_admin'::text]))");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqliteUsersTableForLegacyRoles();

            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client', 'admin', 'agent', 'super_admin') NOT NULL DEFAULT 'client'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role::text = ANY (ARRAY['client'::text, 'admin'::text, 'agent'::text, 'super_admin'::text]))");
        }
    }

    private function rebuildSqliteUsersTableForCurrentRoles(): void
    {
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
                    role VARCHAR NOT NULL DEFAULT "client" CHECK (role IN ("client", "admin", "super_admin"))
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
                        WHEN role IN ("client", "admin", "super_admin") THEN role
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

    private function rebuildSqliteUsersTableForLegacyRoles(): void
    {
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
                    role VARCHAR NOT NULL DEFAULT "client" CHECK (role IN ("client", "admin", "agent", "super_admin"))
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
                        WHEN role IN ("client", "admin", "agent", "super_admin") THEN role
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
