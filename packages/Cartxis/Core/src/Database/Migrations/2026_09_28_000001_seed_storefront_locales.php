<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the Dari, English and Pashto locales so the shop always ships with
     * all three languages. Dari (Afghanistan) is the store default unless an
     * admin has already chosen a different default. Idempotent: safe to run on
     * any database.
     */
    public function up(): void
    {
        $hasDefault = DB::table('locales')->where('is_default', true)->exists();

        $locales = [
            [
                'code' => 'fa',
                'name' => 'Dari',
                'native_name' => 'دری',
                'direction' => 'rtl',
                'is_default' => ! $hasDefault,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'ps',
                'name' => 'Pashto',
                'native_name' => 'پښتو',
                'direction' => 'rtl',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($locales as $locale) {
            DB::table('locales')->updateOrInsert(
                ['code' => $locale['code']],
                array_merge($locale, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('locales')->whereIn('code', ['fa', 'ps'])->delete();

        if (! DB::table('locales')->where('is_default', true)->exists()) {
            DB::table('locales')->where('code', 'en')->update(['is_default' => true]);
        }
    }
};