<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_bookings', function (Blueprint $table) {
            $table->id();

            // Customer-facing reference, e.g. SRV-7K3M9Q.
            $table->string('reference', 32)->unique();

            // The money record. A booking always has one, written in the same
            // transaction, so the job shows up in the existing Orders screens.
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();

            // The service may be deleted later; the snapshots below survive it.
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', [
                'booked',
                'assigned',
                'in_progress',
                'completed',
                'cancelled',
            ])->default('booked');

            // The worker doing the job. This is one of the existing delivery
            // staff (users.role = 'delivery'), never a new user type.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // When the customer asked for the job.
            $table->date('scheduled_date')->nullable();
            $table->string('scheduled_slot', 60)->nullable();

            // What was promised at booking time, so a later price change or a
            // deleted service cannot rewrite history.
            $table->string('service_name');
            $table->decimal('price_snapshot', 12, 2)->default(0);
            $table->string('price_unit', 20)->default('per_job');

            // What the worker actually took on the day. May differ from the
            // promised price (extra hours, extra parts); both are kept.
            $table->decimal('amount_collected', 12, 2)->nullable();
            $table->string('payment_method', 30)->default('cash');

            // Customer details, copied in so the booking stands on its own even
            // for a guest with no account.
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();
            $table->text('address');
            $table->string('city')->nullable();

            // What the customer typed.
            $table->text('notes')->nullable();

            // Never shown to the customer.
            $table->text('internal_notes')->nullable();

            $table->string('cancel_reason')->nullable();
            $table->string('source', 20)->default('web');

            $table->timestamps();

            $table->index('status');
            $table->index('assigned_to');
            $table->index('scheduled_date');
            $table->index(['status', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_bookings');
    }
};
