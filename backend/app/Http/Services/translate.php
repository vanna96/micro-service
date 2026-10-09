<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

if (! function_exists('normalize_locale_code')) {
    function normalize_locale_code(?string $locale): string
    {
        if (!$locale) {
            return 'en';
        }
        $code = strtolower(trim($locale));
        $code = explode('-', $code)[0];
        $code = explode('_', $code)[0];
        $code = explode(',', $code)[0];
        $code = explode(';', $code)[0];

        if ($code === 'km') {
            return 'kh';
        }

        return $code;
    }
}

if (! function_exists('translate')) {
    /**
     * Translate a string or array into the target language.
     *
     * @param  mixed  $data
     * @param  string|null  $lng
     * @return mixed
     */
    function translate($data, $lng = null)
    {
        // 1. Resolve requested target language
        $targetLng = null;
        if ($lng !== null && is_string($lng) && trim($lng) !== '') {
            $targetLng = trim($lng);
        } else {
            $targetLng = request('lng')
                ?? request('locale')
                ?? request('lang')
                ?? request()->header('X-Locale')
                ?? request()->header('X-Language')
                ?? (request()->hasSession() ? request()->session()->get('locale') : null)
                ?? request()->cookie('locale')
                ?? app()->getLocale()
                ?? request()->header('Accept-Language')
                ?? 'en';
        }

        $targetLng = normalize_locale_code((string) $targetLng);

        // If target language is English or empty, return original data
        if ($targetLng === 'en' || empty($targetLng)) {
            return $data;
        }

        // Check if language is allowed
        $allowed = array_map('trim', explode(',', env('LNG_ALLOWED', 'en,kh,km')));
        $allowed = array_map('normalize_locale_code', $allowed);

        if (!in_array($targetLng, $allowed, true)) {
            return $data;
        }

        // Handle array recursion (e.g. validation errors or nested data)
        if (is_array($data)) {
            $translated = [];
            foreach ($data as $key => $value) {
                $translated[$key] = translate($value, $targetLng);
            }
            return $translated;
        }

        if (!is_string($data) || trim($data) === '') {
            return $data;
        }

        $trimmed = trim($data);

        // 2. Level 1: Fast local dictionary lookup via Laravel translation system
        // Checks lang/kh.json, lang/km.json, lang/kh/*.php
        $localTranslated = __($trimmed, [], $targetLng);
        if ($localTranslated !== $trimmed && !empty($localTranslated)) {
            return $localTranslated;
        }

        // Also check alternate locale code ('kh' vs 'km')
        $alternateLng = in_array($targetLng, ['kh', 'km'], true)
            ? ($targetLng === 'kh' ? 'km' : 'kh')
            : null;
        if ($alternateLng) {
            $altTranslated = __($trimmed, [], $alternateLng);
            if ($altTranslated !== $trimmed && !empty($altTranslated)) {
                return $altTranslated;
            }
        }

        // 3. Level 2: Cached dynamic translation
        $cacheKey = 'vpos_trans_' . $targetLng . '_' . md5($trimmed);
        $translationCache = Cache::store('translations');
        $cached = $translationCache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // 4. Level 3: External Translation Microservice (FastAPI / deep-translator)
        try {
            $serviceUrl = env('TRANSLATION_SERVICE_URL', 'http://fastapi-app:8000/api/translate');

            // External translation engine uses 'km' for Khmer
            $apiTargetLng = in_array($targetLng, ['kh', 'km'], true) ? 'km' : $targetLng;

            $response = Http::timeout(3)
                ->asJson()
                ->acceptJson()
                ->post($serviceUrl, [
                    'data' => $trimmed,
                    'lng'  => $apiTargetLng,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                if (is_string($result) && !empty($result) && $result !== $trimmed) {
                    $translationCache->put($cacheKey, $result, now()->addDays(7));
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            // Log quietly and fall back to original text without breaking request
            Log::debug('Translation microservice skipped: ' . $e->getMessage());
        }

        return $data;
    }
}
