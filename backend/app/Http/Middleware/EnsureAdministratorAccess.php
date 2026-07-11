<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdministratorAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (admin_is_tenant_user()) {
            return redirect()
                ->route('home')
                ->with('tenant_required', 'Tenant users cannot access administrator management.');
        }

        return $next($request);
    }
}
