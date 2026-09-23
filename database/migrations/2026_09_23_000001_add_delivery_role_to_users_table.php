<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the "delivery" role so in-house delivery staff can authenticate
     * against the same users table as admins and customers.
     *
     * The users.role column was originally created with ->enum(). MySQL
     * stores a true enum, while PostgreSQL/SQLite render an inline CHECK
     * constraint. Each driver therefore needs its own ALTER.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','customer','delivery') NOT NULL DEFAULT 'customer'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'customer'");
            DB::statement('ALTER TABLE users ALTER COLUMN role SET NOT NULL');
        } else {
            // SQLite (tests): rebuild the column as a plain varchar so no
            // enum CHECK gets in the way of future role values.
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->default('customer')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','customer') NOT NULL DEFAULT 'customer'");
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'customer'))");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->default('customer')->nullable(false)->change();
            });
        }
    }
};