<?php

use Cartxis\Core\Services\SettingService;
use Cartxis\Marketing\Models\ContactMessage;

/*
|--------------------------------------------------------------------------
| The storefront contact details and the contact form
|--------------------------------------------------------------------------
|
| The app and the website both read the business WhatsApp number and the app
| store links from one public endpoint, and the website's contact form writes
| into the dashboard inbox. These tests hold that contract: public, validated,
| and saved.
|
*/

it('gives the contact details to anybody, without signing in', function () {
    $settings = app(SettingService::class);
    $settings->set('store_whatsapp', '+93 700 000 000', 'string', 'store');
    $settings->set('store_phone', '+93 700 111 111', 'string', 'store');
    $settings->set('store_email', 'help@asaan.test', 'string', 'store');
    $settings->set('play_store_url', 'https://play.google.com/store/apps/details?id=com.cartxis.app', 'string', 'store');
    $settings->set('app_store_url', '', 'string', 'store');

    $response = $this->getJson('/api/v1/storefront/contact')->assertOk();

    expect($response->json('success'))->toBeTrue()
        ->and($response->json('data.whatsapp'))->toBe('+93 700 000 000')
        ->and($response->json('data.phone'))->toBe('+93 700 111 111')
        ->and($response->json('data.email'))->toBe('help@asaan.test')
        ->and($response->json('data.play_store_url'))->toContain('play.google.com')
        ->and($response->json('data.app_store_url'))->toBe('');
});

it('saves a message left through the contact form', function () {
    $response = $this->postJson('/api/v1/contact', [
        'name' => 'Ahmad Rahimi',
        'email' => 'ahmad@example.test',
        'subject' => 'Where is my order?',
        'message' => 'Hello, I ordered a phone last week and it has not arrived yet.',
    ])->assertStatus(201);

    expect($response->json('success'))->toBeTrue();

    $message = ContactMessage::first();

    expect($message)->not->toBeNull()
        ->and($message->name)->toBe('Ahmad Rahimi')
        ->and($message->email)->toBe('ahmad@example.test')
        ->and($message->status)->toBe(ContactMessage::STATUS_NEW);
});

it('accepts a phone number instead of an email', function () {
    $this->postJson('/api/v1/contact', [
        'name' => 'Sharif Ahmadi',
        'phone' => '+93 700 222 222',
        'message' => 'Please call me about your services.',
    ])->assertStatus(201);

    expect(ContactMessage::count())->toBe(1);
});

it('refuses a message with no way to reply', function () {
    $this->postJson('/api/v1/contact', [
        'name' => 'Nobody Reachable',
        'message' => 'A message with neither an email nor a phone number.',
    ])->assertStatus(422);

    expect(ContactMessage::count())->toBe(0);
});

it('refuses a message that is only a few characters', function () {
    $this->postJson('/api/v1/contact', [
        'name' => 'Ahmad Rahimi',
        'email' => 'ahmad@example.test',
        'message' => 'Hi',
    ])->assertStatus(422);

    expect(ContactMessage::count())->toBe(0);
});

it('refuses a message with no name', function () {
    $this->postJson('/api/v1/contact', [
        'email' => 'ahmad@example.test',
        'message' => 'A perfectly reasonable message, but with no name on it.',
    ])->assertStatus(422);

    expect(ContactMessage::count())->toBe(0);
});
