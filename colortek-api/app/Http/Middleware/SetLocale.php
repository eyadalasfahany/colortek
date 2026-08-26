<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    private const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->getPreferredLanguage(self::SUPPORTED)
            ?? config('app.locale', 'en');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
