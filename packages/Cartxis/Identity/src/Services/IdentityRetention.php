<?php

namespace Cartxis\Identity\Services;

use Cartxis\Identity\Models\IdentityVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deletes the pictures, keeps the records.
 *
 * A Tazkira scan is the most sensitive document a shop will ever hold: it is a
 * permanent national identifier, valid for life, and useless to a fraudster only
 * for as long as the shop keeps it. So:
 *
 *  - a superseded submission (the customer resubmitted) loses its image at once,
 *    because the newest submission is the only one a reviewer will look at;
 *  - a rejected or abandoned submission keeps its image for the retention
 *    window, in case the customer appeals and the reviewer needs to look again;
 *  - an approved submission keeps its image. That is a deliberate exception:
 *    it is the evidence the decision was made on, and the owner may want to
 *    revisit it. Delete it by hand, or with the cleanup command, if that changes.
 *
 * In every case the row survives. The fingerprint on that row is what stops the
 * same Tazkira being used to open a second account years later, and deleting the
 * picture must never weaken that.
 */
class IdentityRetention
{
    public function __construct(
        protected IdentityImageStore $images,
        protected IdentitySettings $settings,
    ) {}

    /**
     * Delete the pictures of this account's older submissions.
     *
     * The approved row is never touched: it is the current identity of a real
     * person, not a leftover.
     *
     * @return int number of images deleted
     */
    public function purgeSupersededFor(int $userId, ?int $keepVerificationId = null): int
    {
        // The one row that belongs to the account now: the submission being kept,
        // and whatever the account already points at as its verified identity.
        $protected = array_values(array_filter([
            $keepVerificationId,
            DB::table('users')->where('id', $userId)->value('identity_verification_id'),
        ], fn ($id) => $id !== null && $id !== ''));

        // Every row on this account is a candidate except two kinds: the row this
        // pass is keeping (the submission just made, and whatever the account
        // already points at), and any approved row, which is the evidence a
        // decision was made on.
        //
        // The id guard has to sit on the query itself rather than inside a nested
        // branch. Wrapped in `status != APPROVED OR ...` it only ever protected
        // approved rows, so a freshly submitted PENDING row matched the first
        // branch and had its picture deleted the instant it was written.
        $query = IdentityVerification::query()
            ->where('user_id', $userId)
            ->whereNull('image_purged_at')
            ->where('status', '!=', IdentityVerification::STATUS_APPROVED);

        if ($protected !== []) {
            $query->whereNotIn('id', $protected);
        }

        return $this->purgeQuery($query);
    }

    /**
     * The scheduled cleanup: everything past the retention window.
     *
     * Rejected and pending submissions only. Approved documents are the audit
     * evidence and are left alone unless the owner asks for them explicitly.
     */
    public function purgeExpired(?int $retentionDays = null): int
    {
        $days = $retentionDays ?? $this->settings->retentionDays();

        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days);

        return $this->purgeQuery(
            IdentityVerification::query()
                ->whereNull('image_purged_at')
                ->whereIn('status', [
                    IdentityVerification::STATUS_PENDING,
                    IdentityVerification::STATUS_REJECTED,
                ])
                ->where('created_at', '<=', $cutoff)
        );
    }

    /**
     * Delete one document and record that it has gone.
     */
    public function purgeOne(IdentityVerification $verification): bool
    {
        if ($verification->image_purged_at !== null) {
            return false;
        }

        $deleted = $this->images->delete($verification->image_disk, $verification->image_path);

        // Marked purged even when the file was already missing: the intent has
        // been carried out, and leaving it unmarked would make every later run
        // try again forever.
        $verification->forceFill(['image_purged_at' => now()])->save();

        if ($deleted) {
            Log::info('Identity document image purged', [
                'verification_id' => $verification->id,
                'user_id' => $verification->user_id,
                'disk' => $verification->image_disk,
            ]);
        }

        return $deleted;
    }

    /**
     * Delete one submission's picture, by id.
     */
    public function purgeVerification(int $verificationId): bool
    {
        $verification = IdentityVerification::query()->find($verificationId);

        return $verification ? $this->purgeOne($verification) : false;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    protected function purgeQuery($query): int
    {
        $deleted = 0;

        // Chunked so that a shop with years of archived documents does not pull
        // the whole table into memory in one go.
        $query->chunkById(200, function ($rows) use (&$deleted) {
            foreach ($rows as $row) {
                if ($this->purgeOne($row)) {
                    $deleted++;
                }
            }
        });

        return $deleted;
    }
}