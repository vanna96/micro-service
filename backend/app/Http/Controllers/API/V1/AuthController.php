<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

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
            $message = formatValidationErrors($validator->errors()->toArray());
            if (in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')))) $message = translate($message, request('lng'));
            return response()->json($message, 422);
        }

        $validated = $validator->validated();
        $validated['password'] = \Hash::make($validated['password']);
        try {
            $user = User::create($validated);
            return response()->json([
                'success' => true,
                'data'    => $user
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => translate($th->getMessage(), request('lng'))
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'username'      => 'required|string|max:255',
            'password'      => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            $message = formatValidationErrors($validator->errors()->toArray());
            if (in_array(request('lng'), explode(',', env('LNG_ALLOWED', 'en')))) $message = translate($message, request('lng'));
            return response()->json($message, 422);
        }

        $usernameInput = $request->username;
        $credentials = ['password' => $request->password];

        if (filter_var($usernameInput, FILTER_VALIDATE_EMAIL)) {
            $credentials['email'] = $usernameInput;
        } elseif (preg_match('/^\+?[0-9]{8,15}$/', $usernameInput)) {
            $credentials['phone'] = $usernameInput;
        } else {
            $credentials['username'] = $usernameInput;
        }

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => translate('Invalid username or password', request('lng'))
            ], 401);
        }

        $user = Auth::user();
        // $token = $user->createToken('authToken')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => translate('Login successful', request('lng')),
            'data' => [
                'user' => $user,
                // 'token' => $token
            ]
        ], 200);
    }

    public function auth(Request $request)
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => translate('Logged out')], 200);
    }
}
