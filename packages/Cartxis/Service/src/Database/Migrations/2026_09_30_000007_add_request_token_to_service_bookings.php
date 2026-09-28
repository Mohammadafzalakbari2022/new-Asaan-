<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One key per booking form, so the same form submitted twice is one job.
 *
 * People double-tap submit, and a dropped connection sends it again. Without
 * this the customer gets two visits and the store pays for one job it never
 * got. The uniqueness is enforced by the database rather than by a check in
 * PHP, because two taps can arrive at the same moment and both would pass a
 * "does it exist yet" test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_bookings', function (Blueprint $table): void {
            $table->string('request_token', 64)->nullable()->after('reference');
        });

        // Unique, so the database itself refuses the second identical booking.
        // Blank stays allowed many times over, which is what an old row or a
        // booking made by staff on the phone should be able to be.
        Schema::table('service_bookings', function (Blueprint $table): void {
            $table->unique('request_token', 'service_bookings_request_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('service_bookings', function (Blueprint $table): void {
            $table->dropUnique('service_bookings_request_token_unique');
            $table->dropColumn('request_token');
        });
    }
};
