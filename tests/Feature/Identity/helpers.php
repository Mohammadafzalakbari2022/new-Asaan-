<?php

/*
|--------------------------------------------------------------------------
| Identity feature test helpers
|--------------------------------------------------------------------------
|
| The identity tests need the same fixtures in nearly every file: a customer, a
| Tazkira image, and a way to get a submission into the review queue. These live
| in their own file, required by the tests that need them, rather than in
| tests/Pest.php, which another piece of work is already editing.
|
*/

use App\Models\User;
use Cartxis\Identity\Models\IdentityVerification;
use Cartxis\Identity\Services\IdentityCrypto;
use Cartxis\Identity\Services\IdentityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (! function_exists('identityCustomer')) {
    /**
     * A plain, unverified customer account.
     */
    function identityCustomer(string $name = 'Shopper', array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $name,
            'email' => Str::lower(Str::random(8)).'@example.test',
            'password' => 'password',
            'role' => 'customer',
            'is_active' => true,
        ], $attributes));
    }
}

if (! function_exists('identityStaff')) {
    /**
     * A reviewer account.
     *
     * The address is random so one test can approve more than one submission:
     * a fixed one would collide on the unique email the second time round.
     */
    function identityStaff(): User
    {
        return User::create([
            'name' => 'Review Officer',
            'email' => 'reviewer.'.Str::lower(Str::random(8)).'@example.test',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}

if (! function_exists('nationalId')) {
    /**
     * A plausible Tazkira number. Afghan IDs are nine digits.
     */
    function nationalId(): string
    {
        return '100234567';
    }
}

if (! function_exists('tazkiraImage')) {
    /**
     * A real image, not a stub: the service decodes and re-encodes every upload,
     * so a fake file with no pixels would fail for the wrong reason.
     */
    function tazkiraImage(string $filename = 'tazkira.jpg', int $width = 640, int $height = 420): UploadedFile
    {
        return UploadedFile::fake()->image($filename, $width, $height);
    }
}

if (! function_exists('tazkiraWithExif')) {
    /**
     * A JPEG carrying an EXIF block, the way a phone camera produces.
     *
     * Built by hand because nothing in the test suite writes EXIF: an APP1
     * segment is spliced in directly after the SOI marker. A file that reaches
     * disk by being copied keeps that marker, so finding it afterwards proves the
     * pipeline did not simply save the upload.
     */
    function tazkiraWithExif(string $filename = 'phone-photo.jpg'): UploadedFile
    {
        $image = tazkiraImage($filename);
        $bytes = (string) file_get_contents($image->getRealPath());

        $payload = "Exif\0\0".
            "GPSLatitude\0\0\x01\x02\x03\x00".
            'Photographer: someone at home';

        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        // "Exif\0\0" and the marker itself must survive a naive copy.
        file_put_contents($image->getRealPath(), "\xFF\xD8".$segment.substr($bytes, 2));

        return $image;
    }
}

if (! function_exists('submitTazkira')) {
    /**
     * Put a submission into the review queue the way the customer form does.
     */
    function submitTazkira(
        User $user,
        ?string $number = null,
        ?UploadedFile $image = null,
        array $attributes = []
    ): IdentityVerification {
        return app(IdentityService::class)->submit(
            $user,
            array_merge([
                'national_id' => $number ?: nationalId(),
                'full_name' => 'Ahmad Rahimi',
                'father_name' => 'Mohammad',
                'date_of_birth' => '1990-04-12',
            ], $attributes),
            $image ?: tazkiraImage()
        );
    }
}

if (! function_exists('verifiedCustomer')) {
    /**
     * A customer who has already been through review, built the same way the
     * admin flow builds one.
     */
    function verifiedCustomer(string $name = 'Verified Shopper', array $attributes = []): User
    {
        $user = identityCustomer($name, $attributes);
        $verification = submitTazkira($user);

        app(IdentityService::class)->approve($verification, identityStaff());

        return $user->fresh();
    }
}

if (! function_exists('storedDocumentContents')) {
    /**
     * The bytes actually on the private disk.
     */
    function storedDocumentContents(IdentityVerification $verification): string
    {
        return (string) Storage::disk($verification->image_disk)->get($verification->image_path);
    }
}

if (! function_exists('fingerprintOf')) {
    function fingerprintOf(string $number): string
    {
        return app(IdentityCrypto::class)->fingerprint($number);
    }
}

if (! function_exists('identityTableRow')) {
    /**
     * The raw column, straight out of the database, bypassing any casting or
     * model accessor. Used to prove what is actually stored.
     */
    function identityTableRow(int $verificationId): ?object
    {
        return DB::table('identity_verifications')->where('id', $verificationId)->first();
    }
}

if (! function_exists('decryptedNationalId')) {
    function decryptedNationalId(IdentityVerification $verification): ?string
    {
        return app(IdentityCrypto::class)->decrypt($verification->national_id_encrypted);
    }
}