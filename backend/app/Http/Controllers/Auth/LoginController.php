<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\SecurityLog;
use App\Models\SecuritySetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantActivityLogger;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin/dashboard';

    public function showAdminLoginForm()
    {
        return redirect('/');
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function username()
    {
        return 'username';
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            'login_scope' => ['nullable', 'string', Rule::in(['administrator', 'tenant'])],
            'tenant_code' => [
                Rule::requiredIf(fn () => $request->input('login_scope') === 'tenant'),
                'nullable',
                'string',
                Rule::exists('tenants', 'id')->where(function ($query) {
                    $query->where('status', 'Active');
                }),
            ],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'tenant_code.required' => __('Tenant code is required for tenant user login.'),
            'tenant_code.exists' => __('The selected tenant code is not active or does not exist.'),
        ]);
    }

    protected function attemptLogin(Request $request)
    {
        $password = (string) $request->input('password');
        $remember = $request->boolean('remember');
        $loginScope = (string) $request->input('login_scope');

        if ($loginScope === 'administrator') {
            return $this->attemptAdministratorLogin($request, $password, $remember);
        }

        if ($loginScope === 'tenant') {
            return $this->attemptTenantUserLoginForCode($request, $password, $remember);
        }

        if ($this->attemptAdministratorLogin($request, $password, $remember)) {
            return true;
        }

        if ($this->attemptTenantUserLogin($request, $password, $remember)) {
            return true;
        }

        return false;
    }

    protected function attemptAdministratorLogin(Request $request, string $password, bool $remember): bool
    {
        foreach ($this->credentialCandidates((string) $request->input('username'), $password) as $credentials) {
            if ($this->guard()->attempt($credentials, $remember)) {
                if (strcasecmp((string) optional($this->guard()->user())->status, 'Active') !== 0) {
                    $this->guard()->logout();

                    return false;
                }

                $request->session()->put('auth_user_scope', 'administrator');
                $request->session()->forget([
                    'auth_tenant_id',
                    'admin_selected_tenant_id',
                ]);
                cookie()->queue(cookie()->forget('auth_remember_tenant_id'));

                return true;
            }
        }

        return false;
    }

    protected function attemptTenantUserLogin(Request $request, string $password, bool $remember): bool
    {
        $login = (string) $request->input('username');
        $tenants = $this->activeTenants()->get();

        foreach ($tenants as $tenant) {
            if ($this->attemptTenantLoginForTenant($request, $tenant, $login, $password, $remember)) {
                return true;
            }
        }

        return false;
    }

    protected function attemptTenantUserLoginForCode(Request $request, string $password, bool $remember): bool
    {
        $tenant = $this->activeTenants()
            ->whereKey((string) $request->input('tenant_code'))
            ->first();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $this->attemptTenantLoginForTenant(
            $request,
            $tenant,
            (string) $request->input('username'),
            $password,
            $remember
        );
    }

    protected function attemptTenantLoginForTenant(
        Request $request,
        Tenant $tenant,
        string $login,
        string $password,
        bool $remember
    ): bool {
        tenancy()->initialize($tenant);

        $tenantUser = $this->findMatchingTenantUser($login, $password);

        if (! $tenantUser instanceof User) {
            tenancy()->end();

            return false;
        }

        $request->session()->put([
            'auth_user_scope' => 'tenant',
            'auth_tenant_id' => $tenant->id,
            'admin_selected_tenant_id' => $tenant->id,
        ]);
        $this->guard()->login($tenantUser, $remember);

        if ($remember) {
            cookie()->queue(cookie()->forever('auth_remember_tenant_id', (string) $tenant->id));
        } else {
            cookie()->queue(cookie()->forget('auth_remember_tenant_id'));
        }

        app(TenantActivityLogger::class)->log(
            'tenant user logged in',
            [],
            $tenantUser,
            $tenantUser,
            'login',
            'authentication'
        );

        tenancy()->end();

        return true;
    }

    protected function findMatchingTenantUser(string $login, string $password): ?User
    {
        $connectionName = tenant() && filled(tenant()->database_connection_name)
            ? tenant()->database_connection_name
            : 'tenant';
        $userTable = (new User())->getTable();

        foreach ($this->credentialCandidates($login, $password) as $credentials) {
            $query = DB::connection($connectionName)->table($userTable);

            foreach ($credentials as $field => $value) {
                if ($field === 'password') {
                    continue;
                }

                $query->where($field, $value);
            }

            $user = $query->first();

            if (! $user) {
                continue;
            }

            if (strcasecmp((string) $user->status, 'Active') !== 0) {
                continue;
            }

            if (Hash::check($password, (string) $user->password)) {
                return (new User())
                    ->setConnection($connectionName)
                    ->newQuery()
                    ->whereKey($user->id)
                    ->first();
            }
        }

        return null;
    }

    protected function credentialCandidates(string $login, string $password): array
    {
        $login = trim($login);

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [[
                'email' => $login,
                'password' => $password,
            ]];
        }

        $normalizedPhone = preg_replace('/\D+/', '', $login);
        if (preg_match('/^\+?[0-9]{8,15}$/', $login) || preg_match('/^[0-9]{8,15}$/', $normalizedPhone)) {
            $phones = array_values(array_unique(array_filter([
                $normalizedPhone,
                ltrim($normalizedPhone, '0'),
                $login,
            ])));

            return array_map(function ($phone) use ($password) {
                return [
                    'phone' => $phone,
                    'password' => $password,
                ];
            }, $phones);
        }

        return [[
            'username' => $login,
            'password' => $password,
        ]];
    }

    protected function activeTenants()
    {
        return Tenant::query()
            ->where('status', 'Active')
            ->orderBy('id');
    }

    public function maxAttempts()
    {
        return SecuritySetting::getInt('autoban_login_threshold', 5);
    }

    public function decayMinutes()
    {
        return SecuritySetting::getInt('login_lockout_minutes', 1);
    }

    protected function sendFailedLoginResponse(Request $request)
    {
        $ip = $request->ip();
        $username = (string) $request->input($this->username());
        
        $cacheKey = 'failed_login_count_' . md5($ip);
        $attempts = (int) Cache::store('file')->get($cacheKey, 0) + 1;
        Cache::store('file')->put($cacheKey, $attempts, 900); // 15-minute sliding window

        $threshold = SecuritySetting::getInt('autoban_login_threshold', 5);
        $autoBanEnabled = SecuritySetting::getBool('autoban_failed_logins_enabled', true);

        if ($autoBanEnabled && $attempts >= $threshold) {
            SecurityLog::logIncident(
                $ip,
                'brute_force_login',
                'critical',
                'auto_banned',
                "Exceeded maximum failed login attempts ({$attempts}/{$threshold}) for username: {$username}",
                $request
            );

            BlockedIp::block(
                $ip,
                "Too many failed login attempts ({$attempts} attempts)",
                'brute_force',
                SecuritySetting::getInt('autoban_duration_hours', 24),
                'Auth Protection'
            );

            Cache::store('file')->forget($cacheKey);

            return response()->view('errors.security-blocked', [
                'ip' => $ip,
                'reason' => "Your IP has been banned due to {$attempts} repeated failed login attempts.",
                'incidentId' => 'SEC-BRUTE-' . strtoupper(substr(md5(time() . $ip), 0, 6)),
            ], 403);
        }

        SecurityLog::logIncident(
            $ip,
            'failed_login',
            'medium',
            'warning',
            "Failed login attempt ({$attempts}/{$threshold}) for username: {$username}",
            $request
        );

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    protected function sendLockoutResponse(Request $request)
    {
        $seconds = $this->limiter()->availableIn(
            $this->throttleKey($request)
        );

        SecurityLog::logIncident(
            $request->ip(),
            'brute_force_lockout',
            'high',
            'rate_limited',
            "Login rate limited for {$seconds} seconds. Username: " . $request->input($this->username()),
            $request
        );

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ])->status(429);
    }

    protected function authenticated(Request $request, $user)
    {
        $request->session()->forget('admin_intended_url');

        if (! admin_is_tenant_user()) {
            $request->session()->forget('admin_selected_tenant_id');
        }

        // Reset failed login tracking on successful login
        Cache::store('file')->forget('failed_login_count_' . md5($request->ip()));
    }

    public function logout(Request $request): RedirectResponse
    {
        $portalKey = (string) $request->session()->get('next_portal_session_key', '');

        if ($portalKey !== '') {
            Cache::store((string) config('cache.portal_session_store', 'portal_sessions'))
                ->forget('next_portal_session:' . hash('sha256', $portalKey));
        }

        if (admin_is_tenant_user() && tenant() && $request->user() instanceof User) {
            app(TenantActivityLogger::class)->log(
                'tenant user logged out',
                [],
                $request->user(),
                $request->user(),
                'logout',
                'authentication'
            );
        }

        $this->guard()->logout();
        cookie()->queue(cookie()->forget('auth_remember_tenant_id'));

        $request->session()->forget([
            'auth_user_scope',
            'auth_tenant_id',
            'admin_selected_tenant_id',
            'admin_intended_url',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
