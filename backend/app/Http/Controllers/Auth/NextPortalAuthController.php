<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use App\Models\SecurityLog;
use App\Models\SecuritySetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NextPortalAuthController extends Controller
{
    private const SESSION_KEY = 'next_portal_session_key';

    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! $this->isCentralHost($request)) {
            return response()->json([
                'success' => false,
                'message' => __('Administrator sign-in is only available on the central domain.'),
            ], 403);
        }

        $ip = $request->ip();
        $cacheKey = 'failed_login_count_' . md5($ip);

        if (! $this->attemptAdministratorLogin(
            $validated['username'],
            $validated['password'],
            (bool) ($validated['remember'] ?? false)
        )) {
            $attempts = (int) Cache::store('file')->get($cacheKey, 0) + 1;
            Cache::store('file')->put($cacheKey, $attempts, 900);

            $threshold = SecuritySetting::getInt('autoban_login_threshold', 5);
            $autoBanEnabled = SecuritySetting::getBool('autoban_failed_logins_enabled', true);

            if ($autoBanEnabled && $attempts >= $threshold) {
                SecurityLog::logIncident(
                    $ip,
                    'brute_force_login',
                    'critical',
                    'auto_banned',
                    "Portal login: Exceeded max failed attempts ({$attempts}/{$threshold}) for username: {$validated['username']}",
                    $request
                );

                BlockedIp::block(
                    $ip,
                    "Too many portal failed login attempts ({$attempts} attempts)",
                    'brute_force',
                    SecuritySetting::getInt('autoban_duration_hours', 24),
                    'Portal Auth'
                );

                Cache::store('file')->forget($cacheKey);

                return response()->json([
                    'success' => false,
                    'message' => __('Your IP has been temporarily blocked due to repeated failed login attempts.'),
                ], 403);
            }

            SecurityLog::logIncident(
                $ip,
                'failed_login',
                'medium',
                'warning',
                "Portal failed login ({$attempts}/{$threshold}) for username: {$validated['username']}",
                $request
            );

            return response()->json([
                'success' => false,
                'message' => __('Invalid username or password.'),
            ], 401);
        }

        // Clear failed attempts on success
        Cache::store('file')->forget($cacheKey);

        $user = Auth::user();

        if (! $user instanceof User || strcasecmp((string) $user->status, 'Active') !== 0) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => __('Invalid username or password.'),
            ], 401);
        }

        $request->session()->regenerate();
        $request->session()->put('auth_user_scope', 'administrator');
        $request->session()->forget([
            'auth_tenant_id',
            'admin_selected_tenant_id',
            'pos_tenant_id',
        ]);

        $portalKey = Str::random(64);
        $request->session()->put(self::SESSION_KEY, $portalKey);
        $this->portalCache()->put(
            $this->portalCacheKey($portalKey),
            ['user_id' => $user->getAuthIdentifier()],
            now()->addMinutes((int) config('session.lifetime', 120))
        );

        return response()->json([
            'success' => true,
            'data' => $this->sessionPayload($request, $user),
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $this->portalSessionIsValid($request, $user)) {
            return response()->json(['authenticated' => false], 401);
        }

        if (! $this->isCentralHost($request) && ! $this->tenantSessionIsValid($request, $user)) {
            return response()->json(['authenticated' => false], 401);
        }

        return response()->json([
            'authenticated' => true,
            'data' => $this->sessionPayload($request, $user),
        ]);
    }

    public function createTenantHandoff(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $this->portalSessionIsValid($request, $user) || ! $this->isCentralHost($request)) {
            return response()->json(['message' => __('Unauthenticated.')], 401);
        }

        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'domain' => ['required', 'string', 'max:255'],
        ]);

        $tenant = $user->tenants()
            ->where('tenants.id', $validated['tenant_id'])
            ->where('tenants.status', 'Active')
            ->whereHas('domains', function ($query) use ($validated) {
                $query->where('domain', strtolower($validated['domain']));
            })
            ->with('domains')
            ->first();

        if (! $tenant instanceof Tenant) {
            return response()->json([
                'message' => __('That tenant domain is not assigned to your account.'),
            ], 403);
        }

        $domain = $tenant->domains
            ->first(fn ($candidate) => strtolower((string) $candidate->domain) === strtolower($validated['domain']));

        if (! $domain) {
            return response()->json(['message' => __('Tenant domain is unavailable.')], 422);
        }

        $ticket = Str::random(80);
        $this->portalCache()->put($this->handoffCacheKey($ticket), [
            'user_id' => $user->getAuthIdentifier(),
            'tenant_id' => (string) $tenant->getTenantKey(),
            'domain' => strtolower((string) $domain->domain),
            'portal_key' => (string) $request->session()->get(self::SESSION_KEY),
        ], now()->addMinutes(5));

        $port = $request->getPort();
        $includePort = ! in_array($port, [80, 443], true);
        $authority = strtolower((string) $domain->domain) . ($includePort ? ':' . $port : '');

        return response()->json([
            'success' => true,
            'url' => $request->getScheme() . '://' . $authority
                . '/next/auth/tenant-handoff?ticket=' . urlencode($ticket),
        ]);
    }

    public function redeemTenantHandoff(Request $request): RedirectResponse
    {
        $ticket = (string) $request->query('ticket', '');
        $payload = $ticket !== '' ? $this->portalCache()->pull($this->handoffCacheKey($ticket)) : null;

        if (! is_array($payload) || ! hash_equals((string) ($payload['domain'] ?? ''), strtolower($request->getHost()))) {
            return redirect($this->centralPortalUrl($request));
        }

        $user = User::query()
            ->whereKey($payload['user_id'] ?? null)
            ->where('status', 'Active')
            ->first();

        $tenant = $user?->tenants()
            ->where('tenants.id', $payload['tenant_id'] ?? '')
            ->where('tenants.status', 'Active')
            ->whereHas('domains', function ($query) use ($request) {
                $query->where('domain', strtolower($request->getHost()));
            })
            ->first();

        $portalKey = (string) ($payload['portal_key'] ?? '');

        if (! $user instanceof User || ! $tenant instanceof Tenant || $portalKey === '' || ! $this->portalCache()->has($this->portalCacheKey($portalKey))) {
            return redirect($this->centralPortalUrl($request));
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put([
            'auth_user_scope' => 'administrator',
            'admin_selected_tenant_id' => (string) $tenant->getTenantKey(),
            'pos_tenant_id' => (string) $tenant->getTenantKey(),
            self::SESSION_KEY => $portalKey,
        ]);

        return redirect('/');
    }

    public function logout(Request $request): JsonResponse
    {
        $portalKey = (string) $request->session()->get(self::SESSION_KEY, '');

        if ($portalKey !== '') {
            $this->portalCache()->forget($this->portalCacheKey($portalKey));
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['success' => true]);
    }

    private function attemptAdministratorLogin(string $login, string $password, bool $remember): bool
    {
        foreach ($this->credentialCandidates($login, $password) as $credentials) {
            if (Auth::attempt($credentials, $remember)) {
                return true;
            }
        }

        return false;
    }

    private function credentialCandidates(string $login, string $password): array
    {
        $login = trim($login);

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [['email' => $login, 'password' => $password]];
        }

        $phone = preg_replace('/\D+/', '', $login);

        if (preg_match('/^\+?[0-9]{8,15}$/', $login) || preg_match('/^[0-9]{8,15}$/', $phone)) {
            $phones = array_values(array_unique(array_filter([
                $phone,
                ltrim($phone, '0'),
                $login,
            ])));

            return array_map(fn ($candidate) => [
                'phone' => $candidate,
                'password' => $password,
            ], $phones);
        }

        return [['username' => $login, 'password' => $password]];
    }

    private function sessionPayload(Request $request, User $user): array
    {
        $tenantId = (string) $request->session()->get('pos_tenant_id', '');

        return [
            'user' => [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
            ],
            'tenants' => $this->isCentralHost($request) ? $this->tenantPayloads($user) : [],
            'tenant' => $tenantId !== ''
                ? $this->tenantPayload($user, $tenantId, strtolower($request->getHost()))
                : null,
        ];
    }

    private function tenantPayloads(User $user): array
    {
        return $user->tenants()
            ->where('tenants.status', 'Active')
            ->with('domains')
            ->orderBy('tenants.id')
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => (string) $tenant->getTenantKey(),
                'name' => admin_tenant_display_name($tenant),
                'alias' => $tenant->alias,
                'domains' => $tenant->domains
                    ->pluck('domain')
                    ->map(fn ($domain) => strtolower((string) $domain))
                    ->sort()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function tenantPayload(User $user, string $tenantId, string $domain): ?array
    {
        $tenant = $user->tenants()
            ->where('tenants.id', $tenantId)
            ->where('tenants.status', 'Active')
            ->whereHas('domains', function ($query) use ($domain) {
                $query->where('domain', $domain);
            })
            ->with('domains')
            ->first();

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return [
            'id' => (string) $tenant->getTenantKey(),
            'name' => admin_tenant_display_name($tenant),
            'domain' => $domain,
        ];
    }

    private function portalSessionIsValid(Request $request, User $user): bool
    {
        $portalKey = (string) $request->session()->get(self::SESSION_KEY, '');
        $cached = $portalKey !== '' ? $this->portalCache()->get($this->portalCacheKey($portalKey)) : null;

        return is_array($cached)
            && (string) ($cached['user_id'] ?? '') === (string) $user->getAuthIdentifier();
    }

    private function tenantSessionIsValid(Request $request, User $user): bool
    {
        $tenantId = (string) $request->session()->get('pos_tenant_id', '');

        if ($tenantId === '') {
            return false;
        }

        return $user->tenants()
            ->where('tenants.id', $tenantId)
            ->where('tenants.status', 'Active')
            ->whereHas('domains', function ($query) use ($request) {
                $query->where('domain', strtolower($request->getHost()));
            })
            ->exists();
    }

    private function isCentralHost(Request $request): bool
    {
        return in_array(strtolower($request->getHost()), array_map('strtolower', config('tenancy.central_domains', [])), true);
    }

    private function portalCacheKey(string $key): string
    {
        return 'next_portal_session:' . hash('sha256', $key);
    }

    private function portalCache(): CacheRepository
    {
        return Cache::store((string) config('cache.portal_session_store', 'portal_sessions'));
    }

    private function handoffCacheKey(string $ticket): string
    {
        return 'next_portal_handoff:' . hash('sha256', $ticket);
    }

    private function centralPortalUrl(Request $request): string
    {
        $centralHost = (string) collect(config('tenancy.central_domains', ['localhost']))
            ->first(fn ($host) => $host === 'localhost', 'localhost');
        $port = $request->getPort();

        return $request->getScheme() . '://' . $centralHost
            . (! in_array($port, [80, 443], true) ? ':' . $port : '') . '/';
    }
}
