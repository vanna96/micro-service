<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantActivityLogger;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

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
    protected $redirectTo = '/home';

    public function showAdminLoginForm()
    {
        return view('auth.login');
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

    protected function authenticated(Request $request, $user)
    {
        $request->session()->forget('admin_intended_url');

        if (! admin_is_tenant_user()) {
            $request->session()->forget('admin_selected_tenant_id');
        }
    }

    public function logout(Request $request): RedirectResponse
    {
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

        $request->session()->forget([
            'auth_user_scope',
            'auth_tenant_id',
            'admin_selected_tenant_id',
            'admin_intended_url',
        ]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
