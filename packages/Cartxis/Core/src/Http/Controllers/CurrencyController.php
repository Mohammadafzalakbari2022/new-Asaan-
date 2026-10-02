<?php

namespace Cartxis\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Cartxis\Core\Models\Currency;
use Cartxis\Core\Support\DisplayCurrency;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

/**
 * Lets a shopper choose which of the store's two currencies prices are shown
 * in.
 *
 * The choice is a session value, not a query string and not a setting on the
 * product, because a converted figure must never travel back to the server as
 * if it were a real price. Everything the shop stores, calculates and charges
 * stays in afghani; this only changes which label is drawn on top of it.
 */
class CurrencyController extends Controller
{
    /**
     * Switch the currency the shopper is browsing in.
     *
     * Anything other than AFN or USD is refused rather than ignored-and-accepted
     * with a 200: a stale bookmark or a hand-crafted request should be told it
     * was wrong, not quietly shown a currency the store does not sell in.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ]);

        $code = strtoupper(trim($validated['code']));

        if (! Currency::isSupportedCode($code)) {
            return $this->refuse($request, $code);
        }

        $currency = Currency::getByCode($code);

        if (! $currency || ! $currency->is_active) {
            return $this->refuse($request, $code);
        }

        DisplayCurrency::remember($currency->code);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'code' => $currency->code,
            ]);
        }

        return back();
    }

    /**
     * A currency the store does not offer, said plainly.
     *
     * Plain English rather than a translated string: the translation files are
     * owned by another agent and there is no existing key for this, so this
     * message would not be translated in Dari or Pashto until one is added.
     * Said in English rather than silently ignored, because a shopper who
     * picked something unsupported should be told why their choice did not
     * stick.
     */
    private function refuse(Request $request, string $code): RedirectResponse|JsonResponse
    {
        $supported = Currency::SUPPORTED_CODES;
        $message = "This store prices in AFN and USD. {$code} is not available.";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'supported' => $supported,
            ], 422);
        }

        return back()->with('error', $message);
    }
}
