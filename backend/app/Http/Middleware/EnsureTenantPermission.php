<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTenantPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (! auth()->check() || ! admin_is_tenant_user()) {
            return $next($request);
        }

        $user = $request->user();

        if ($user && method_exists($user, 'hasTenantPermission') && $user->hasTenantPermission($permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this tenant resource.',
            ], 403);
        }

        return redirect()
            ->route('home')
            ->with('status', 'You do not have permission to access that page.');
    }
}
