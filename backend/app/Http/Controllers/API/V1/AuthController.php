<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        $input = $request->all();
        $country = country_code()[$request->input('country_code') ?? '855'] ?? 'KH';

        if (!empty($input['phone']))  $input['phone'] = ltrim($input['phone'], '0');
        $validator = Validator::make($input, [
            'name'          => 'required|string|max:255',
            'username'      => 'required|string|max:255|unique:users,username',
            'email'         => 'nullable|string|email|unique:users,email',
            'password'      => 'required|string|min:6|confirmed',
            'first_name'    => 'nullable|string|max:255',
            'last_name'     => 'nullable|string|max:255',
            'country_code'  => 'nullable|string|max:5',
            'phone'         => "required|phone:{$country}|unique:users,phone",
            'gender'        => 'nullable|in:Male,Female',
            'dob'           => 'nullable|date'
        ]);

        if ($validator->fails()) {
            if (in_array(request('lng'), ['km'])) return $this->customValidation($validator);
            return response()->json(formatValidationErrors($validator->errors()->toArray()), 422);
        }

        $validated = $validator->validated();
        dd('done', $validated);
    }
}   
