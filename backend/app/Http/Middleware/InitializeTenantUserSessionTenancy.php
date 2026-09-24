<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Recaller;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InitializeTenantUserSessionTenancy
{
    public function handle(Request $request, Closure $next)
    {
        if (tenant()) {
            return $next($request);
        }

        if (admin_is_tenant_user()) {
            $this->initializeTenancyFromSession();

            return $next($request);
        }

        $this->restoreRememberedTenantSession($request);

        if (admin_is_tenant_user()) {
            $this->initializeTenancyFromSession();
        }

        return $next($request);
    }

    protected function initializeTenancyFromSession(): void
    {
        $tenantId = admin_auth_tenant_id();

        if ($tenantId) {
            $tenant = Tenant::query()
                ->where('id', $tenantId)
                ->where('status', 'Active')
                ->first();

            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }
    }

    protected function restoreRememberedTenantSession(Request $request): void
    {
        if (! $request->hasSession()
            || $request->session()->has('auth_tenant_id')
            || $request->session()->get('auth_user_scope') === 'administrator'
            || Auth::guard()->check()
            || $request->session()->get('remember_tenant_checked')
        ) {
            return;
        }

        $guard = Auth::guard();

        if (! method_exists($guard, 'getRecallerName')) {
            return;
        }

        $recallerValue = $request->cookies->get($guard->getRecallerName());

        if (! is_string($recallerValue) || $recallerValue === '') {
            return;
        }

        $recaller = new Recaller($recallerValue);

        if (! $recaller->valid()) {
            return;
        }

        // 1. Check if recaller belongs to central administrator/user first
        try {
            $centralConnection = config('tenancy.database.central_connection') ?: config('database.default', 'central');
            $isCentralUser = DB::connection($centralConnection)
                ->table('users')
                ->where('id', $recaller->id())
                ->where('remember_token', $recaller->token())
                ->where('status', 'Active')
                ->exists();

            if ($isCentralUser) {
                $request->session()->put('remember_tenant_checked', true);

                return;
            }
        } catch (\Throwable $e) {
        }

        // 2. Fast path: check specific tenant if remember_tenant_id cookie exists
        $tenantIdCookie = (string) $request->cookies->get('auth_remember_tenant_id', '');
        if ($tenantIdCookie !== '') {
            $tenant = Tenant::query()
                ->where('id', $tenantIdCookie)
                ->where('status', 'Active')
                ->first();

            if ($tenant && $this->verifyRecallerOnTenant($tenant, $recaller)) {
                $request->session()->put([
                    'auth_user_scope' => 'tenant',
                    'auth_tenant_id' => $tenant->id,
                    'admin_selected_tenant_id' => $tenant->id,
                ]);

                return;
            }
        }

        // 3. Fallback: search active tenants
        $tenant = $this->findTenantForRecaller($recaller);

        if (! $tenant instanceof Tenant) {
            $request->session()->put('remember_tenant_checked', true);

            return;
        }

        $request->session()->put([
            'auth_user_scope' => 'tenant',
            'auth_tenant_id' => $tenant->id,
            'admin_selected_tenant_id' => $tenant->id,
        ]);
    }

    protected function verifyRecallerOnTenant(Tenant $tenant, Recaller $recaller): bool
    {
        tenancy()->initialize($tenant);

        try {
            $userTable = (new User())->getTable();

            $user = DB::connection('tenant')
                ->table($userTable)
                ->where('id', $recaller->id())
                ->where('remember_token', $recaller->token())
                ->where('password', $recaller->hash())
                ->where('status', 'Active')
                ->first();

            return (bool) $user;
        } finally {
            tenancy()->end();
            DB::purge('tenant');
        }
    }

    protected function findTenantForRecaller(Recaller $recaller): ?Tenant
    {
        foreach (Tenant::query()->where('status', 'Active')->orderBy('id')->cursor() as $tenant) {
            if ($this->verifyRecallerOnTenant($tenant, $recaller)) {
                return $tenant;
            }
        }

        return null;
    }

    public function terminate(Request $request, $response): void
    {
        if (tenant()) {
            tenancy()->end();
        }
    }
}
