<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $tenant = tenant(); // from Stancl Tenancy helper

        if (!$tenant || $tenant->status !== 'Active') {
            return response()->json([
                'message' => 'Tenant is inactive or not allowed to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
