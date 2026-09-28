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
        Schema::create('referral_ledger', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', [
                'earned',
                'spent',
                'reversed',
                'admin_credit',
                'admin_debit',
            ]);

            // Signed: negative for spends and reversals.
            $table->decimal('amount', 12, 2)->default(0);

            // For 'earned' rows this is the commission's unlock date, so locked
            // credit is simply credit whose available_from is still in the future.
            $table->timestamp('available_from')->nullable();

            $table->decimal('balance_after', 12, 2)->default(0);

            $table->foreignId('commission_id')->nullable()->constrained('referral_commissions')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('reason', 500)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'available_from'], 'referral_ledger_user_available_idx');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_ledger');
    }
};
