<?php

declare(strict_types=1);

use App\Models\User;
use Cartxis\Core\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->withoutTwoFactor()->create([
        'role'      => 'admin',
        'is_active' => true,
    ]);
}

function theme(array $attrs = []): Theme
{
    return Theme::create(array_merge([
        'name'       => 'Cartxis Default',
        'slug'       => 'cartxis-default',
        'version'    => '1.0.0',
        'author'     => 'Test',
        'description' => 'Test theme',
        'is_active'  => true,
        'is_default' => true,
        'source'     => 'bundled',
        'settings'   => ['contact' => ['phone' => '+93 700 000 000']],
    ], $attrs));
}

test('GET /admin/appearance renders', function () {
    theme();
    $this->actingAs(admin(), 'admin')->get(route('admin.appearance.index'))->assertStatus(200);
});

test('GET /admin/appearance/themes index renders and discovers themes', function () {
    theme();
    $this->actingAs(admin(), 'admin')
        ->get(route('admin.themes.index'))
        ->assertStatus(200);
});

test('PUT theme settings saves nested settings', function () {
    $t = theme();
    $user = admin();

    $this->actingAs($user, 'admin')
        ->put(route('admin.themes.settings.update', $t->slug), [
            'settings' => [
                'contact' => ['phone' => '+93 700 111 222', 'email' => 'a@b.af'],
                'colors'  => ['primary' => '#e11d48'],
            ],
        ])
        ->assertStatus(302);

    $fresh = $t->fresh();
    expect($fresh->settings['contact']['phone'])->toBe('+93 700 111 222')
        ->and($fresh->settings['colors']['primary'])->toBe('#e11d48');
});

test('GET active-settings redirects to appearance', function () {
    theme();
    $this->actingAs(admin(), 'admin')
        ->get(route('admin.themes.active-settings'))
        ->assertStatus(302);
});

test('appearance page works when theme settings contain deeply nested and list values', function () {
    theme([
        'settings' => [
            'contact' => ['phone' => '1'],
            'social'  => ['links' => ['a', 'b', 'c']],
            'nested'  => ['a' => ['b' => ['c' => 'd']]],
        ],
    ]);

    $this->actingAs(admin(), 'admin')
        ->get(route('admin.appearance.index'))
        ->assertStatus(200);
});

test('appearance page works when the active theme directory is missing on disk', function () {
    theme(['slug' => 'theme-that-does-not-exist-in-fs']);

    $this->actingAs(admin(), 'admin')
        ->get(route('admin.appearance.index'))
        ->assertStatus(302);
});
