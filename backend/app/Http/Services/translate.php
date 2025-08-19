<?php

use Illuminate\Support\Facades\Http;

if (! function_exists('translate')) {
    function translate($errors, $lng = 'en')
    {
        if(!in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')))) return $errors;
        $response = Http::post('http://host.docker.internal:8883/api/translate', [
            'data' => $errors,
            'lng' => request('lng')//$lng,
        ]);

        if ($response->successful()) {
            $translatedErrors = $response->json();
            return $translatedErrors;
        } else{
            return $errors;
        }
    }
}
