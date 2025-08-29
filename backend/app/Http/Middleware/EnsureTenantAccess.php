<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureTenantAccess
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
        $tenant_id = tenant('id');
        $accessToken = request()->bearerToken();
        if (!$accessToken || !str_contains($accessToken, '|')) return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);

        [$id, $token] = explode('|', $accessToken, 2);
        $tokenModel = PersonalAccessToken::on('central')->find($id);
        if (!$tokenModel) return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);

        if (!hash_equals($tokenModel->token, hash('sha256', $token))) return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
        
        $user = $tokenModel->tokenable;
        if (!$user->tenants()->where('tenants.id', $tenant_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized tenant access',
            ], 403);
        }
        return $next($request);
    }
}
