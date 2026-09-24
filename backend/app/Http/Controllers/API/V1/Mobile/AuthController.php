<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique($this->centralUsersTable(), 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique($this->centralUsersTable(), 'email')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['required', 'string', 'max:30', Rule::unique($this->centralUsersTable(), 'phone')],
            'gender' => ['nullable', Rule::in(['Male', 'Female'])],
            'dob' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = 'Active';

        $centralUser = DB::connection('central')->transaction(function () use ($validated) {
            $user = $this->centralUserModel();
            $user->fill($validated);
            $user->save();
            $this->attachUserToCurrentTenant($user);

            return $user;
        });

        $this->ensureTenantUserMirror($centralUser);
        $token = $this->issueMobileToken($centralUser, $request);

        Notification::query()->create([
            'user_id' => $centralUser->id,
            'type' => 'Account',
            'title' => 'Welcome',
            'message' => 'Your mobile account is ready to use.',
            'data' => [
                'screen' => 'account',
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mobile account created successfully.',
            'data' => [
                'user'            => $this->mobileUserPayload($centralUser),
                'token'           => $token['token'],
                'access_token'    => $token['access_token'],
                'refresh_token'   => $token['refresh_token'],
                'token_type'      => $token['token_type'],
                'expires_in'      => $token['expires_in'],
                'timeout'         => $token['timeout'],
                'timeout_minutes' => $token['timeout_minutes'],
                'tenant'          => tenant('id'),
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $usernameInput = trim((string) $request->input('username'));
        $centralUser = $this->findCentralUserByLogin($usernameInput);
        $password = (string) $request->input('password');

        if (! $centralUser || ! Hash::check($password, $centralUser->password)) {
            $tenantUser = $this->findTenantUserByLogin($usernameInput);

            if (
                $tenantUser instanceof \App\Models\User &&
                Hash::check($password, $tenantUser->password)
            ) {
                $centralUser = $this->synchronizeTenantUserToCentral($tenantUser);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid username or password.',
                ], 401);
            }
        }

        if (($centralUser->status ?? 'Inactive') !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'This account is inactive.',
            ], 403);
        }

        $this->ensureTenantAccess($centralUser);
        $this->ensureTenantUserMirror($centralUser);
        $token = $this->issueMobileToken($centralUser, $request);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user'            => $this->mobileUserPayload($centralUser),
                'token'           => $token['token'],
                'access_token'    => $token['access_token'],
                'refresh_token'   => $token['refresh_token'],
                'token_type'      => $token['token_type'],
                'expires_in'      => $token['expires_in'],
                'timeout'         => $token['timeout'],
                'timeout_minutes' => $token['timeout_minutes'],
                'tenant'          => tenant('id'),
            ],
        ]);
    }

    public function refresh(Request $request)
    {
        $refreshTokenString = $request->input('refresh_token') ?: $request->bearerToken();

        if (! $refreshTokenString) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is required.',
            ], 422);
        }

        $token = $this->findPersonalAccessToken($refreshTokenString);

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token. Please sign in again.',
            ], 401);
        }

        if ($token->name !== 'mobile-refresh' || ! $token->can('issue-token')) {
            return response()->json([
                'success' => false,
                'message' => 'Provided token is not a valid refresh token.',
            ], 401);
        }

        $centralUser = $this->centralUserQuery()->find($token->tokenable_id) ?: $token->tokenable;

        if (! $centralUser || ($centralUser->status ?? 'Inactive') !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'User account is inactive or not found.',
            ], 401);
        }

        $this->ensureTenantAccess($centralUser);

        // Revoke the old refresh token (refresh token rotation)
        $token->delete();

        $tokenData = $this->issueMobileToken($centralUser, $request);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'data' => [
                'user'            => $this->mobileUserPayload($centralUser),
                'token'           => $tokenData['token'],
                'access_token'    => $tokenData['access_token'],
                'refresh_token'   => $tokenData['refresh_token'],
                'token_type'      => $tokenData['token_type'],
                'expires_in'      => $tokenData['expires_in'],
                'timeout'         => $tokenData['timeout'],
                'timeout_minutes' => $tokenData['timeout_minutes'],
                'tenant'          => tenant('id'),
            ],
        ]);
    }

    public function me(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);
        $this->ensureTenantUserMirror($centralUser);

        return response()->json([
            'success' => true,
            'data' => $this->mobileUserPayload($centralUser),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
