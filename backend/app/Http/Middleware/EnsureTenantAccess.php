<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (! $tokenModel
            || ($tokenModel->expires_at && $tokenModel->expires_at->isPast())
            || $tokenModel->name !== 'authToken'
            || ! $tokenModel->can('tenant:access')
            || $tokenModel->tokenable_type !== User::class
        ) return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);

        if (!hash_equals($tokenModel->token, hash('sha256', $token))) return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
        
        $user = User::on('central')->find($tokenModel->tokenable_id);
        if (! $user || $user->status !== 'Active') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!$user->tenants()->where('tenants.id', $tenant_id)->where('tenants.status', 'Active')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized tenant access',
            ], 403);
        }

        if (method_exists($user, 'withAccessToken')) {
            $user = $user->withAccessToken($tokenModel);
        }

        $tokenModel->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
