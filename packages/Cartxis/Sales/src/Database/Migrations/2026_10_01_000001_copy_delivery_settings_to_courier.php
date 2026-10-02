<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move the external courier settings off the overloaded "delivery" name.
 *
 * There were two unrelated features both called "delivery": the built-in
 * in-house delivery system (always on, no credentials) and the optional
 * third-party courier API. They shared the "shipping.delivery.*" settings
 * keys, which is how the courier's "enabled" flag ended up gating the in-house
 * one. The courier now owns "shipping.courier.*".
 *
 * This copies every existing value across so an installation that had already
 * configured the courier keeps its configuration, and removes the old keys so
 * the two can never diverge again.
 */
return new class extends Migration
{
    private const OLD_PREFIX = 'shipping.delivery.';

    private const NEW_PREFIX = 'shipping.courier.';

    public function up(): void
    {
        $this->copy(self::OLD_PREFIX, self::NEW_PREFIX);
    }

    public function down(): void
    {
        $this->copy(self::NEW_PREFIX, self::OLD_PREFIX);
    }

    /**
     * Copy every setting under one prefix to the other, then drop the source.
     * An existing destination key is never overwritten.
     */
    private function copy(string $fromPrefix, string $toPrefix): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $rows = DB::table('settings')
            ->where('key', 'like', $fromPrefix . '%')
            ->get();

        foreach ($rows as $row) {
            $newKey = $toPrefix . substr($row->key, strlen($fromPrefix));

            if (DB::table('settings')->where('key', $newKey)->exists()) {
                DB::table('settings')->where('key', $row->key)->delete();

                continue;
            }

            DB::table('settings')->insert([
                'key' => $newKey,
                'value' => $row->value,
                'type' => $row->type,
                'group' => $row->group,
                'is_public' => $row->is_public,
                'extension_code' => $row->extension_code,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);

            DB::table('settings')->where('key', $row->key)->delete();
        }
    }
};
