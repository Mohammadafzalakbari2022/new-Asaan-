<?php

namespace Cartxis\Identity\Services;

use App\Models\User;
use Cartxis\Identity\Models\IdentityVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The whole verification lifecycle: submit, approve, reject.
 *
 * There is no external identity service to call. Nothing trustworthy and public
 * answers "is this Afghan national ID real?", so the decision is a person looking
 * at a document, and everything here exists to make that person's decision safe
 * and to make the reward for faking one impossible to collect.
 *
 * What stops one person holding many accounts:
 *
 *  - the fingerprint of the national ID is unique in the database, so the same
 *    Tazkira cannot be registered twice no matter how the checks above are
 *    bypassed;
 *  - the fingerprint is checked against every row, including rejected ones, so a
 *    rejection does not release a document for reuse;
 *  - the check is "has a DIFFERENT account used this number", so the same person
 *    can still fix a mistake and resubmit;
 *  - the document image is digested as well, so one forged picture cannot back
 *    two accounts under two different numbers.
 */
class IdentityService
{
    public function __construct(
        protected IdentityCrypto $crypto,
        protected IdentityImageStore $images,
        protected IdentityConfig $config,
        protected IdentitySettings $settings,
        protected IdentityRetention $retention,
        protected IdentityEarningBridge $bridge,
    ) {}

    /**
     * Take a customer's submission and queue it for review.
     *
     * @param  array{national_id: string, full_name: string, father_name?: ?string, date_of_birth?: ?string}  $data
     */
    public function submit(User $user, array $data, UploadedFile $document): IdentityVerification
    {
        if (! $this->settings->isEnabled()) {
            throw ValidationException::withMessages([
                'national_id' => __('Identity verification is not available right now. Please try again later.'),
            ]);
        }

        if ($user->identity_verified_at) {
            throw ValidationException::withMessages([
                'national_id' => __('Your identity is already verified.'),
            ]);
        }

        $this->guardAgainstDuplicateSubmission($user);
        $this->guardAgainstNumberOwnedByAnotherAccount($data['national_id'], $user);

        $fingerprint = $this->crypto->fingerprint($data['national_id']);

        $stored = null;

        try {
            // The file is written before the row, because a row with no picture
            // is useless to a reviewer. If the row cannot be written, the file is
            // deleted again in the catch below.
            $stored = $this->images->store($document, (int) $user->id);

            $this->guardAgainstImageUsedByAnotherAccount($stored['sha256'], $user);

            $verification = DB::transaction(function () use ($user, $data, $fingerprint, $stored) {
                // Re-checked inside the transaction. Two tabs open on the same
                // account, or two accounts submitting at the same instant, can
                // both pass the checks above; only one of them gets past this
                // point, and the other is told why.
                $this->guardAgainstDuplicateSubmission($user);
                $this->guardAgainstNumberOwnedByAnotherAccount($data['national_id'], $user);
                $this->guardAgainstImageUsedByAnotherAccount($stored['sha256'], $user);

                return IdentityVerification::create([
                    'user_id' => $user->id,
                    'national_id_encrypted' => $this->crypto->encrypt($this->crypto->normalise($data['national_id'])),
                    'national_id_fingerprint' => $fingerprint,
                    'full_name_encrypted' => $this->crypto->encrypt($data['full_name']),
                    'father_name_encrypted' => ($data['father_name'] ?? null)
                        ? $this->crypto->encrypt((string) $data['father_name'])
                        : null,
                    'date_of_birth_encrypted' => ! empty($data['date_of_birth'])
                        ? $this->crypto->encrypt((string) $data['date_of_birth'])
                        : null,
                    'document_type' => IdentityVerification::DOCUMENT_TAZKIRA,
                    'image_disk' => $stored['disk'],
                    'image_path' => $stored['path'],
                    'image_sha256' => $stored['sha256'],
                    'status' => IdentityVerification::STATUS_PENDING,
                ]);
            });
        } catch (Throwable $e) {
            if (is_array($stored)) {
                $this->images->delete($stored['disk'], $stored['path']);
            }

            throw $e;
        }

        // Only now, with the new submission safely stored, is the previous one
        // superseded and its picture deleted.
        $this->retention->purgeSupersededFor((int) $user->id, (int) $verification->id);

        Log::info('Identity document submitted', [
            'verification_id' => $verification->id,
            'user_id' => $user->id,
            // The fingerprint identifies the row without carrying the number.
            'fingerprint' => $this->crypto->shortFingerprint($fingerprint),
        ]);

        return $verification->fresh();
    }

    /**
     * A reviewer's approve.
     *
     * @return array{verification: IdentityVerification, already_verified: bool}
     */
    public function approve(IdentityVerification $verification, User $reviewer, ?string $note = null): array
    {
        $result = DB::transaction(function () use ($verification, $reviewer, $note) {
            $row = IdentityVerification::query()->lockForUpdate()->findOrFail($verification->id);

            if ($row->isApproved()) {
                return ['verification' => $row, 'already_verified' => true];
            }

            if (! $row->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('This submission has already been decided.'),
                ]);
            }

            $user = User::query()->lockForUpdate()->findOrFail($row->user_id);

            // One approved identity per account. If this account is already
            // verified from an earlier submission, the newer one is closed out
            // rather than replacing the approval that may already have earned
            // somebody a reward.
            if ($user->identity_verified_at) {
                $row->update([
                    'status' => IdentityVerification::STATUS_REJECTED,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                    'rejection_reason' => __('This account is already verified from an earlier submission.'),
                    'review_note' => $note,
                ]);

                return ['verification' => $row->fresh(), 'already_verified' => true];
            }

            $row->update([
                'status' => IdentityVerification::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'review_note' => $note,
            ]);

            // The unique index on this column is what makes "one approved
            // identity per account" true in the database and not only in this
            // method: the same verification row cannot be claimed twice.
            //
            // forceFill, not update: these two columns are deliberately absent
            // from the User model's $fillable so that no form can ever set
            // them. A plain update() would therefore throw the values away
            // silently, leaving an approved account unverified.
            $user->forceFill([
                'identity_verified_at' => now(),
                'identity_verification_id' => $row->id,
            ])->save();

            return ['verification' => $row->fresh(), 'already_verified' => false];
        });

        Log::info('Identity document reviewed', [
            'verification_id' => $result['verification']->id,
            'user_id' => $result['verification']->user_id,
            'decision' => $result['verification']->status,
            'reviewer_id' => $reviewer->id,
            'fingerprint' => $this->crypto->shortFingerprint($result['verification']->national_id_fingerprint),
        ]);

        if (! $result['already_verified']) {
            // Outside the transaction on purpose: a payout that fails must not
            // roll back a reviewer's decision, and the payout is idempotent, so
            // it is safe to run again.
            $user = User::find($result['verification']->user_id);

            if ($user) {
                $this->bridge->afterVerification($user, $result['verification']);
            }
        }

        return $result;
    }

    /**
     * A reviewer's reject. The customer may submit again.
     */
    public function reject(
        IdentityVerification $verification,
        User $reviewer,
        string $reason,
        ?string $note = null
    ): IdentityVerification {
        $row = DB::transaction(function () use ($verification, $reviewer, $reason, $note) {
            $locked = IdentityVerification::query()->lockForUpdate()->findOrFail($verification->id);

            if ($locked->isRejected()) {
                return $locked;
            }

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('This submission has already been decided.'),
                ]);
            }

            $locked->update([
                'status' => IdentityVerification::STATUS_REJECTED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
                'review_note' => $note,
            ]);

            return $locked->fresh();
        });

        Log::info('Identity document rejected', [
            'verification_id' => $row->id,
            'user_id' => $row->user_id,
            'reviewer_id' => $reviewer->id,
        ]);

        return $row;
    }

    /**
     * The submission the customer page should show: the newest one.
     */
    public function latestFor(User $user): ?IdentityVerification
    {
        return IdentityVerification::query()->latestFor((int) $user->id)->first();
    }

    /**
     * One submission at a time per account.
     *
     * Without this, a rejected customer could queue a hundred forged documents
     * before a reviewer ever looked at the first one.
     */
    protected function guardAgainstDuplicateSubmission(User $user): void
    {
        $pending = IdentityVerification::query()
            ->where('user_id', $user->id)
            ->where('status', IdentityVerification::STATUS_PENDING)
            ->count();

        if ($pending >= $this->config->maxPendingPerUser()) {
            throw ValidationException::withMessages([
                'image' => __('You already have a document waiting to be reviewed. We will let you know as soon as it has been looked at.'),
            ]);
        }
    }

    /**
     * The core anti-fraud check: this number, on this account, is not already
     * attached to somebody else's.
     *
     * Deliberately says nothing about who holds the number. A customer told "that
     * ID belongs to account 47" learns the shape of the customer base and can
     * walk down the list of numbers; a customer told only that it cannot be used
     * learns nothing they did not already know.
     */
    protected function guardAgainstNumberOwnedByAnotherAccount(string $nationalId, User $user): void
    {
        $taken = IdentityVerification::query()
            ->where('national_id_fingerprint', $this->crypto->fingerprint($nationalId))
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'national_id' => __('This national ID has already been used by another account. If this is your ID, please contact support.'),
            ]);
        }
    }

    /**
     * The same picture cannot back two accounts either.
     *
     * A forged document reused under two different numbers would pass the number
     * check twice, so the stored bytes are digested too. Only an exact re-upload
     * collides, which is why this is a second signal and not the only one.
     */
    protected function guardAgainstImageUsedByAnotherAccount(string $digest, User $user): void
    {
        if ($digest === '') {
            return;
        }

        $taken = IdentityVerification::query()
            ->where('image_sha256', $digest)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'image' => __('This document image has already been submitted by another account. Please contact support.'),
            ]);
        }
    }
}