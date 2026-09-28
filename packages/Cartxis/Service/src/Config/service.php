<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Service Package Configuration
    |--------------------------------------------------------------------------
    |
    | Defaults for the bookable services catalogue. Everything here can be
    | overridden at runtime by the store owner through Services -> Settings,
    | which is stored in the settings table under the "service" group. These
    | values are only the fallback used when no setting row exists yet.
    |
    */

    'name' => 'Service',
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Booking defaults
    |--------------------------------------------------------------------------
    */

    'booking' => [
        // How many hours ahead of "now" the earliest bookable date is.
        'lead_time_hours' => 24,

        // Bookings close this many hours after the chosen slot starts.
        'booking_window_hours' => 168,

        // The day before a booking, no new bookings for it.
        'same_day_allowed' => false,

        // Slots offered when the owner has not configured their own.
        'default_time_slots' => [
            ['label' => 'Morning', 'start' => '08:00', 'end' => '12:00'],
            ['label' => 'Afternoon', 'start' => '12:00', 'end' => '16:00'],
            ['label' => 'Evening', 'start' => '16:00', 'end' => '20:00'],
        ],

        // How many jobs one worker can be given for the same day and slot.
        'capacity_per_slot' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    |
    | "SRV-" prefixes every service order number and booking reference, so a
    | service job is obvious in the Orders list and in reporting.
    |
    */

    'order_number_prefix' => 'SRV-',
    'reference_prefix' => 'SRV-',

    /*
    |--------------------------------------------------------------------------
    | Order integration
    |--------------------------------------------------------------------------
    |
    | A booking writes one order row so it shows up in the existing Orders
    | screens. "source_channel" marks it as a service job. The order is always
    | written with model events suppressed, which is what keeps a service job
    | out of the referral programme.
    |
    */

    'order' => [
        'source_channel' => 'services',
        'payment_method' => 'cash',
        'tax' => 0,
        'shipping_cost' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Catalogue
    |--------------------------------------------------------------------------
    */

    'price_units' => ['per_job', 'per_hour', 'per_day', 'per_sqm'],

    'image_upload_path' => 'services',
    'category_image_upload_path' => 'service-categories',

    'statuses' => ['enabled', 'disabled'],

    'booking_statuses' => [
        'booked',
        'assigned',
        'in_progress',
        'completed',
        'cancelled',
    ],
];
