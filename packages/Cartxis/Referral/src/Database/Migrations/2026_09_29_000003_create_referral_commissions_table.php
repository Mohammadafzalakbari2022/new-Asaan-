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
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('referral_id')->constrained('referrals')->cascadeOnDelete();
            $table->foreignId('referrer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->unsignedTinyInteger('level')->default(1);
            $table->decimal('amount', 12, 2)->default(0);

            // Snapshot of the settings at award time, so editing the reward in the
            // dashboard can never retroactively change money already earned.
            $table->decimal('reward_snapshot', 12, 2)->default(0);
            $table->decimal('share_snapshot', 5, 2)->default(100);

            $table->enum('status', ['active', 'reversed'])->default('active');
            $table->timestamp('unlocks_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->timestamps();

            // Idempotency guarantee. Marking the same order paid any number of
            // times still produces exactly one commission per level.
            $table->unique(['order_id', 'referral_id', 'level'], 'referral_commissions_unique_award');

            $table->index('referrer_user_id');
            $table->index('status');
            $table->index('unlocks_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};
