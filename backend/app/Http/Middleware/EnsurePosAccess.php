<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsurePosAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $tenantId = (string) tenant('id');

        if (! $user instanceof User || strcasecmp((string) $user->status, 'Active') !== 0 || $tenantId === '') {
            return $this->denied(401, 'Unauthenticated');
        }

        $accessToken = $user->currentAccessToken();
        if ($accessToken instanceof PersonalAccessToken
            && (($accessToken->expires_at && $accessToken->expires_at->isPast())
                || $accessToken->name !== 'authToken'
                || ! $accessToken->can('tenant:access'))
        ) {
            return $this->denied(401, 'Unauthenticated');
        }

        if (admin_is_tenant_user()) {
            if (admin_auth_tenant_id() !== $tenantId) {
                return $this->denied(403, 'Unauthorized tenant access');
            }

            return $next($request);
        }

        $centralConnection = config('tenancy.database.central_connection') ?: 'central';
        if ($user->getConnectionName() !== $centralConnection) {
            return $this->denied(403, 'Unauthorized tenant access');
        }

        $hasTenantAccess = $user->tenants()
            ->where('tenants.id', $tenantId)
            ->where('tenants.status', 'Active')
            ->exists();

        if (! $hasTenantAccess) {
            return $this->denied(403, 'Unauthorized tenant access');
        }

        return $next($request);
    }

    private function denied(int $status, string $message)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
