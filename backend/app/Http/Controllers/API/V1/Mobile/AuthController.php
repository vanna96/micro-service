<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Mail\CustomerVerificationCode;
use App\Models\Customer;
use App\Models\Notification;
use App\Services\FacebookLoginService;
use App\Services\GoogleLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    private const VERIFICATION_EXPIRES_MINUTES = 10;

    private const VERIFICATION_RESEND_SECONDS = 60;

    private const VERIFICATION_MAX_ATTEMPTS = 5;

    public function register(Request $request)
    {
        $input = $request->all();
        $input['phone'] = $this->normalizePhone((string) ($input['phone'] ?? ''));
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique($this->customersTable(), 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique($this->customersTable(), 'email')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['required', 'string', 'max:20', Rule::unique($this->customersTable(), 'phone')],
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
        $validated['code'] = $this->nextCustomerCode();
        $validated['type'] = Customer::TYPE_CUSTOMER;
        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = 'Active';

        $customer = DB::transaction(fn () => Customer::query()->create($validated));

        Customer::flushQueryCache();

        try {
            $this->sendVerificationCode($customer);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'code' => 'EMAIL_DELIVERY_FAILED',
                'message' => 'Your account was created, but the verification email could not be sent. Please try resending the code.',
                'data' => $this->verificationPayload($customer),
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'A verification code was sent to your email.',
            'data' => $this->verificationPayload($customer),
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

        $customer = $this->findCustomerByLogin((string) $request->input('username'));

        if (! $customer || ! $customer->password || ! Hash::check((string) $request->input('password'), $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password.',
            ], 401);
        }

        if ($customer->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'This customer account is inactive.',
            ], 403);
        }

        if (! $customer->email_verified_at) {
            if (! $this->hasActiveVerificationCode($customer)) {
                try {
                    $this->sendVerificationCode($customer);
                } catch (Throwable $exception) {
                    report($exception);

                    return response()->json([
                        'success' => false,
                        'code' => 'EMAIL_DELIVERY_FAILED',
                        'message' => 'Your email is not verified and a verification code could not be sent. Please try again.',
                        'data' => $this->verificationPayload($customer),
                    ], 503);
                }
            }

            return response()->json([
                'success' => false,
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Verify your email before signing in.',
                'data' => $this->verificationPayload($customer),
            ], 403);
        }

        $tokens = $this->issueMobileToken($customer, $request);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => $this->authPayload($customer, $tokens),
        ]);
    }

    public function facebookStart(Request $request, FacebookLoginService $facebook)
    {
        $tenant = tenant();
        abort_unless($tenant && $facebook->isConfigured($tenant), 503, 'Facebook sign-in is not configured for this store.');

        $state = Str::random(64);
        $redirectUri = $request->getSchemeAndHttpHost().'/api/shop/auth/facebook/callback';
        Cache::store('portal_sessions')->put(
            'facebook_oauth_state:'.hash('sha256', $state),
            [
                'tenant_id' => (string) $tenant->id,
                'redirect_uri' => $redirectUri,
                'return_to' => $this->safeFacebookReturnPath((string) $request->query('next', '/account')),
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'authorization_url' => $facebook->authorizationUrl($redirectUri, $state, $tenant),
            ],
        ]);
    }

    public function facebookCallback(Request $request, FacebookLoginService $facebook)
    {
        $validator = Validator::make($request->all(), [
            'state' => ['required', 'string', 'size:64'],
            'code' => ['required_without:error', 'nullable', 'string', 'max:2048'],
            'error' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The Facebook sign-in response is invalid or expired.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $state = (string) $request->query('state');
        $statePayload = Cache::store('portal_sessions')->pull(
            'facebook_oauth_state:'.hash('sha256', $state)
        );
        $tenant = tenant();

        if (! is_array($statePayload)
            || ! $tenant
            || ! hash_equals((string) ($statePayload['tenant_id'] ?? ''), (string) $tenant->id)
            || ! hash_equals(
                (string) ($statePayload['redirect_uri'] ?? ''),
                $request->getSchemeAndHttpHost().'/api/shop/auth/facebook/callback'
            )) {
            return response()->json([
                'success' => false,
                'message' => 'The Facebook sign-in request has expired. Please try again.',
            ], 419);
        }

        if ($request->filled('error')) {
            return response()->json([
                'success' => false,
                'message' => 'Facebook sign-in was cancelled.',
            ], 422);
        }

        try {
            $profile = $facebook->userFromCode(
                (string) $request->query('code'),
                (string) $statePayload['redirect_uri'],
                $tenant
            );

            $customer = DB::transaction(function () use ($profile): Customer {
                $customer = $this->customerQuery()
                    ->where('facebook_id', $profile['id'])
                    ->first();

                if (! $customer && $profile['email']) {
                    $customer = $this->customerQuery()
                        ->whereRaw('LOWER(email) = ?', [$profile['email']])
                        ->first();
                }

                if ($customer && filled($customer->facebook_id)
                    && ! hash_equals((string) $customer->facebook_id, $profile['id'])) {
                    throw ValidationException::withMessages([
                        'facebook' => 'This email is already linked to another Facebook account.',
                    ]);
                }

                if (! $customer) {
                    $customer = Customer::query()->create([
                        'code' => $this->nextCustomerCode(),
                        'type' => Customer::TYPE_CUSTOMER,
                        'name' => $profile['name'] ?: 'Facebook Customer',
                        'username' => $this->nextFacebookUsername($profile['id']),
                        'email' => $profile['email'],
                        'facebook_id' => $profile['id'],
                        'facebook_avatar_url' => $profile['avatar_url'],
                        'first_name' => $profile['first_name'] ?: null,
                        'last_name' => $profile['last_name'] ?: null,
                        'status' => 'Active',
                    ]);

                    $customer->forceFill([
                        'email_verified_at' => $profile['email'] ? now() : null,
                    ])->save();

                    Notification::query()->create([
                        'customer_id' => $customer->id,
                        'type' => 'Account',
                        'title' => 'Welcome',
                        'message' => 'Your Facebook customer account is ready to use.',
                        'data' => ['screen' => 'account'],
                    ]);
                } else {
                    $customer->forceFill([
                        'facebook_id' => $profile['id'],
                        'facebook_avatar_url' => $profile['avatar_url'] ?: $customer->facebook_avatar_url,
                        'email_verified_at' => $profile['email']
                            ? ($customer->email_verified_at ?: now())
                            : $customer->email_verified_at,
                    ])->save();
                }

                return $customer->fresh();
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Facebook sign-in could not be completed. Please try again.',
            ], 502);
        }

        Customer::flushQueryCache();

        if ($customer->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'This customer account is inactive.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Facebook sign-in successful.',
            'data' => array_merge(
                $this->authPayload($customer, $this->issueMobileToken($customer, $request)),
                ['redirect_to' => (string) ($statePayload['return_to'] ?? '/account')]
            ),
        ]);
    }
    public function googleStart(Request $request, GoogleLoginService $google)
    {
        $tenant = tenant();
        abort_unless($tenant && $google->isConfigured($tenant), 503, 'Google sign-in is not configured for this store.');

        $state = Str::random(64);
        $redirectUri = $request->getSchemeAndHttpHost().'/api/shop/auth/google/callback';
        Cache::store('portal_sessions')->put(
            'google_oauth_state:'.hash('sha256', $state),
            [
                'tenant_id' => (string) $tenant->id,
                'redirect_uri' => $redirectUri,
                'return_to' => $this->safeFacebookReturnPath((string) $request->query('next', '/account')),
            ],
            now()->addMinutes(10)
        );

        return response()->json([
            'success' => true,
            'data' => [
                'authorization_url' => $google->authorizationUrl($redirectUri, $state, $tenant),
            ],
        ]);
    }

    public function googleCallback(Request $request, GoogleLoginService $google)
    {
        $validator = Validator::make($request->all(), [
            'state' => ['required', 'string', 'size:64'],
            'code' => ['required_without:error', 'nullable', 'string', 'max:2048'],
            'error' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The Google sign-in response is invalid or expired.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $state = (string) $request->query('state');
        $statePayload = Cache::store('portal_sessions')->pull(
            'google_oauth_state:'.hash('sha256', $state)
        );
        $tenant = tenant();

        if (! is_array($statePayload)
            || ! $tenant
            || ! hash_equals((string) ($statePayload['tenant_id'] ?? ''), (string) $tenant->id)
            || ! hash_equals(
                (string) ($statePayload['redirect_uri'] ?? ''),
                $request->getSchemeAndHttpHost().'/api/shop/auth/google/callback'
            )) {
            return response()->json([
                'success' => false,
                'message' => 'The Google sign-in request has expired. Please try again.',
            ], 419);
        }

        if ($request->filled('error')) {
            return response()->json([
                'success' => false,
                'message' => 'Google sign-in was cancelled.',
            ], 422);
        }

        try {
            $profile = $google->userFromCode(
                (string) $request->query('code'),
                (string) $statePayload['redirect_uri'],
                $tenant
            );

            $customer = DB::transaction(function () use ($profile): Customer {
                $customer = $this->customerQuery()
                    ->where('google_id', $profile['id'])
                    ->first();

                if (! $customer && $profile['email']) {
                    $customer = $this->customerQuery()
                        ->whereRaw('LOWER(email) = ?', [$profile['email']])
                        ->first();
                }

                if ($customer && filled($customer->google_id)
                    && ! hash_equals((string) $customer->google_id, $profile['id'])) {
                    throw ValidationException::withMessages([
                        'google' => 'This email is already linked to another Google account.',
                    ]);
                }

                if (! $customer) {
                    $customer = Customer::query()->create([
                        'code' => $this->nextCustomerCode(),
                        'type' => Customer::TYPE_CUSTOMER,
                        'name' => $profile['name'] ?: 'Google Customer',
                        'username' => 'google_'.$profile['id'],
                        'email' => $profile['email'],
                        'google_id' => $profile['id'],
                        'google_avatar_url' => $profile['avatar_url'],
                        'first_name' => $profile['first_name'] ?: null,
                        'last_name' => $profile['last_name'] ?: null,
                        'status' => 'Active',
                    ]);

                    $customer->forceFill([
                        'email_verified_at' => $profile['email'] ? now() : null,
                    ])->save();

                    Notification::query()->create([
                        'customer_id' => $customer->id,
                        'type' => 'Account',
                        'title' => 'Welcome',
                        'message' => 'Your Google customer account is ready to use.',
                        'data' => ['screen' => 'account'],
                    ]);
                } else {
                    $customer->forceFill([
                        'google_id' => $profile['id'],
                        'google_avatar_url' => $profile['avatar_url'] ?: $customer->google_avatar_url,
                        'email_verified_at' => $profile['email']
                            ? ($customer->email_verified_at ?: now())
                            : $customer->email_verified_at,
                    ])->save();
                }

                return $customer->fresh();
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Google sign-in could not be completed. Please try again.',
            ], 502);
        }

        Customer::flushQueryCache();

        if ($customer->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'This customer account is inactive.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Google sign-in successful.',
            'data' => array_merge(
                $this->authPayload($customer, $this->issueMobileToken($customer, $request)),
                ['redirect_to' => (string) ($statePayload['return_to'] ?? '/account')]
            ),
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = mb_strtolower(trim((string) $request->input('email')));
        $customer = $this->customerQuery()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $customer) {
            return $this->invalidVerificationCode();
        }

        if ($customer->email_verified_at) {
            return response()->json([
                'success' => false,
                'code' => 'EMAIL_ALREADY_VERIFIED',
                'message' => 'This email is already verified. Please sign in.',
            ], 409);
        }

        if (! $this->hasActiveVerificationCode($customer)) {
            return response()->json([
                'success' => false,
                'code' => 'VERIFICATION_CODE_EXPIRED',
                'message' => 'The verification code has expired. Request a new code.',
            ], 422);
        }

        if (! Hash::check((string) $request->input('code'), (string) $customer->email_verification_code_hash)) {
            $attempts = ((int) $customer->email_verification_attempts) + 1;
            $updates = ['email_verification_attempts' => $attempts];

            if ($attempts >= self::VERIFICATION_MAX_ATTEMPTS) {
                $updates = array_merge($updates, [
                    'email_verification_code_hash' => null,
                    'email_verification_expires_at' => null,
                ]);
            }

            $customer->forceFill($updates)->save();
            Customer::flushQueryCache();

            if ($attempts >= self::VERIFICATION_MAX_ATTEMPTS) {
                return response()->json([
                    'success' => false,
                    'code' => 'VERIFICATION_ATTEMPTS_EXCEEDED',
                    'message' => 'Too many incorrect attempts. Request a new code.',
                ], 429);
            }

            return $this->invalidVerificationCode();
        }

        DB::transaction(function () use ($customer) {
            $customer->forceFill([
                'email_verified_at' => now(),
                'email_verification_code_hash' => null,
                'email_verification_expires_at' => null,
                'email_verification_sent_at' => null,
                'email_verification_attempts' => 0,
            ])->save();

            Notification::query()->create([
                'customer_id' => $customer->id,
                'type' => 'Account',
                'title' => 'Welcome',
                'message' => 'Your customer account is ready to use.',
                'data' => ['screen' => 'account'],
            ]);
        });

        Customer::flushQueryCache();
        $tokens = $this->issueMobileToken($customer->fresh(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => $this->authPayload($customer->fresh(), $tokens),
        ]);
    }

    public function resendVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = mb_strtolower(trim((string) $request->input('email')));
        $customer = $this->customerQuery()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $customer || $customer->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'If this email has an unverified account, a new code was sent.',
            ]);
        }

        if ($customer->email_verification_sent_at) {
            $availableAt = $customer->email_verification_sent_at->copy()->addSeconds(self::VERIFICATION_RESEND_SECONDS);

            if ($availableAt->isFuture()) {
                return response()->json([
                    'success' => false,
                    'code' => 'VERIFICATION_RESEND_THROTTLED',
                    'message' => 'Please wait before requesting another code.',
                    'retry_after' => max(1, (int) now()->diffInSeconds($availableAt)),
                ], 429);
            }
        }

        try {
            $this->sendVerificationCode($customer);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'code' => 'EMAIL_DELIVERY_FAILED',
                'message' => 'The verification email could not be sent. Please try again.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new verification code was sent.',
            'data' => $this->verificationPayload($customer->fresh()),
        ]);
    }

    public function refresh(Request $request)
    {
        $plainTextToken = $request->input('refresh_token') ?: $request->bearerToken();

        if (! $plainTextToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is required.',
            ], 422);
        }

        $token = $this->findPersonalAccessToken((string) $plainTextToken);

        if (! $token
            || ($token->expires_at && $token->expires_at->isPast())
            || $token->name !== 'customer-refresh'
            || ! $token->can('issue-token')
            || $token->tokenable_type !== Customer::class) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token. Please sign in again.',
            ], 401);
        }

        $customer = $this->customerQuery()->find($token->tokenable_id);

        if (! $customer || $customer->status !== 'Active' || ! $customer->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Customer account is inactive or not found.',
            ], 401);
        }

        $token->delete();
        $tokens = $this->issueMobileToken($customer, $request);

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'data' => $this->authPayload($customer, $tokens),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->mobileUserPayload($this->currentCustomer($request)),
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

    private function authPayload(Customer $customer, array $tokens): array
    {
        return array_merge([
            'user' => $this->mobileUserPayload($customer),
        ], $tokens, [
            'tenant' => tenant('id'),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        return ltrim((string) preg_replace('/\D+/', '', $phone), '0');
    }

    private function nextFacebookUsername(string $facebookId): string
    {
        $base = 'facebook_'.preg_replace('/\D+/', '', $facebookId);
        $username = $base;
        $suffix = 1;

        while (Customer::query()->where('username', $username)->exists()) {
            $username = $base.'_'.++$suffix;
        }

        return $username;
    }

    private function safeFacebookReturnPath(string $path): string
    {
        return preg_match('#^/(?!/)#', $path) ? $path : '/account';
    }

    private function sendVerificationCode(Customer $customer): void
    {
        $code = (string) random_int(100000, 999999);

        $customer->forceFill([
            'email_verification_code_hash' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(self::VERIFICATION_EXPIRES_MINUTES),
            'email_verification_sent_at' => now(),
            'email_verification_attempts' => 0,
        ])->save();
        Customer::flushQueryCache();

        try {
            if ($tenant = tenant()) {
                if (app()->bound(\App\Services\MailNotificationService::class)) {
                    app(\App\Services\MailNotificationService::class)->configureMailerForTenant($tenant);
                }
            }
            $settings = is_array(tenant()?->general_settings) ? tenant()->general_settings : [];
            $storeName = (string) ($settings['store_name'] ?? tenant()?->alias ?? config('app.name'));
            $fromAddress = (string) ($settings['mail_from_address'] ?? config('mail.from.address'));
            $fromName = (string) ($settings['mail_from_name'] ?? $storeName);

            Mail::to((string) $customer->email)->queue(new CustomerVerificationCode(
                code: $code,
                storeName: $storeName,
                expiresInMinutes: self::VERIFICATION_EXPIRES_MINUTES,
                tenantId: $tenant ? (string) $tenant->id : null,
                fromAddress: $fromAddress,
                fromName: $fromName,
            ));
        } catch (Throwable $exception) {
            $customer->forceFill([
                'email_verification_code_hash' => null,
                'email_verification_expires_at' => null,
                'email_verification_sent_at' => null,
                'email_verification_attempts' => 0,
            ])->save();
            Customer::flushQueryCache();

            throw $exception;
        }
    }

    private function hasActiveVerificationCode(Customer $customer): bool
    {
        return filled($customer->email_verification_code_hash)
            && $customer->email_verification_expires_at
            && $customer->email_verification_expires_at->isFuture()
            && (int) $customer->email_verification_attempts < self::VERIFICATION_MAX_ATTEMPTS;
    }

    private function verificationPayload(Customer $customer): array
    {
        return [
            'requires_verification' => true,
            'email' => (string) $customer->email,
            'masked_email' => $this->maskEmail((string) $customer->email),
            'expires_in' => self::VERIFICATION_EXPIRES_MINUTES * 60,
        ];
    }

    private function invalidVerificationCode()
    {
        return response()->json([
            'success' => false,
            'code' => 'INVALID_VERIFICATION_CODE',
            'message' => 'The verification code is incorrect.',
        ], 422);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible.str_repeat('*', max(2, mb_strlen($local) - mb_strlen($visible))).'@'.$domain;
    }
}
