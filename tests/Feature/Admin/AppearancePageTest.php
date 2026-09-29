<?php

declare(strict_types=1);

use App\Models\User;
use Cartxis\Core\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTheme(array $attrs = []): Theme
{
    return Theme::create(array_merge([
        'name'        => 'Cartxis Default',
        'slug'        => 'cartxis-default',
        'version'     => '1.0.0',
        'author'      => 'Test',
        'description' => 'Test theme',
        'is_active'   => true,
        'is_default'  => true,
        'source'      => 'bundled',
    ], $attrs));
}

test('appearance page renders for an admin when a theme is active', function () {
    makeTheme();

    $admin = User::factory()->withoutTwoFactor()->create([
        'role'      => 'admin',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.appearance.index'));

    $response->assertStatus(200);
});

test('appearance page renders when the theme has no settings or data file', function () {
    makeTheme(['settings' => null, 'screenshot' => null]);

    $admin = User::factory()->withoutTwoFactor()->create([
        'role'      => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.appearance.index'))
        ->assertStatus(200);
});

test('appearance page renders when the theme has a screenshot configured but the file is missing', function () {
    makeTheme(['screenshot' => 'screenshot.png']);

    $admin = User::factory()->withoutTwoFactor()->create([
        'role'      => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.appearance.index'))
        ->assertStatus(200);
});

test('appearance page renders when there is no active theme', function () {
    makeTheme(['is_active' => false, 'is_default' => false]);

    $admin = User::factory()->withoutTwoFactor()->create([
        'role'      => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.appearance.index'))
        ->assertStatus(302);
});
