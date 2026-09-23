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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('scheduled_date')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('customer_phone', 30)->nullable();
            $table->decimal('cod_amount', 12, 2)->nullable();
            $table->decimal('cod_received', 12, 2)->default(0);
            $table->string('recipient_name', 255)->nullable();
            $table->string('delivered_photo_path', 500)->nullable();
            $table->string('failure_reason', 50)->nullable();
            $table->text('failure_note')->nullable();
            $table->decimal('last_latitude', 10, 7)->nullable();
            $table->decimal('last_longitude', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('assigned_to');
            $table->index('scheduled_date');
            $table->index('priority');
        });

        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at');

            $table->index('delivery_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('deliveries');
    }
};