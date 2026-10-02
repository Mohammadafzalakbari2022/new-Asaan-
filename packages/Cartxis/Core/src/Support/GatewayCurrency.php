<?php

namespace Cartxis\Core\Support;

use Cartxis\Core\Models\Currency;

/**
 * The currency each payment gateway settles in, and the one place a gateway's
 * hardcoded 'inr' used to be.
 *
 * Two kinds of gateway, and the difference matters:
 *
 *  - A CARD gateway (Stripe, PayPal, PayUMoney) settles in USD. Its own API
 *    demands an amount in its own currency, so the canonical AFN total is
 *    converted ONCE on the way out. That conversion is a settlement concern,
 *    not a display one: the order row still holds AFN, the refund is computed
 *    back to AFN.
 *
 *  - A LOCAL gateway (HesabPay) settles in AFN and nothing else. It is an
 *    Afghan wallet and charges afghani. It gets the canonical AFN total
 *    untouched.
 *
 * The trap this class exists to prevent: a gateway that settles in AFN being
 * handed a converted number. If the amount were converted to USD and then
 * labelled AFN, or converted twice, the shopper is charged the wrong amount and
 * the verified webhook then fails its amount check, so a paid order never gets
 * marked paid. afnTotal() therefore takes the canonical amount and returns it
 * as-is, and amount() is the only thing that ever converts.
 */
final class GatewayCurrency
{
    /**
     * Gateways that settle in USD and therefore receive a converted amount.
     *
     * @var list<string>
     */
    private const USD_SETTLING = ['stripe', 'paypal', 'payumoney'];

    /**
     * Gateways that settle in AFN and always receive the canonical amount.
     *
     * HesabPay and nothing else. It is an Afghan wallet: it debits afghani from
     * a local bank account and has no dollar option at all, so handing it a
     * converted figure would charge the wrong sum of money.
     *
     * @var list<string>
     */
    private const AFN_SETTLING = ['hesabpay'];

    /**
     * The currency a gateway charges in, lower case for card APIs that insist
     * on it ('usd', not 'USD') and upper case otherwise.
     */
    public static function settle(string $gatewayCode, bool $lowerCase = true): string
    {
        $code = self::isUsdSettling($gatewayCode) ? Currency::OPTIONAL_CODE : Currency::BASE_CODE;

        return $lowerCase ? strtolower($code) : $code;
    }

    /**
     * True when this gateway converts the canonical AFN amount on the way out.
     */
    public static function isUsdSettling(string $gatewayCode): bool
    {
        return in_array(strtolower(trim($gatewayCode)), self::USD_SETTLING, true);
    }

    /**
     * True when this gateway is paid in afghani and must never be converted.
     */
    public static function isAfnSettling(string $gatewayCode): bool
    {
        return in_array(strtolower(trim($gatewayCode)), self::AFN_SETTLING, true);
    }

    /**
     * The amount a gateway should actually charge, in its own currency.
     *
     * AFN-settling gateways get the canonical amount back unchanged -- there is
     * no second conversion and no rounding, because a charge has to match
     * orders.total to the paisa or the webhook rejects it.
     */
    public static function amount(float $canonicalAfnAmount, string $gatewayCode): float
    {
        if (! self::isUsdSettling($gatewayCode)) {
            return $canonicalAfnAmount;
        }

        return DisplayCurrency::convert($canonicalAfnAmount, Currency::OPTIONAL_CODE);
    }

    /**
     * The canonical AFN amount, for a gateway that settles in afghani.
     *
     * Separate from amount() on purpose so an AFN gateway reads as "this is
     * the AFN total" at the call site rather than "this is some conversion of
     * the total", which is how a double conversion gets in.
     */
    public static function afnTotal(float $canonicalAfnAmount): float
    {
        return $canonicalAfnAmount;
    }

    /**
     * A single amount in the smallest unit the gateway expects.
     *
     * Stripe works in the currency's minor unit (cents); for AFN there is no
     * minor unit at all, which is exactly why its multiplier is 1 and not 100.
     */
    public static function minorUnits(float $canonicalAfnAmount, string $gatewayCode): int
    {
        $amount = self::amount($canonicalAfnAmount, $gatewayCode);

        $multiplier = self::isUsdSettling($gatewayCode) ? 100 : 1;

        return (int) round($amount * $multiplier);
    }

    /**
     * The charge, written out for a receipt, in the currency it was taken in.
     *
     * For a payment confirmation this is the only honest figure to show: the
     * amount on the shopper's card statement. It replaced a hardcoded rupee
     * sign with two decimals, which told an Afghan customer their 1,250 afghani
     * order cost "₹1,250.00" -- the wrong symbol, the wrong currency, and a
     * decimal they do not have.
     */
    public static function describeCharge(float $canonicalAfnAmount, string $gatewayCode): string
    {
        $amount = self::amount($canonicalAfnAmount, $gatewayCode);

        $decimals = self::isUsdSettling($gatewayCode) ? 2 : 0;

        return number_format($amount, $decimals, '.', ',');
    }

    /**
     * The charge, formatted with its currency symbol, for a receipt.
     */
    public static function formatCharge(float $canonicalAfnAmount, string $gatewayCode): string
    {
        $usdSettling = self::isUsdSettling($gatewayCode);

        return ($usdSettling ? '$' : "\u{060B}") . self::describeCharge($canonicalAfnAmount, $gatewayCode);
    }
}
