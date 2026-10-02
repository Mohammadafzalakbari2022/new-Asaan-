<?php

namespace Cartxis\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Core\Services\ThemeViewResolver;
use Cartxis\Identity\Http\Requests\SubmitIdentityVerificationRequest;
use Cartxis\Identity\Models\IdentityVerification;
use Cartxis\Identity\Services\IdentityConfig;
use Cartxis\Identity\Services\IdentityService;
use Cartxis\Identity\Services\IdentitySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * The customer's own verification page.
 *
 * Nothing on this page ever contains the national ID. The customer typed it, so
 * showing it back would be no help, and a page that displays a national number
 * is a page that ends up in a screenshot, a support ticket or a browser history.
 * What they get instead is the state of their submission and, when it was
 * refused, the reviewer's reason.
 */
class IdentityVerificationController extends Controller
{
    public function __construct(
        protected IdentityService $identity,
        protected IdentitySettings $settings,
        protected IdentityConfig $config,
        protected ThemeViewResolver $themes,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $latest = $this->identity->latestFor($user);

        return $this->themes->inertia('Account/Identity/Index', [
            'identity' => [
                'verified' => $user->identity_verified_at !== null,
                'verified_at' => $user->identity_verified_at?->toDateString(),
                'status' => $latest?->status ?? IdentityVerification::STATUS_PENDING,
                'submitted_at' => $latest?->created_at?->toDateString(),
                'reviewed_at' => $latest?->reviewed_at?->toDateString(),
                // The customer's own rejection reason. Deliberately not the
                // reviewer note, which is staff-only.
                'rejection_reason' => $latest?->isRejected() ? $latest->rejection_reason : null,
                'can_submit' => $this->canSubmit($user),
            ],
            'requirements' => [
                'max_upload_kb' => $this->config->maxUploadKb(),
                'accepted_types' => implode(', ', ['JPG', 'PNG', 'WebP']),
                'required_document' => __('Tazkira (national identity card)'),
            ],
            'available' => $this->settings->isEnabled(),
        ]);
    }

    public function store(SubmitIdentityVerificationRequest $request): RedirectResponse
    {
        $this->identity->submit(
            $request->user(),
            $request->submission(),
            $request->file('image'),
        );

        return back()->with('success', __('Thank you. Your identity document has been sent for review.'));
    }

    /**
     * Whether the form should be offered at all.
     *
     * A verified account has nothing left to submit, and an account with a
     * submission in the queue would only be able to queue another one, which is
     * exactly the flooding the limit exists to stop.
     */
    protected function canSubmit(\App\Models\User $user): bool
    {
        if (! $this->settings->isEnabled() || $user->identity_verified_at) {
            return false;
        }

        return IdentityVerification::query()
            ->where('user_id', $user->id)
            ->where('status', IdentityVerification::STATUS_PENDING)
            ->count() < $this->config->maxPendingPerUser();
    }
}