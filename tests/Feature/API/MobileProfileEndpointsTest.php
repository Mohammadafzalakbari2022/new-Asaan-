<?php

use App\Models\User;
use Cartxis\API\Http\Controllers\V1\IdentityController;
use Cartxis\API\Http\Controllers\V1\LocaleController;
use Cartxis\API\Http\Controllers\V1\ServiceController;
use Cartxis\Core\Models\Locale;
use Cartxis\Identity\Models\IdentityVerification;
use Cartxis\Identity\Services\IdentityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require_once __DIR__ . '/../Services/helpers.php';
require_once __DIR__ . '/../Identity/helpers.php';

/*
|--------------------------------------------------------------------------
| The mobile endpoints behind Profile: services, verification, languages
|--------------------------------------------------------------------------
|
| These are what the Flutter app calls. The rules they enforce are not new
| ones -- they are the storefront's rules reached by a different URL -- so
| every test here is really asking "did the app get the same answer the
| website would have given?".
|
| They are worth having separately: a mobile endpoint that quietly accepts
| what the website rejects is a rule that only exists for people using a
| browser.
|
*/

// ---------------------------------------------------------------------------
// Services
// ---------------------------------------------------------------------------

it('lists the published services for the app', function () {
    $visible = makeService(['name' => 'Deep Cleaning']);
    makeService(['name' => 'Retired Work', 'status' => 'disabled']);

    $response = $this->getJson('/api/v1/services')->assertOk();

    $names = array_column($response->json('data'), 'name');

    expect($names)->toContain('Deep Cleaning')
        ->and($names)->not->toContain('Retired Work');
});

it('serves one service with its booking window', function () {
    configureServices();
    $service = makeService(['name' => 'Deep Cleaning']);

    $response = $this->getJson('/api/v1/services/' . $service->slug)->assertOk();

    expect($response->json('data.service.name'))->toBe('Deep Cleaning')
        ->and($response->json('data.booking.enabled'))->toBeTrue()
        ->and($response->json('data.booking.earliest_date'))->not->toBeNull()
        ->and($response->json('data.settings.contact_phone'))->toBe('0700000000');
});

it('hides a service whose category is switched off', function () {
    $category = makeServiceCategory(['status' => 'disabled']);
    $service = makeService(['service_category_id' => $category->id]);

    $this->getJson('/api/v1/services/' . $service->slug)->assertNotFound();

    $names = array_column($this->getJson('/api/v1/services')->json('data'), 'name');
    expect($names)->not->toContain('Deep Cleaning');
});

it('serves the categories with their service counts', function () {
    // Both services have to name the category explicitly: makeService builds
    // its own when none is given, and a second "Cleaning" would be slugged
    // cleaning-2 and never be the row this test looks at.
    $category = makeServiceCategory(['name' => 'Cleaning']);
    makeService(['service_category_id' => $category->id, 'name' => 'Deep Cleaning']);
    makeService(['service_category_id' => $category->id, 'name' => 'Second Cleaning']);

    $response = $this->getJson('/api/v1/services/categories')->assertOk();

    $cleaning = collect($response->json('data'))->firstWhere('slug', 'cleaning');

    expect($cleaning)->not->toBeNull()
        ->and((int) $cleaning['services_count'])->toBe(2);
});

it('serves one category with the services inside it', function () {
    $category = makeServiceCategory(['name' => 'Cleaning']);
    makeService(['service_category_id' => $category->id, 'name' => 'Deep Cleaning']);
    makeService(['name' => 'Plumbing']);

    $response = $this->getJson('/api/v1/services/categories/cleaning')->assertOk();

    $names = array_column($response->json('data.services'), 'name');

    expect($response->json('data.category.slug'))->toBe('cleaning')
        ->and($names)->toContain('Deep Cleaning')
        ->and($names)->not->toContain('Plumbing');
});

it('takes a booking from the app and returns the reference', function () {
    configureServices();
    $service = makeService();

    $response = $this->postJson('/api/v1/services/' . $service->slug . '/book', [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0700000000',
        'address' => 'House 12, Street 4, Kabul',
        'scheduled_date' => bookableDate(),
        'scheduled_slot' => 'Morning',
    ])->assertCreated();

    expect($response->json('data.reference'))->not->toBeNull()
        ->and($response->json('data.status'))->toBe('booked')
        ->and($response->json('message'))->toBe('Your booking has been received.');

    $this->assertDatabaseHas('service_bookings', [
        'reference' => $response->json('data.reference'),
        'customer_phone' => '0700000000',
    ]);
});

it('tells the app which field a booking problem belongs to', function () {
    configureServices();
    $service = makeService();

    // The booking window is seven days, so a date three weeks out is outside
    // it. This is the same refusal the website gives.
    $response = $this->postJson('/api/v1/services/' . $service->slug . '/book', [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0700000000',
        'address' => 'House 12, Street 4, Kabul',
        'scheduled_date' => bookableDate(30),
        'scheduled_slot' => 'Morning',
    ])->assertStatus(422);

    expect($response->json('error_code'))->toBe('BOOKING_NOT_AVAILABLE')
        ->and(array_keys($response->json('errors')))->toContain('scheduled_date');
});

it('reports a booking that is missing a required field under that field', function () {
    configureServices();
    $service = makeService();

    $response = $this->postJson('/api/v1/services/' . $service->slug . '/book', [
        'customer_name' => 'Test Customer',
        'scheduled_date' => bookableDate(),
        'scheduled_slot' => 'Morning',
    ])->assertStatus(422);

    expect(array_keys($response->json('errors')))
        ->toContain('address', 'customer_phone');
});

it('refuses a booking from a guest when the owner requires an account', function () {
    configureServices(['require_login_to_book' => true]);
    $service = makeService();

    $this->postJson('/api/v1/services/' . $service->slug . '/book', [
        'customer_name' => 'Test Customer',
        'customer_phone' => '0700000000',
        'address' => 'House 12, Street 4, Kabul',
        'scheduled_date' => bookableDate(),
        'scheduled_slot' => 'Morning',
    ])->assertStatus(401);

    $this->assertDatabaseCount('service_bookings', 0);
});

it('lets a signed-in customer book when the owner requires an account', function () {
    configureServices(['require_login_to_book' => true]);
    $service = makeService();
    $customer = User::factory()->withoutTwoFactor()->create([
        'role' => 'customer',
        'is_active' => true,
    ]);

    $this->actingAs($customer)
        ->postJson('/api/v1/services/' . $service->slug . '/book', [
            'customer_name' => 'Test Customer',
            'customer_phone' => '0700000000',
            'address' => 'House 12, Street 4, Kabul',
            'scheduled_date' => bookableDate(),
            'scheduled_slot' => 'Morning',
        ])
        ->assertCreated();

    $this->assertDatabaseCount('service_bookings', 1);
});

it('serves the booking slots and dates for the date picker', function () {
    configureServices();
    $service = makeService();

    $response = $this->getJson('/api/v1/services/' . $service->slug . '/slots')->assertOk();

    expect($response->json('data.slots'))->toBe(['Morning', 'Afternoon'])
        ->and($response->json('data.earliest_date'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Identity verification
// ---------------------------------------------------------------------------

it('requires an account to read identity status', function () {
    $this->getJson('/api/v1/identity')->assertUnauthorized();
});

it('gives the app the customer verification state without the national ID', function () {
    $customer = identityCustomer();
    submitTazkira($customer, '100234567');

    $response = $this->actingAs($customer)->getJson('/api/v1/identity')->assertOk();

    expect($response->json('data.status'))->toBe(IdentityVerification::STATUS_PENDING)
        ->and($response->json('data.verified'))->toBeFalse()
        ->and($response->json('data.can_submit'))->toBeFalse();

    // The number itself is never in the payload, in any form.
    expect(json_encode($response->json()))->not->toContain('100234567');
});

it('tells a verified customer they are verified and cannot submit again', function () {
    $customer = verifiedCustomer();

    $response = $this->actingAs($customer)->getJson('/api/v1/identity')->assertOk();

    expect($response->json('data.verified'))->toBeTrue()
        ->and($response->json('data.status'))->toBe(IdentityVerification::STATUS_APPROVED)
        ->and($response->json('data.can_submit'))->toBeFalse();
});

it('shows a customer why their document was refused', function () {
    $customer = identityCustomer();
    $verification = submitTazkira($customer);
    app(IdentityService::class)->reject(
        $verification,
        identityStaff(),
        'The photo of the Tazkira is not readable.'
    );

    $response = $this->actingAs($customer)->getJson('/api/v1/identity')->assertOk();

    expect($response->json('data.status'))->toBe(IdentityVerification::STATUS_REJECTED)
        ->and($response->json('data.rejection_reason'))->toBe('The photo of the Tazkira is not readable.')
        ->and($response->json('data.can_submit'))->toBeTrue();
});

it('takes a Tazkira submission from the app', function () {
    Storage::fake('identity_private');
    $customer = identityCustomer();

    $response = $this->actingAs($customer)
        ->postJson('/api/v1/identity', [
            'national_id' => '100234567',
            'full_name' => 'Ahmad Rahimi',
            'father_name' => 'Mohammad',
            'date_of_birth' => '1990-04-12',
            'image' => tazkiraImage(),
        ])
        ->assertCreated();

    expect($response->json('data.status'))->toBe(IdentityVerification::STATUS_PENDING)
        ->and($response->json('data.can_submit'))->toBeFalse();

    $this->assertDatabaseHas('identity_verifications', [
        'user_id' => $customer->id,
        'status' => IdentityVerification::STATUS_PENDING,
    ]);
});

it('reports a bad document upload under the field it belongs to', function () {
    Storage::fake('identity_private');
    $customer = identityCustomer();

    $response = $this->actingAs($customer)
        ->postJson('/api/v1/identity', [
            'national_id' => '100234567',
            'full_name' => 'Ahmad Rahimi',
            'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
        ])
        ->assertStatus(422);

    expect(array_keys($response->json('errors')))->toContain('image');

    $this->assertDatabaseCount('identity_verifications', 0);
});

it('refuses the same Tazkira from a second account through the API too', function () {
    Storage::fake('identity_private');
    $first = identityCustomer();
    $second = identityCustomer();

    $this->actingAs($first)->postJson('/api/v1/identity', [
        'national_id' => '100234567',
        'full_name' => 'Ahmad Rahimi',
        'image' => tazkiraImage(),
    ])->assertCreated();

    $response = $this->actingAs($second)->postJson('/api/v1/identity', [
        'national_id' => '100234567',
        'full_name' => 'Someone Else',
        'image' => tazkiraImage(),
    ])->assertStatus(422);

    expect(array_keys($response->json('errors')))->toContain('national_id')
        ->and(IdentityVerification::count())->toBe(1);
});

it('says the feature is off rather than taking a document nobody will review', function () {
    Storage::fake('identity_private');
    app(\Cartxis\Core\Services\SettingService::class)
        ->set('identity.enabled', false, 'boolean', 'identity');
    $customer = identityCustomer();

    $response = $this->actingAs($customer)->getJson('/api/v1/identity')->assertOk();

    expect($response->json('data.available'))->toBeFalse()
        ->and($response->json('data.can_submit'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Locales
// ---------------------------------------------------------------------------

it('offers the languages the app actually ships a dictionary for', function () {
    $response = $this->getJson('/api/v1/locales')->assertOk();

    $codes = array_column($response->json('data'), 'code');

    expect($codes)->toContain('en', 'fa', 'ps');
});

it('reads the language list before anyone has signed in', function () {
    // The picker sits on the sign-in screen, so this one has to be public.
    $this->getJson('/api/v1/locales')->assertOk();
});

it('does not offer a language the admin switched on with no translation behind it', function () {
    Locale::create([
        'code' => 'fr',
        'name' => 'French',
        'native_name' => 'Français',
        'direction' => 'ltr',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 99,
    ]);

    $codes = array_column($this->getJson('/api/v1/locales')->json('data'), 'code');

    // fr.json does not exist in this build, so French is not offered.
    expect($codes)->not->toContain('fr')
        ->and($codes)->toContain('en');
});

it('marks exactly one language as the default', function () {
    $data = $this->getJson('/api/v1/locales')->json('data');

    $defaults = array_filter($data, fn (array $row) => ! empty($row['is_default']));

    expect($defaults)->toHaveCount(1);
});
