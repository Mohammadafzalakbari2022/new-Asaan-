<?php

namespace Tests\Feature\Locale;

use Cartxis\Core\Models\Locale;

it('registers Dari, English and Pashto locales with correct settings', function () {
    $dari = Locale::where('code', 'fa')->first();
    $english = Locale::where('code', 'en')->first();
    $pashto = Locale::where('code', 'ps')->first();

    expect($dari)->not->toBeNull();
    expect($english)->not->toBeNull();
    expect($pashto)->not->toBeNull();

    expect($english->is_active)->toBeTrue();
    expect($pashto->is_active)->toBeTrue();
    expect($pashto->name)->toBe('Pashto');
    expect($pashto->direction)->toBe('rtl');
    expect($dari->name)->toBe('Dari');
    expect($dari->native_name)->toBe('دری');
    expect($dari->direction)->toBe('rtl');

    $default = Locale::where('is_default', true)->first();
    expect($default)->not->toBeNull();
    expect($default->code)->toBe('fa');
});

it('ships matching English and Pashto translation files', function () {
    $en = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);
    $ps = json_decode(file_get_contents(lang_path('ps.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($en)->not->toBeEmpty();
    expect(array_keys($en))->toBe(array_keys($ps));
});

it("keeps every language's area dictionaries key-aligned", function () {
    foreach (['storefront.json', 'admin.json'] as $area) {
        $en = json_decode(file_get_contents(lang_path("en/{$area}")), true, 512, JSON_THROW_ON_ERROR);
        $fa = json_decode(file_get_contents(lang_path("fa/{$area}")), true, 512, JSON_THROW_ON_ERROR);
        $ps = json_decode(file_get_contents(lang_path("ps/{$area}")), true, 512, JSON_THROW_ON_ERROR);

        expect(array_keys($en))->toBe(array_keys($fa));
        expect(array_keys($en))->toBe(array_keys($ps));
    }
});

it('renders Pashto strings through the translator', function () {
    app()->setLocale('ps');

    expect(__('Shop'))->toBe('پلورنځی');
    expect(__('Add to Cart'))->toBe('ګاډۍ ته یې اضافه کړئ');
    expect(__('English'))->toBe('انګلیسي');
    expect(__('Pashto'))->toBe('پښتو');

    app()->setLocale('en');
});