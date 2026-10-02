<?php

namespace Cartxis\Identity\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Cartxis\Identity\Http\Requests\ReviewIdentityVerificationRequest;
use Cartxis\Identity\Models\IdentityVerification;
use Cartxis\Identity\Services\IdentityCrypto;
use Cartxis\Identity\Services\IdentityRetention;
use Cartxis\Identity\Services\IdentityService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review queue.
 *
 * The number is decrypted here and nowhere else. It is passed to the page for
 * this reviewer to check against the photograph, which means it exists in one
 * admin response and one admin's screen, and nowhere else: not in the list, not
 * in a URL, not in a log line, not in an API response.
 */
class IdentityVerificationController extends Controller
{
    public function __construct(
        protected IdentityService $identity,
        protected IdentityCrypto $crypto,
    ) {}

    /**
     * The queue: pending first, then everything else.
     */
    public function index(Request $request): Response
    {
        $status = $request->get('status');

        $query = IdentityVerification::query()->with(['user', 'reviewer'])->orderBy('created_at');

        if ($status && in_array($status, [
            IdentityVerification::STATUS_PENDING,
            IdentityVerification::STATUS_APPROVED,
            IdentityVerification::STATUS_REJECTED,
        ], true)) {
            $query->where('status', $status);
        } else {
            $status = null;

            // Default view is the queue, oldest first, because that is the work.
            $query->where('status', IdentityVerification::STATUS_PENDING)->orderBy('created_at');
        }

        if ($search = trim((string) $request->get('search'))) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $verifications = $query
            ->paginate($request->get('per_page', 20))
            ->withQueryString();

        $verifications->setCollection($verifications->getCollection()->map(
            fn (IdentityVerification $verification) => $this->summarise($verification)
        ));

        return Inertia::render('Admin/Customers/Identity/Index', [
            'verifications' => $verifications,
            'filters' => ['status' => $status, 'search' => $request->get('search')],
            'counts' => $this->counts(),
        ]);
    }

    /**
     * One submission in full, for the reviewer to compare with the document.
     */
    public function show(IdentityVerification $verification): Response
    {
        $verification->loadMissing(['user', 'reviewer']);

        $user = $verification->user;

        return Inertia::render('Admin/Customers/Identity/Show', [
            'verification' => [
                'id' => $verification->id,
                'status' => $verification->status,
                'document_type' => $verification->document_type,
                'submitted_at' => $verification->created_at?->toDateTimeString(),
                'reviewed_at' => $verification->reviewed_at?->toDateTimeString(),
                'reviewer' => $verification->reviewer?->name,
                'rejection_reason' => $verification->rejection_reason,
                'review_note' => $verification->review_note,
                'has_image' => $verification->hasImage(),
                // Streamed through an authenticated route, never a public file.
                'image_url' => route('admin.customers.identity.document', $verification),
                // Decrypted for the reviewer only.
                'national_id' => $this->crypto->decrypt($verification->national_id_encrypted) ?? __('Unreadable'),
                'full_name' => $this->crypto->decrypt($verification->full_name_encrypted) ?? __('Unreadable'),
                'father_name' => $this->crypto->decrypt($verification->father_name_encrypted),
                'date_of_birth' => $this->crypto->decrypt($verification->date_of_birth_encrypted),
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'verified_at' => $user->identity_verified_at?->toDateTimeString(),
                ] : null,
                'attempts' => IdentityVerification::query()
                    ->where('user_id', $verification->user_id)
                    ->count(),
            ],
        ]);
    }

    public function approve(ReviewIdentityVerificationRequest $request, IdentityVerification $verification): RedirectResponse
    {
        $result = $this->identity->approve(
            $verification,
            $request->user(),
            $request->validated()['note'] ?? null,
        );

        return back()->with(
            'success',
            $result['already_verified']
                ? __('This account was already verified, so the submission was closed instead.')
                : __('Identity approved.'),
        );
    }

    public function reject(ReviewIdentityVerificationRequest $request, IdentityVerification $verification): RedirectResponse
    {
        $this->identity->reject(
            $verification,
            $request->user(),
            (string) $request->validated()['reason'],
            $request->validated()['note'] ?? null,
        );

        return back()->with('success', __('Identity rejected. The customer can submit a new document.'));
    }

    /**
     * Delete a document picture, keeping the row.
     *
     * For an approved identity this is the only way to erase the scan, so it is
     * deliberately a separate, named action rather than something that happens
     * as a side effect.
     */
    public function destroy(Request $request, IdentityVerification $verification): RedirectResponse
    {
        abort_unless($request->user()?->role === 'admin', 403);

        app(IdentityRetention::class)->purgeOne($verification);

        return back()->with('success', __('The document image has been deleted. The record was kept.'));
    }

    /**
     * The queue row.
     *
     * The national ID is masked to its last four characters here. A reviewer
     * comparing two queues should not have every ID number in the shop on one
     * screen; the full number is on the single submission page, where there is
     * exactly one of them.
     */
    protected function summarise(IdentityVerification $verification): array
    {
        return [
            'id' => $verification->id,
            'status' => $verification->status,
            'user' => $verification->user ? [
                'id' => $verification->user->id,
                'name' => $verification->user->name,
                'email' => $verification->user->email,
            ] : null,
            'name_on_document' => $this->crypto->decrypt($verification->full_name_encrypted) ?? __('Unreadable'),
            'national_id_masked' => $this->mask($this->crypto->decrypt($verification->national_id_encrypted)),
            'submitted_at' => $verification->created_at?->toDateTimeString(),
            'reviewed_at' => $verification->reviewed_at?->toDateTimeString(),
            'reviewer' => $verification->reviewer?->name,
            'rejection_reason' => $verification->rejection_reason,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        return [
            'pending' => IdentityVerification::query()->pending()->count(),
            'approved' => IdentityVerification::query()->approved()->count(),
            'rejected' => IdentityVerification::query()->where('status', IdentityVerification::STATUS_REJECTED)->count(),
        ];
    }

    protected function mask(?string $nationalId): ?string
    {
        $nationalId = (string) $nationalId;

        if ($nationalId === '') {
            return null;
        }

        $tail = substr($nationalId, -4);

        return str_repeat('•', max(0, mb_strlen($nationalId) - 4)).$tail;
    }
}