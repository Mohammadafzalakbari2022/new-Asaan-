<?php

namespace App\Models\Concerns;

use Cartxis\Referral\Exceptions\ReferralBalanceOutstanding;
use Cartxis\Referral\Models\ReferralLedgerEntry;
use Cartxis\Referral\Services\ReferralSettings;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stops a bulk delete from wiping referral credit.
 *
 * The User model's own deleting hook covers single deletes, but
 * User::query()->delete() is a plain SQL statement and fires no model events at
 * all. Admin screens, console commands and API endpoints all use it, so without
 * this the rule would only be enforced on the one path nobody uses to bulk
 * delete customers.
 */
class ReferralAwareUserBuilder extends Builder
{
    public function delete()
    {
        $this->guardAgainstOutstandingCredit();

        return parent::delete();
    }

    public function forceDelete()
    {
        $this->guardAgainstOutstandingCredit();

        return parent::forceDelete();
    }

    protected function guardAgainstOutstandingCredit(): void
    {
        // Model::delete() ends up calling this builder, so the deliberate-erasure
        // escape hatch has to be honoured here as well as on the model.
        if (\App\Models\User::isDeletingWithReferralHistory()) {
            return;
        }

        if (! app(ReferralSettings::class)->blocksAccountDeletion()) {
            return;
        }

        $ids = (clone $this)->pluck('users.id');

        if ($ids->isEmpty()) {
            return;
        }

        // One query for the whole set rather than a lookup per user. The total of
        // every ledger row for an account is available plus locked credit, so a
        // positive sum means the shop still owes that person something.
        $holders = ReferralLedgerEntry::whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->havingRaw('SUM(amount) > 0')
            ->pluck('user_id')
            ->all();

        if ($holders === []) {
            return;
        }

        $total = round((float) ReferralLedgerEntry::whereIn('user_id', $holders)->sum('amount'), 2);

        throw new ReferralBalanceOutstanding(
            (int) $holders[0],
            $total,
            0.0,
            count($holders) > 1
                ? sprintf(
                    '%d accounts still hold referral credit totalling %s, so none of them were deleted. Account ids: %s',
                    count($holders),
                    number_format($total, 2),
                    implode(', ', $holders)
                )
                : null
        );
    }
}
