<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Private disk for identity documents
    |--------------------------------------------------------------------------
    |
    | This disk holds copies of people's national IDs. It must never be the
    | public disk, and it must never carry "serve" => true or a "url" key:
    | a served local disk gets a public /storage/{path} route, and a url makes
    | every file fetchable by anyone who can guess the name.
    |
    | The root lives outside storage/app/public (the only directory the
    | storage:link symlink exposes) and outside storage/app/private (the root
    | the "local" disk serves).
    |
    */

    'disk' => env('IDENTITY_DISK', 'identity_private'),

    /*
    |--------------------------------------------------------------------------
    | Fingerprint key
    |--------------------------------------------------------------------------
    |
    | The unique fingerprint of a national ID is an HMAC keyed with this value.
    | Leaving it empty derives a separate key from APP_KEY, so the fingerprint
    | space is never the same key space as anything else in the app.
    |
    | Set it explicitly (IDENTITY_FINGERPRINT_KEY) to keep fingerprints valid
    | across an APP_KEY rotation, at the cost of one more secret to manage.
    |
    */

    'fingerprint_key' => env('IDENTITY_FINGERPRINT_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Accepted uploads
    |--------------------------------------------------------------------------
    |
    | Checked against the file's own bytes, never against the name the browser
    | sent. The extension written to disk is derived from the detected mime type.
    |
    */

    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],

    'max_upload_kb' => 4096,

    /*
    |--------------------------------------------------------------------------
    | Largest accepted pixel dimensions
    |--------------------------------------------------------------------------
    |
    | A tiny file can decode into a huge image and exhaust memory while being
    | re-encoded to strip its metadata. Refusing the oversized image up front is
    | cheaper than defending against it afterwards.
    |
    */

    'max_dimension' => 6000,

    /*
    |--------------------------------------------------------------------------
    | Longest edge kept after re-encoding
    |--------------------------------------------------------------------------
    |
    | Every upload is decoded and re-encoded by the image library rather than
    | copied byte for byte. That is what removes EXIF (which carries the GPS
    | coordinates of the person's home) and any other embedded metadata, and it
    | is also where the image is capped.
    |
    */

    'max_edge' => 2000,

    /*
    |--------------------------------------------------------------------------
    | Resubmission limits
    |--------------------------------------------------------------------------
    |
    | One pending submission at a time per account, and a daily ceiling on how
    | many times an account can try. Both stop a rejected customer from filling
    | the private disk with copies of the same forged document.
    |
    */

    'max_pending_per_user' => 1,

    'max_submissions_per_day' => 3,

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Days a rejected or superseded document image is kept before the cleanup
    | command deletes it. The row (fingerprint, decision, reviewer, reason) is
    | always kept: it is the audit trail that stops the same Tazkira being
    | tried again on another account.
    |
    */

    'retention_days' => (int) env('IDENTITY_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Referral gate
    |--------------------------------------------------------------------------
    |
    | Identity verification is required before a referral reward can be earned.
    | Turning this off is the owner's decision alone, and it is recorded in the
    | admin log so a store that opens the gate cannot do it quietly.
    |
    */

    'require_verification_for_referral' => true,

    /*
    |--------------------------------------------------------------------------
    | Face matching
    |--------------------------------------------------------------------------
    |
    | Reserved and OFF. The columns for a face match and a selfie reference
    | exist in the table so that enabling it later is a setting change rather
    | than a schema change, but nothing writes them and nothing reads them.
    |
    */

    'face_match_enabled' => false,

];