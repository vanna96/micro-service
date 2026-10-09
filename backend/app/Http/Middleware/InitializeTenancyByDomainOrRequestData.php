<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedByRequestDataException;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedOnDomainException;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

class InitializeTenancyByDomainOrRequestData
{
    public function __construct(
        protected InitializeTenancyByDomain $domainMiddleware,
        protected InitializeTenancyByRequestData $requestDataMiddleware
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $payload = $request->header(InitializeTenancyByRequestData::$header)
            ?? $request->input(InitializeTenancyByRequestData::$queryParameter);

        if ($payload !== null && trim((string) $payload) !== '') {
            $payload = trim((string) $payload);

            // 1. Try resolving by primary ID, alias, or domain prefix
            $tenant = Tenant::find($payload)
                ?? Tenant::where('alias', $payload)->first()
                ?? Tenant::whereHas('domains', function ($q) use ($payload) {
                    $q->where('domain', $payload)
                      ->orWhere('domain', 'like', "{$payload}.%");
                })->first();

            if ($tenant) {
                tenancy()->initialize($tenant);
                return $next($request);
            }

            // Fallback to domain middleware if payload wasn't matched
            try {
                return $this->domainMiddleware->handle($request, $next);
            } catch (TenantCouldNotBeIdentifiedOnDomainException) {
                try {
                    return $this->requestDataMiddleware->handle($request, $next);
                } catch (TenantCouldNotBeIdentifiedByRequestDataException) {
                    return $this->storeNotFound();
                }
            }
        }

        try {
            return $this->domainMiddleware->handle($request, $next);
        } catch (TenantCouldNotBeIdentifiedOnDomainException) {
            return $this->storeNotFound();
        }
    }

    private function storeNotFound()
    {
        return response()->json([
            'success' => false,
            'message' => 'Store not found.',
        ], 404);
    }
}
