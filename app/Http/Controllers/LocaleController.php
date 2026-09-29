<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LocaleController extends Controller
{
    /**
     * The codes the language switcher can select. Keep in sync with the
     * seeded locales (Dari, English, Pashto).
     */
    protected const SUPPORTED = ['fa', 'en', 'ps'];

    public function store(Request $request, string $locale)
    {
        $locale = in_array($locale, static::SUPPORTED, true)
            ? strtolower($locale)
            : (string) config('app.locale', 'fa');

        Cookie::queue('locale', $locale, 60 * 24 * 365 * 10);

        app()->setLocale($locale);

        return back();
    }
}