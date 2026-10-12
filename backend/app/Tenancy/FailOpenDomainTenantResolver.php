<?php

namespace App\Tenancy;

use Stancl\Tenancy\Contracts\Tenant;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Throwable;

class FailOpenDomainTenantResolver extends DomainTenantResolver
{
    public function resolve(...$args): Tenant
    {
        try {
            return parent::resolve(...$args);
        } catch (Throwable $exception) {
            if (! $this->isCacheFailure($exception)) {
                throw $exception;
            }

            return $this->resolveWithoutCache(...$args);
        }
    }

    public function invalidateCache(Tenant $tenant): void
    {
        try {
            parent::invalidateCache($tenant);
        } catch (Throwable $exception) {
            if (! $this->isCacheFailure($exception)) {
                throw $exception;
            }
        }
    }

    private function isCacheFailure(Throwable $exception): bool
    {
        for ($current = $exception; $current; $current = $current->getPrevious()) {
            $class = strtolower($current::class);
            $message = strtolower($current->getMessage());

            if (str_contains($class, 'redis')
                || str_contains($class, 'predis')
                || str_contains($message, 'redis')
                || str_contains($message, 'connection refused')
                || str_contains($message, 'connection timed out')) {
                return true;
            }
        }

        return false;
    }
}
