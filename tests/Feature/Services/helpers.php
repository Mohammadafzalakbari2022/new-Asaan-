<?php

/*
|--------------------------------------------------------------------------
| Service feature test helpers
|--------------------------------------------------------------------------
|
| The services tests need the same handful of fixtures in nearly every file:
| an admin, a worker, a service and a booked job. These live in their own file,
| required by the test that needs them, rather than in tests/Pest.php, which
| another piece of work is already editing.
|
*/

use App\Models\User;
use Cartxis\Service\Models\Service;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Models\ServiceCategory;
use Cartxis\Service\Services\ServiceBookingService;
use Cartxis\Service\Services\ServiceSettings;
use Carbon\CarbonImmutable;

if (! function_exists('serviceAdmin')) {
    function serviceAdmin(): User
    {
        return User::factory()->withoutTwoFactor()->create([
            'role' => 'admin',
            'is_active' => true,
            'name' => 'Store Owner',
        ]);
    }
}

if (! function_exists('serviceWorker')) {
    /**
     * A worker is an existing delivery staff account. There is no new user type,
     * and these tests are what prove it.
     */
    function serviceWorker(string $name = 'Test Worker'): User
    {
        return User::factory()->withoutTwoFactor()->create([
            'role' => 'delivery',
            'is_active' => true,
            'name' => $name,
        ]);
    }
}

if (! function_exists('makeServiceCategory')) {
    function makeServiceCategory(array $attributes = []): ServiceCategory
    {
        return ServiceCategory::create(array_merge([
            'name' => 'Cleaning',
            'status' => 'enabled',
        ], $attributes));
    }
}

if (! function_exists('makeService')) {
    function makeService(array $attributes = []): Service
    {
        $category = $attributes['service_category_id'] ?? makeServiceCategory()->id;

        return Service::create(array_merge([
            'service_category_id' => $category,
            'name' => 'Deep Cleaning',
            'price' => 3000,
            'price_unit' => 'per_job',
            'status' => 'enabled',
            'booking_enabled' => true,
        ], $attributes));
    }
}

if (! function_exists('bookableDate')) {
    /**
     * A date far enough ahead that it always clears the lead time, so a test
     * about something else is never tripped up by the booking window.
     */
    function bookableDate(int $daysAhead = 3): string
    {
        return CarbonImmutable::today()->addDays($daysAhead)->format('Y-m-d');
    }
}

if (! function_exists('bookServiceJob')) {
    /**
     * A real booking, made the way the website makes one, so the order row and
     * the timeline behind it are genuinely exercised.
     */
    function bookServiceJob(
        ?Service $service = null,
        array $overrides = [],
        ?User $customer = null,
    ): ServiceBooking {
        $service ??= makeService();

        return app(ServiceBookingService::class)->book(
            $service,
            array_merge([
                'scheduled_date' => bookableDate(),
                'scheduled_slot' => 'Morning',
                'customer_name' => 'Test Customer',
                'customer_phone' => '0700000000',
                'customer_email' => 'customer@example.test',
                'address' => 'House 12, Street 4, Kabul',
                'notes' => 'Please ring the bell.',
            ], $overrides),
            $customer,
        );
    }
}

if (! function_exists('configureServices')) {
    /**
     * Turn the feature on with a known configuration for the test.
     */
    function configureServices(array $overrides = []): void
    {
        app(ServiceSettings::class)->save(array_merge([
            'booking_enabled' => true,
            'lead_time_hours' => 24,
            'booking_window_hours' => 168,
            'same_day_allowed' => false,
            'time_slots' => [
                ['label' => 'Morning', 'start' => '08:00', 'end' => '12:00'],
                ['label' => 'Afternoon', 'start' => '12:00', 'end' => '16:00'],
            ],
            'capacity_per_slot' => 10,
            'coverage_note' => 'We cover Kabul, Herat and Jalalabad.',
            'contact_phone' => '0700000000',
            'contact_whatsapp' => '',
            'require_login_to_book' => false,
            'auto_assign' => false,
            'reference_prefix' => 'SRV-',
        ], $overrides));
    }
}
