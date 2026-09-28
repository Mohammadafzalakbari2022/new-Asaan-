<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // What the job covers, and what it does not. Clients ask for both.
            $table->text('includes')->nullable();
            $table->text('excludes')->nullable();

            // Icon (lucide name) and image. icon_only lets the owner publish a
            // service as a plain icon tile with no photo.
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->boolean('icon_only')->default(false);

            // Pricing. A service has one fixed price; price_unit says what the
            // customer is buying one of ("per hour", "per job", ...).
            $table->decimal('price', 12, 4)->default(0);
            $table->enum('price_unit', ['per_job', 'per_hour', 'per_day', 'per_sqm'])->default('per_job');
            $table->string('price_note')->nullable();

            // How long the job takes. duration_minutes drives the display;
            // duration_label is a free-text override such as "half a day".
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('duration_label')->nullable();

            // Which areas the owner covers for this service.
            $table->string('service_area')->nullable();

            $table->enum('status', ['enabled', 'disabled'])->default('enabled');
            $table->boolean('featured')->default(false);

            // Lets the owner publish a service for display only, with booking
            // switched off, without hiding it.
            $table->boolean('booking_enabled')->default(true);

            $table->integer('sort_order')->default(0);

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('status');
            $table->index('service_category_id');
            $table->index(['status', 'featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
