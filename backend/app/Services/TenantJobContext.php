<?php

namespace App\Services;

use App\Models\Tenant;

class TenantJobContext
{
    public static function run(?string $tenantId, callable $callback): mixed
    {
        $previous = tenant();
        $target = $tenantId
            ? ($previous && (string) $previous->id === $tenantId ? $previous : Tenant::findOrFail($tenantId))
            : null;

        try {
            if ($target && (! $previous || (string) $previous->id !== (string) $target->id)) {
                tenancy()->initialize($target);
            } elseif (! $target && $previous) {
                tenancy()->end();
            }

            return $callback($target);
        } finally {
            $current = tenant();
            if ($previous && (! $current || (string) $current->id !== (string) $previous->id)) {
                tenancy()->initialize($previous);
            } elseif (! $previous && $current) {
                tenancy()->end();
            }
        }
    }
}
