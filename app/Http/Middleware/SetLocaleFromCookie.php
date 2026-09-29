<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromCookie
{
    /**
     * The locale codes the language switcher supports (Dari, English, Pashto).
     */
    protected const SUPPORTED = ['fa', 'en', 'ps'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        if (is_string($locale) && in_array(strtolower($locale), static::SUPPORTED, true)) {
            app()->setLocale(strtolower($locale));
        } else {
            app()->setLocale((string) config('app.locale', 'fa'));
        }

        return $next($request);
    }
}