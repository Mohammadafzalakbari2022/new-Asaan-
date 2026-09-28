<?php

namespace Cartxis\Referral\Exceptions;

use RuntimeException;

/**
 * Thrown when an account is deleted while it still holds referral credit.
 *
 * The referral ledger is the shop's record of money it owes people, so it is not
 * allowed to disappear quietly along with an account row.
 */
class ReferralBalanceOutstanding extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly float $available = 0.0,
        public readonly float $locked = 0.0,
        ?string $message = null,
    ) {
        parent::__construct($message ?? sprintf(
            'User %d still holds referral credit: %s available and %s locked. '
            .'Spend it, reverse it, or call User::allowDeletingWithReferralHistory() to erase it deliberately.',
            $userId,
            number_format($available, 2),
            number_format($locked, 2)
        ));
    }
}
