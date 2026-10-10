<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite role constraint is rebuilt in a later dedicated migration.
            return;
        }

        if ($driver === 'pgsql') {
            // PostgreSQL: drop the old check constraint and add a new one
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("
                UPDATE users
                SET role = CASE
                    WHEN role IN ('client', 'admin', 'agent', 'super_admin') THEN role
                    WHEN role IN ('shadow', 'developer') THEN 'super_admin'
                    WHEN role IN ('super_user', 'technical', 'technician') THEN 'admin'
                    ELSE 'client'
                END
            ");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('client', 'admin', 'agent', 'super_admin'))");
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client', 'admin', 'agent', 'super_admin') NOT NULL DEFAULT 'client'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("
                UPDATE users
                SET role = CASE
                    WHEN role IN ('client', 'admin', 'agent') THEN role
                    WHEN role = 'super_admin' THEN 'admin'
                    ELSE 'client'
                END
            ");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('client', 'admin', 'agent'))");
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('client', 'admin', 'agent') NOT NULL DEFAULT 'client'");
        }
    }
};
