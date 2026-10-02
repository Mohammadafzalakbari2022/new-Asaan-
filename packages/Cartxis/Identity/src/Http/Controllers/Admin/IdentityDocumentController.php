<?php

namespace Cartxis\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Identity\Models\IdentityVerification;
use Cartxis\Identity\Services\IdentityCrypto;
use Cartxis\Identity\Services\IdentityImageStore;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands the document to a reviewer, and to nobody else.
 *
 * This route exists because the file cannot be reached any other way. There is
 * no URL for it: the private disk has no url and is not served, and the picture
 * is not in the media library. A reviewer opens the submission page, the page
 * points at this route, and the browser sends the staff session with it.
 *
 * Consequences that are deliberate:
 *
 *  - the response is never cached, so a shared machine cannot serve it again
 *    from disk after the reviewer signs out;
 *  - every view is written to the log with the reviewer and the record, not with
 *    the number, so there is a trail of who looked at whose document without a
 *    trail of everyone's ID numbers.
 */
class IdentityDocumentController extends Controller
{
    public function __construct(
        protected IdentityImageStore $images,
        protected IdentityCrypto $crypto,
    ) {}

    public function show(IdentityVerification $verification): Response
    {
        abort_unless(auth()->user()?->role === 'admin', 403);

        // A decided submission still has its picture until the retention window
        // passes, so this does not check status.
        abort_if(! $verification->hasImage(), 404);

        $contents = $this->images->get($verification->image_disk, $verification->image_path);

        // 404 rather than 500 when the file has gone but the row has not been
        // marked as purged yet.
        abort_if($contents === null, 404);

        Log::info('Identity document viewed', [
            'verification_id' => $verification->id,
            'user_id' => $verification->user_id,
            'reviewer_id' => auth()->id(),
            'fingerprint' => $this->crypto->shortFingerprint($verification->national_id_fingerprint),
        ]);

        return response($contents, 200, [
            'Content-Type' => $this->mimeFor($verification->image_path),
            // Served to the reviewer, shown in the page, never downloaded to a
            // name the customer could later share.
            'Content-Disposition' => 'inline; filename="identity-document"',
            'Content-Length' => (string) strlen($contents),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            // Belt and braces for a browser extension or a proxy that tries to
            // run the response as a document.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    protected function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}