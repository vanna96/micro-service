<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureCustomerAccess
{
    public function handle(Request $request, Closure $next)
    {
        $plainTextToken = (string) $request->bearerToken();

        if (! str_contains($plainTextToken, '|')) {
            return $this->unauthenticated();
        }

        [$id, $secret] = explode('|', $plainTextToken, 2);
        if (! tenant()) {
            return $this->unauthenticated();
        }

        $connection = tenant()?->database_connection_name ?: 'tenant';
        $token = PersonalAccessToken::on($connection)->find($id);

        $isAccessToken = $token?->name === 'customer-access';
        $isRefreshLogout = $token?->name === 'customer-refresh'
            && $request->is('v1/api/mobile/auth/logout');

        if (! $token
            || ! hash_equals((string) $token->token, hash('sha256', $secret))
            || ($token->expires_at && $token->expires_at->isPast())
            || (! $isAccessToken && ! $isRefreshLogout)
            || $token->tokenable_type !== Customer::class) {
            return $this->unauthenticated();
        }

        $customer = Customer::query()->customers()->find($token->tokenable_id);

        if (! $customer || $customer->status !== 'Active') {
            return $this->unauthenticated();
        }

        $customer->withAccessToken($token);
        $token->forceFill(['last_used_at' => now()])->save();
        Auth::setUser($customer);
        $request->setUserResolver(static fn () => $customer);

        return $next($request);
    }

    private function unauthenticated()
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated',
        ], 401);
    }
}
