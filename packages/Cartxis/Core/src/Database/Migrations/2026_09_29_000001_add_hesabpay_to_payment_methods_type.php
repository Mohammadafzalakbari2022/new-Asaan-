<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow "hesabpay" as a payment_methods.type value.
 *
 * The original table declared type as a fixed enum, so the new gateway could
 * not be stored. Every driver needs a different statement, and SQLite is the
 * awkward one: it compiles the enum to a CHECK constraint, and SQLite has no
 * ALTER for that, so the table is rebuilt instead.
 */
return new class extends Migration
{
    /**
     * PostgreSQL cannot use a value added by ALTER TYPE ... ADD VALUE inside
     * the same transaction on older servers, so this migration opts out of the
     * transaction wrapper. It is not an atomicity problem: the statement is
     * idempotent and re-running it is harmless.
     */
    public $withinTransaction = false;

    private const VALUES = [
        'cod', 'bank_transfer', 'stripe', 'paypal', 'razorpay',
        'hesabpay', 'phonepe', 'payumoney', 'other',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('payment_methods') || !Schema::hasColumn('payment_methods', 'type')) {
            return;
        }

        $this->widenTypeColumn();

        $this->seedPaymentMethod();
    }

    /**
     * Add the hesabpay value to the type column for whichever driver is in use.
     */
    private function widenTypeColumn(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Laravel derives the enum type name as {table}_{column}_enum.
            $exists = DB::selectOne("SELECT 1 FROM pg_type WHERE typname = 'payment_methods_type_enum'");

            if ($exists) {
                DB::statement("ALTER TYPE payment_methods_type_enum ADD VALUE IF NOT EXISTS 'hesabpay'");
            }

            return;
        }

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE payment_methods MODIFY COLUMN type ENUM('
                . "'" . implode("','", self::VALUES) . "') DEFAULT 'cod'"
            );

            return;
        }

        if ($driver === 'sqlite') {
            $this->rebuildSqliteTable();
        }
    }

    /**
     * Insert the HesabPay payment method.
     *
     * Seeded from the migration rather than the service provider because the
     * provider boots before the schema exists, so its insert would only ever
     * run on a database that already had the table. Left inactive: switching a
     * payment method on is the store owner's decision.
     */
    private function seedPaymentMethod(): void
    {
        $existing = DB::table('payment_methods')->where('code', 'hesabpay')->exists();

        if ($existing) {
            return;
        }

        DB::table('payment_methods')->insert([
            'code' => 'hesabpay',
            'name' => 'HesabPay',
            'description' => 'Pay with the HesabPay wallet, AfPay card, or an international card.',
            'type' => 'hesabpay',
            'is_active' => false,
            'is_default' => false,
            'sort_order' => 3,
            'configuration' => json_encode([
                'mode' => 'sandbox',
                'test_api_key' => '',
                'api_key' => '',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Rebuild payment_methods on SQLite with the widened CHECK constraint.
     *
     * The documented SQLite procedure: build the replacement, copy the rows
     * across, drop the original, rename. Foreign keys are suspended for the
     * swap so any table referencing payment_methods is not left dangling.
     */
    private function rebuildSqliteTable(): void
    {
        DB::statement('PRAGMA foreign_keys = off');

        try {
            Schema::dropIfExists('payment_methods_hesabpay_tmp');

            Schema::create('payment_methods_hesabpay_tmp', function (Blueprint $table) {
                $table->id();

                $table->string('code', 100)->unique();
                $table->string('name', 255);
                $table->text('description')->nullable();

                $table->enum('type', self::VALUES)->default('cod');
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);

                $table->integer('sort_order')->default(0);

                $table->text('instructions')->nullable();
                $table->json('configuration')->nullable();

                $table->timestamps();

                $table->index('code');
                $table->index('type');
                $table->index('is_active');
                $table->index('sort_order');
            });

            $columns = [
                'id', 'code', 'name', 'description', 'type', 'is_active', 'is_default',
                'sort_order', 'instructions', 'configuration', 'created_at', 'updated_at',
            ];

            $list = implode(', ', $columns);

            DB::statement("INSERT INTO payment_methods_hesabpay_tmp ({$list}) SELECT {$list} FROM payment_methods");

            Schema::drop('payment_methods');

            DB::statement('ALTER TABLE payment_methods_hesabpay_tmp RENAME TO payment_methods');
        } finally {
            DB::statement('PRAGMA foreign_keys = on');
        }
    }

    public function down(): void
    {
        // PostgreSQL cannot drop an enum value without recreating the type, and
        // recreating it would rewrite the column. Removing a value that is
        // already in use is destructive, so this is intentionally a no-op.
    }
};
