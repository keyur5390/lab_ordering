<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Languages (B-32): the language the visitor chose (this session, else their account), else the
 * best match for their browser, else the default. Staff screens are always English.
 */
class SetLocale
{
    public const SESSION_KEY = 'locale';

    /** Admin (including the lab) and collector screens. */
    public const STAFF_ROUTES = ['admin.*', 'collector.*'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(self::resolve($request));

        return $next($request);
    }

    /**
     * The `i18n` prop pages get (and error pages, which render without the shared props): the
     * language, the choices (none on staff screens) and, other than for English, the dictionary
     * from lang/{locale}.json that __() also uses.
     *
     * @return array{locale: string, locales: list<array{code: string, label: string}>, messages: array<string, string>|null}
     */
    public static function shared(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'locale' => $locale,
            'locales' => $request->routeIs(...self::STAFF_ROUTES)
                ? []
                : collect(config('app.locales'))->map(fn (string $label, string $code) => ['code' => $code, 'label' => $label])->values()->all(),
            'messages' => $locale === config('app.fallback_locale') ? null : app('translator')->getLoader()->load($locale, '*', '*'),
        ];
    }

    public static function supported(): array
    {
        return array_keys(config('app.locales'));
    }

    public static function resolve(Request $request): string
    {
        // Not app.locale: setLocale() overwrites that with the current request's language.
        $default = config('app.fallback_locale');
        if ($request->routeIs(...self::STAFF_ROUTES)) {
            return $default;
        }

        $supported = self::supported();
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        $chosen ??= $request->user()?->locale;

        if (is_string($chosen) && in_array($chosen, $supported, true)) {
            return $chosen;
        }

        return $request->getPreferredLanguage($supported) ?? $default;
    }
}
