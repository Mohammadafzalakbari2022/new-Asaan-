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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();

            // The unique constraint on referred_user_id is what makes the reward
            // fire exactly once per person, no matter how many orders they place.
            $table->foreignId('referred_user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('referrer_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('referral_code_id')
                ->nullable()
                ->constrained('referral_codes')
                ->nullOnDelete();

            $table->unsignedTinyInteger('level')->default(1);
            $table->enum('status', ['active', 'voided'])->default('active');
            $table->timestamp('rewarded_at')->nullable();

            $table->text('voided_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();

            $table->timestamps();

            $table->index('referrer_user_id');
            $table->index(['status', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
