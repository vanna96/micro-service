<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported application locales.
     *
     * @var array<string>
     */
    protected array $supportedLocales = ['en', 'kh', 'km'];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        app()->setLocale($locale);

        $response = $next($request);

        if ($response instanceof Response) {
            $response->headers->set('Content-Language', $locale);
            $response->headers->set('X-Locale', $locale);
        }

        return $response;
    }

    /**
     * Determine the preferred locale for the request.
     */
    protected function determineLocale(Request $request): string
    {
        // 1. Explicit query or request parameter (e.g. ?lng=kh or ?locale=kh or ?lang=kh)
        $param = $request->query('lng')
            ?? $request->query('locale')
            ?? $request->query('lang')
            ?? $request->input('lng')
            ?? $request->input('locale')
            ?? $request->input('lang');

        if ($param) {
            $normalized = $this->normalizeLocale((string) $param);
            if (in_array($normalized, $this->supportedLocales, true)) {
                return $normalized;
            }
        }

        // 2. Custom header X-Locale or X-Language
        $headerLocale = $request->header('X-Locale') ?? $request->header('X-Language');
        if ($headerLocale) {
            $normalized = $this->normalizeLocale($headerLocale);
            if (in_array($normalized, $this->supportedLocales, true)) {
                return $normalized;
            }
        }

        // 3. User Session (user explicitly switched language in admin/web)
        if ($request->hasSession() && $request->session()->has('locale')) {
            $normalized = $this->normalizeLocale((string) $request->session()->get('locale'));
            if (in_array($normalized, $this->supportedLocales, true)) {
                return $normalized;
            }
        }

        // 4. Stored Cookie
        if ($request->cookies->has('locale')) {
            $normalized = $this->normalizeLocale((string) $request->cookies->get('locale'));
            if (in_array($normalized, $this->supportedLocales, true)) {
                return $normalized;
            }
        }

        // 5. Accept-Language header (browser default fallback)
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $languages = explode(',', $acceptLanguage);
            foreach ($languages as $lang) {
                $clean = trim(explode(';', $lang)[0]);
                $normalized = $this->normalizeLocale($clean);
                if (in_array($normalized, $this->supportedLocales, true)) {
                    return $normalized;
                }
            }
        }

        // 6. Default fallback from config
        return config('app.locale', 'en');
    }

    /**
     * Normalize locale strings (e.g. km-KH -> kh, KM -> kh, en-US -> en).
     */
    protected function normalizeLocale(string $locale): string
    {
        $code = strtolower(trim($locale));
        $code = explode('-', $code)[0];
        $code = explode('_', $code)[0];

        // Map km to kh as the primary Khmer locale identifier
        if ($code === 'km') {
            return 'kh';
        }

        return $code;
    }
}
