<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the language for the request from the enabled locales (config/locales.php):
 * website — ?lang=, then the visitor's earlier choice (session), then the signed-in customer's
 * saved language, else English; API — ?lang=, then Accept-Language.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $enabled = self::enabled();
        $pick = fn (?string $l) => $l && in_array($l, $enabled, true) ? $l : null;
        $wanted = $pick($request->query('lang'));
        if ($request->is('admin', 'admin/*', 'staff/*')) {
            $locale = 'en';                                  // the control centre is English
        } elseif ($request->is('api/*')) {
            // ?lang, then the app's Accept-Language, then the signed-in customer's saved language
            $header = $request->headers->has('Accept-Language') ? $pick(substr((string) $request->getPreferredLanguage($enabled), 0, 2)) : null;
            $locale = $wanted ?? $header ?? $pick(auth('sanctum')->user()?->locale) ?? 'en';
        } else {
            $session = $request->hasSession() ? $request->session() : null;
            if ($wanted && $session && ! $request->is('rashifal*', 'rashi/*', 'gochar*')) {
                $session->put('locale', $wanted);       // the Rashifal pages switch only their own content language
            }
            $user = $request->user();
            $locale = $wanted ?? $pick($session?->get('locale')) ?? ($user && ! $user->isStaff() ? $pick($user->locale) : null) ?? 'en';
        }
        App::setLocale($locale);

        return $next($request);
    }

    /** @return list<string> */
    public static function enabled(): array
    {
        $all = array_keys(config('locales.available'));
        $on = array_values(array_intersect(config('locales.enabled') ?: ['en'], $all));

        return in_array('en', $on, true) ? $on : ['en', ...$on];
    }
}
