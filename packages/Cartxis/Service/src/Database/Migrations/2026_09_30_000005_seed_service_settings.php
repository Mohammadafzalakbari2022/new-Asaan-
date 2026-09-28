<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Default settings for the services catalogue.
     *
     * These match the baked-in defaults in Cartxis\Service\Services\ServiceSettings,
     * so the app behaves correctly even if this migration never runs.
     */
    protected array $defaults = [
        'service.booking_enabled' => '1',
        'service.lead_time_hours' => '24',
        'service.booking_window_hours' => '168',
        'service.same_day_allowed' => '0',
        'service.time_slots' => '[{"label":"Morning","start":"08:00","end":"12:00"},{"label":"Afternoon","start":"12:00","end":"16:00"},{"label":"Evening","start":"16:00","end":"20:00"}]',
        'service.capacity_per_slot' => '10',
        'service.coverage_note' => '',
        'service.contact_phone' => '',
        'service.contact_whatsapp' => '',
        'service.require_login_to_book' => '0',
        'service.auto_assign' => '0',
        'service.reference_prefix' => 'SRV-',
    ];

    protected array $types = [
        'service.booking_enabled' => 'boolean',
        'service.lead_time_hours' => 'integer',
        'service.booking_window_hours' => 'integer',
        'service.same_day_allowed' => 'boolean',
        'service.time_slots' => 'json',
        'service.capacity_per_slot' => 'integer',
        'service.coverage_note' => 'string',
        'service.contact_phone' => 'string',
        'service.contact_whatsapp' => 'string',
        'service.require_login_to_book' => 'boolean',
        'service.auto_assign' => 'boolean',
        'service.reference_prefix' => 'string',
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->defaults as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'service',
                    'value' => $value,
                    'type' => $this->types[$key],
                    'is_public' => false,
                    'extension_code' => 'cartxis_service',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'like', 'service.%')
            ->delete();
    }
};
