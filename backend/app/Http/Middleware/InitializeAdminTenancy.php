<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InitializeAdminTenancy
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = admin_current_tenant();

        if ($tenant && ! tenant()) {
            tenancy()->initialize($tenant);
        }

        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (tenant()) {
            tenancy()->end();
        }
    }
}
