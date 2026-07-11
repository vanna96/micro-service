<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TenantActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantContextController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string'],
            'redirect_to' => ['nullable', 'string'],
        ]);

        $tenantId = (string) $validated['tenant_id'];

        $tenant = $request->user()
            ->tenants()
            ->where('tenants.id', $tenantId)
            ->where('status', 'Active')
            ->firstOrFail();

        $request->session()->put('admin_selected_tenant_id', $tenant->id);

        tenancy()->initialize($tenant);
        app(TenantActivityLogger::class)->log(
            'tenant context selected',
            [],
            null,
            $request->user(),
            'selected',
            'authentication'
        );
        tenancy()->end();

        $intendedUrl = $request->session()->pull('admin_intended_url');
        $redirectTo = $this->resolveRedirectTarget($request, (string) ($validated['redirect_to'] ?? ''));

        return redirect()->to($redirectTo ?: $intendedUrl ?: route('home'))
            ->with('status', 'Tenant selected successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_selected_tenant_id');

        return redirect()
            ->route('home')
            ->with('status', 'Tenant selection cleared.');
    }

    private function resolveRedirectTarget(Request $request, string $redirectTo): ?string
    {
        if ($redirectTo === '') {
            return null;
        }

        if (! str_starts_with($redirectTo, '/')) {
            return null;
        }

        return $request->getSchemeAndHttpHost() . $redirectTo;
    }
}
