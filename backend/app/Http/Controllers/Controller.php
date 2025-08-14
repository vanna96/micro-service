<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function customValidation($validator)
    {
        $exception = new ValidationException($validator);
        $errors = $validator->errors()->toArray();
        try {
            $response = Http::post('http://host.docker.internal:8883/api/translate', [
                'data' => formatValidationErrors($errors),
                'lng' => request('lng'),
            ]);

            if ($response->successful()) {
                $translatedErrors = $response->json();
                return response()->json($translatedErrors, $exception->status);
            } else{
                return response()->json($errors, $exception->status);
            }

        } catch (\Exception $e)
        {
            return response()->json($errors, $exception->status);
        }
    }
}
