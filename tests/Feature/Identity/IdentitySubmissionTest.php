<?php

require_once __DIR__ . '/helpers.php';

use Cartxis\Identity\Models\IdentityVerification;
use Cartxis\Identity\Services\IdentityCrypto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| A customer submits their Tazkira
|--------------------------------------------------------------------------
*/

it('puts a submitted document in the review queue as pending', function () {
    $customer = identityCustomer();

    $this->actingAs($customer)
        ->post(route('shop.account.identity.store'), [
            'national_id' => '100234567',
            'full_name' => 'Ahmad Rahimi',
            'father_name' => 'Mohammad',
            'date_of_birth' => '1990-04-12',
            'image' => tazkiraImage(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $verification = IdentityVerification::first();

    expect($verification)->not->toBeNull()
        ->and($verification->status)->toBe(IdentityVerification::STATUS_PENDING)
        ->and((int) $verification->user_id)->toBe((int) $customer->id)
        ->and($verification->reviewed_by)->toBeNull()
        ->and($verification->reviewed_at)->toBeNull()
        ->and($verification->document_type)->toBe('tazkira')
        ->and($customer->fresh()->identity_verified_at)->toBeNull();
});

it('shows the customer their own state and never their national ID number', function () {
    $customer = identityCustomer();
    submitTazkira($customer, '100234567');

    $response = $this->actingAs($customer)->get('/account/identity')->assertSuccessful();

    $response->assertInertia(fn ($page) => $page
        ->where('identity.status', 'pending')
        ->where('identity.verified', false)
    );

    // The number is the customer's own, so showing it back helps nobody and puts
    // a national ID into browser history and support screenshots.
    expect($response->getContent())->not->toContain('100234567');
});

it('keeps a signed out visitor out of the identity page', function () {
    $this->get('/account/identity')->assertRedirect();
});

/*
|--------------------------------------------------------------------------
| What a submission is allowed to contain
|--------------------------------------------------------------------------
*/

it('refuses a submission with no number, no name or no picture', function (string $field) {
    $payload = [
        'national_id' => '100234567',
        'full_name' => 'Ahmad Rahimi',
        'image' => tazkiraImage(),
    ];

    unset($payload[$field]);

    $this->actingAs(identityCustomer())
        ->post(route('shop.account.identity.store'), $payload)
        ->assertSessionHasErrors($field);

    expect(IdentityVerification::count())->toBe(0);
})->with(['national_id', 'full_name', 'image']);

it('refuses a picture that is not an image, whatever it is named', function () {
    $this->actingAs(identityCustomer())
        ->post(route('shop.account.identity.store'), [
            'national_id' => '100234567',
            'full_name' => 'Ahmad Rahimi',
            'image' => UploadedFile::fake()->create('id.pdf', 40, 'application/pdf'),
        ])
        ->assertSessionHasErrors('image');

    expect(IdentityVerification::count())->toBe(0);
});

it('refuses a file that is named like a picture but is not one', function () {
    // The name says jpg, the bytes say PHP. Nothing downstream reads the name,
    // and the service checks the bytes.
    $this->actingAs(identityCustomer())
        ->post(route('shop.account.identity.store'), [
            'national_id' => '100234567',
            'full_name' => 'Ahmad Rahimi',
            'image' => UploadedFile::fake()->create('tazkira.jpg', 40, 'application/x-php'),
        ])
        ->assertSessionHasErrors('image');

    expect(IdentityVerification::count())->toBe(0);
});

it('refuses a picture larger than the limit', function () {
    // A megabyte of nothing, named as an image.
    $oversized = tazkiraImage('huge.jpg', 3000, 3000);

    config()->set('identity.max_upload_kb', 10);

    $this->actingAs(identityCustomer())
        ->post(route('shop.account.identity.store'), [
            'national_id' => '100234567',
            'full_name' => 'Ahmad Rahimi',
            'image' => $oversized,
        ])
        ->assertSessionHasErrors('image');

    expect(IdentityVerification::count())->toBe(0);
});

it('refuses a picture with pixel dimensions past the ceiling', function () {
    config()->set('identity.max_dimension', 100);

    $this->actingAs(identityCustomer())
        ->post(route('shop.account.identity.store'), [
            'national_id' => '100234567',
            'full_name' => 'Ahmad Rahimi',
            'image' => tazkiraImage('big.jpg', 800, 600),
        ])
        ->assertSessionHasErrors('image');

    expect(IdentityVerification::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Anti-abuse: flooding the queue
|--------------------------------------------------------------------------
*/

it('lets one account have only one document waiting at a time', function () {
    $customer = identityCustomer();

    submitTazkira($customer, '100234567');

    $this->actingAs($customer)
        ->post(route('shop.account.identity.store'), [
            'national_id' => '100234568',
            'full_name' => 'Ahmad Rahimi',
            'image' => tazkiraImage('second.jpg'),
        ])
        ->assertSessionHasErrors('image');

    expect(IdentityVerification::count())->toBe(1);
});

it('stops an account hammering the form', function () {
    config()->set('identity.max_submissions_per_day', 3);

    $customer = identityCustomer();

    // Each attempt is rejected and cleared so the next one is not blocked by the
    // one-in-the-queue rule. The point of this test is the rate limiter, not the
    // queue limit.
    foreach (range(1, 3) as $ignored) {
        $this->actingAs($customer)
            ->post(route('shop.account.identity.store'), [
                'national_id' => (string) (100234560 + $ignored),
                'full_name' => 'Ahmad Rahimi',
                'image' => tazkiraImage("attempt-{$ignored}.jpg"),
            ])
            ->assertSessionHasNoErrors();

        IdentityVerification::query()->delete();
    }

    $this->actingAs($customer)
        ->post(route('shop.account.identity.store'), [
            'national_id' => '100234599',
            'full_name' => 'Ahmad Rahimi',
            'image' => tazkiraImage('too-many.jpg'),
        ])
        ->assertStatus(429);

    expect(IdentityVerification::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The picture is re-encoded, not copied
|--------------------------------------------------------------------------
*/

it('rebuilds the picture instead of saving the upload byte for byte', function () {
    $upload = tazkiraWithExif('phone-photo.jpg');
    $uploadedBytes = (string) file_get_contents($upload->getRealPath());

    $customer = identityCustomer();
    $verification = submitTazkira($customer, '100234567', $upload);

    $stored = storedDocumentContents($verification);

    // The EXIF block the "phone camera" carried is gone, which is the point: a
    // copied upload would still contain it.
    expect($stored)->not->toContain('Exif')
        ->and($stored)->not->toContain('Photographer')
        ->and($stored)->not->toBe($uploadedBytes);

    // Still a readable picture, so the reviewer can actually do their job.
    expect(@getimagesizefromstring($stored))->not->toBeFalse();
});

it('caps a huge upload and keeps it readable', function () {
    config()->set('identity.max_edge', 300);

    $customer = identityCustomer();
    $verification = submitTazkira($customer, '100234567', tazkiraImage('huge.jpg', 2400, 1200));

    $size = @getimagesizefromstring(storedDocumentContents($verification));

    expect($size)->not->toBeFalse()
        ->and(max($size[0], $size[1]))->toBe(300);
});

it('never lets the name the browser sent decide where the file goes', function () {
    $customer = identityCustomer();

    $verification = submitTazkira($customer, '100234567', tazkiraImage('../../../../evil.php.jpg'));

    expect($verification->image_path)->toStartWith("identity/{$customer->id}/")
        ->and($verification->image_path)->not->toContain('..')
        ->and(pathinfo($verification->image_path, PATHINFO_EXTENSION))->toBe('jpg')
        ->and(Storage::disk($verification->image_disk)->exists($verification->image_path))->toBeTrue();
});

it('stores a real Tazkira number where it can be read back by the reviewer only', function () {
    $verification = submitTazkira(identityCustomer(), '100234567');

    expect(decryptedNationalId($verification))->toBe('100234567')
        ->and(app(IdentityCrypto::class)->fingerprint('100234567'))->toBe($verification->national_id_fingerprint);
});