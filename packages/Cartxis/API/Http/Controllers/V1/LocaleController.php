<?php

namespace Cartxis\API\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Cartxis\API\Helpers\ApiResponse;
use Cartxis\Core\Models\Locale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

/**
 * The languages the app may offer.
 *
 * Public on purpose: the language picker is on the sign-in screen, before
 * there is anybody to authenticate.
 *
 * The list comes from the locales table, which is what the admin edits, but a
 * code is only offered when the app also ships a dictionary for it. A row
 * switched on with no translation behind it would put a menu of English keys
 * on screen for a Dari speaker, which is worse than not offering the language
 * at all.
 */
class LocaleController extends Controller
{
    public function index(): JsonResponse
    {
        $shipped = $this->shippedCodes();

        $locales = Locale::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Locale $locale) => in_array($locale->code, $shipped, true))
            ->map(fn (Locale $locale) => [
                'code' => $locale->code,
                'name' => $locale->name,
                'native_name' => $locale->native_name,
                'direction' => $locale->direction,
                'is_default' => (bool) $locale->is_default,
            ])
            ->values();

        // A store with no locale rows yet still has to answer, or the app has
        // no way to show a language picker at all.
        if ($locales->isEmpty()) {
            $locales = collect(array_map(
                static fn (array $row) => $row + ['is_default' => $row['code'] === $this->defaultCode()],
                [
                    ['code' => 'fa', 'name' => 'Dari', 'native_name' => 'دری', 'direction' => 'rtl'],
                    ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr'],
                    ['code' => 'ps', 'name' => 'Pashto', 'native_name' => 'پښتو', 'direction' => 'rtl'],
                ],
            ));
        }

        return ApiResponse::success($locales, 'Locales retrieved successfully');
    }

    /**
     * The language codes that actually have a dictionary in this build.
     *
     * @return array<int, string>
     */
    protected function shippedCodes(): array
    {
        $codes = [];

        foreach (['fa', 'en', 'ps'] as $code) {
            if (File::exists(lang_path($code . '.json'))) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    protected function defaultCode(): string
    {
        $default = Locale::getDefault();

        return $default?->code ?? (string) config('app.locale', 'fa');
    }
}
