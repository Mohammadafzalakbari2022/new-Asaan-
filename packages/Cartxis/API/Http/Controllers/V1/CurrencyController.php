<?php

namespace Cartxis\API\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Cartxis\API\Helpers\ApiResponse;
use Cartxis\Core\Models\Currency;

class CurrencyController extends Controller
{
    /**
     * Get default currency for the application.
     */
    public function default()
    {
        $currency = Currency::getDefault();

        if (!$currency) {
            return ApiResponse::error('Default currency not configured', null, 500, 'CURRENCY_NOT_CONFIGURED');
        }

        return ApiResponse::success(
            $this->present($currency),
            'Default currency retrieved successfully'
        );
    }

    /**
     * Get all currencies a shopper may choose.
     *
     * The store trades in AFN and offers USD. Every other row left over from
     * the old country-derived seeder is switched off, and this is the query
     * that keeps it that way from the app's side too.
     */
    public function index()
    {
        $currencies = Currency::selectable()
            ->get()
            ->map(fn (Currency $currency) => $this->present($currency))
            ->values();

        return ApiResponse::success($currencies, 'Currencies retrieved successfully');
    }

    /**
     * One currency as the app should read it.
     *
     * The keys are the real column names on the currencies table --
     * decimal_places and symbol_position -- so the app's CurrencyModel and the
     * database never drift apart. There is no decimal_separator or
     * thousands_separator here because the currencies table has never had those
     * columns and reading them is what made this endpoint return nulls.
     *
     * @return array<string, mixed>
     */
    private function present(Currency $currency): array
    {
        return $currency->toDisplayArray();
    }
}
