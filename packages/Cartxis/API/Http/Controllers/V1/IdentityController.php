<?php

namespace Cartxis\API\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Cartxis\API\Helpers\ApiResponse;
use Cartxis\Identity\Http\Requests\SubmitIdentityVerificationRequest;
use Cartxis\Identity\Models\IdentityVerification;
use Cartxis\Identity\Services\IdentityConfig;
use Cartxis\Identity\Services\IdentityService;
use Cartxis\Identity\Services\IdentitySettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The customer's Tazkira verification, for the mobile app.
 *
 * Same rules and the same service as the website, because the anti-fraud
 * checks live in IdentityService and must not have a second, weaker copy
 * written here. The app sends the same fields, including the photo, and gets
 * back the same state the website shows.
 *
 * Nothing returned contains the national ID. The customer typed it, so
 * showing it back would be no help, and a response that carries a national
 * number is one that ends up in a log file. What comes back is the status,
 * the dates and, if it was refused, the reviewer's reason.
 */
class IdentityController extends Controller
{
    public function __construct(
        protected IdentityService $identity,
        protected IdentitySettings $settings,
        protected IdentityConfig $config,
    ) {}

    /**
     * The signed-in customer's current verification state.
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();
        $latest = $this->identity->latestFor($user);

        return ApiResponse::success([
            'verified' => $user->identity_verified_at !== null,
            'verified_at' => $user->identity_verified_at?->toDateString(),
            'status' => $latest?->status ?? IdentityVerification::STATUS_PENDING,
            'submitted_at' => $latest?->created_at?->toDateString(),
            'reviewed_at' => $latest?->reviewed_at?->toDateString(),
            // The customer's own rejection reason, never the reviewer note.
            'rejection_reason' => $latest?->isRejected() ? $latest->rejection_reason : null,
            'can_submit' => $this->canSubmit(),
            'available' => $this->settings->isEnabled(),
            'requirements' => [
                'max_upload_kb' => $this->config->maxUploadKb(),
                'accepted_types' => 'JPG, PNG, WebP',
                'required_document' => 'Tazkira (national identity card)',
            ],
        ], 'Identity status retrieved successfully');
    }

    /**
     * Send a Tazkira for review.
     *
     * The FormRequest does the size and type checks, the service does the
     * duplicate checks and the storage, and the message comes back in the
     * field it belongs to so the app can show it under that input.
     */
    public function store(SubmitIdentityVerificationRequest $request): JsonResponse
    {
        $this->identity->submit(
            $request->user(),
            $request->submission(),
            $request->file('image'),
        );

        return ApiResponse::success([
            'status' => IdentityVerification::STATUS_PENDING,
            'can_submit' => false,
        ], 'Thank you. Your identity document has been sent for review.', 201);
    }

    /**
     * Whether the form should be offered at all.
     *
     * A verified account has nothing left to submit, and an account with a
     * submission in the queue would only be able to queue another one, which
     * is exactly the flooding the limit exists to stop.
     */
    protected function canSubmit(): bool
    {
        $user = Auth::user();

        if (! $this->settings->isEnabled() || $user->identity_verified_at) {
            return false;
        }

        return IdentityVerification::query()
            ->where('user_id', $user->id)
            ->where('status', IdentityVerification::STATUS_PENDING)
            ->count() < $this->config->maxPendingPerUser();
    }
}
