<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // How much of this order was paid with referral shop credit.
            // Kept separate from 'discount' so that refunds and revenue figures
            // can tell a coupon apart from the customer's own credit.
            $table->decimal('credit_applied', 12, 2)->default(0)->after('discount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('credit_applied');
        });
    }
};
