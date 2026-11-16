<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreventAccessFromTenantDomains
{
    /**
     * Handle an incoming request.
     *
     * Prevents tenant and wildcard subdomains from accessing backend panel routes.
     * Only central domains (e.g. localhost, 127.0.0.1) are permitted.
     */
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());

        $centralDomains = array_map('strtolower', (array) config('tenancy.central_domains', ['localhost', '127.0.0.1']));
        $appHost = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));

        if ($appHost !== '' && ! in_array($appHost, $centralDomains, true)) {
            $centralDomains[] = $appHost;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $isTenantUser = $session && $session->get('auth_user_scope') === 'tenant';

        if (in_array($host, $centralDomains, true)) {
            // Central domain is for central users only.
            if ($isTenantUser) {
                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();

                return $request->expectsJson() ? abort(401) : redirect('/admin');
            }

            return $next($request);
        }

        // Store subdomain: only that store's own signed-in users get the backend.
        $tenant = Tenant::query()
            ->where('status', 'Active')
            ->whereHas('domains', fn ($query) => $query->where('domain', $host))
            ->first();

        if (! $tenant instanceof Tenant) {
            abort(404, 'The requested administration panel is only accessible on the central domain.');
        }

        if ($isTenantUser && (string) $session->get('auth_tenant_id') === (string) $tenant->getTenantKey()) {
            return $next($request);
        }

        // Not signed in as this store's user: go to the store sign-in page.
        return $request->expectsJson() ? abort(401) : redirect('/admin');
    }
}
