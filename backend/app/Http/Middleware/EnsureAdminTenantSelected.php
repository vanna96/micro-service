<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant;
use Illuminate\Http\Request;

class EnsureAdminTenantSelected
{
    public function handle(Request $request, Closure $next)
    {
        $selectedTenantId = (string) $request->session()->get('admin_selected_tenant_id', admin_auth_tenant_id() ?: '');

        if ($selectedTenantId === '') {
            $request->session()->put('admin_intended_url', $request->fullUrl());

            return redirect()
                ->route('home')
                ->with('tenant_required', 'Please select a tenant before opening the admin area.');
        }

        if (admin_is_tenant_user()) {
            $tenantExists = Tenant::query()
                ->where('id', $selectedTenantId)
                ->where('status', 'Active')
                ->exists();
        } else {
            $tenantExists = $request->user()
                ->tenants()
                ->where('tenants.id', $selectedTenantId)
                ->where('status', 'Active')
                ->exists();
        }

        if (! $tenantExists) {
            $request->session()->forget('admin_selected_tenant_id');
            $request->session()->put('admin_intended_url', $request->fullUrl());

            return redirect()
                ->route('home')
                ->with('tenant_required', 'Your selected tenant is no longer available. Please choose another tenant.');
        }

        return $next($request);
    }
}
