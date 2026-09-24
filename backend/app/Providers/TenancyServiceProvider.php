<?php

declare(strict_types=1);

namespace App\Providers;

use Closure;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\Contracts\ClearableRepository as TelescopeClearableRepository;
use Laravel\Telescope\Contracts\EntriesRepository as TelescopeEntriesRepository;
use Laravel\Telescope\Contracts\PrunableRepository as TelescopePrunableRepository;
use Laravel\Telescope\Storage\DatabaseEntriesRepository as TelescopeDatabaseEntriesRepository;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
            // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                $this->tenantCreatedListener(),
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                $this->tenantDeletedListener(),
            ],

            // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

            // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

            // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
                $this->useTenantTelescopeStorage(),
                $this->attachTenantSentryContext(),
                $this->useTenantQueueConnection(),
                $this->forgetQueueConnections(),
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
                $this->useCentralTelescopeStorage(),
                $this->detachTenantSentryContext(),
                $this->restoreFallbackTenantConnection(),
                $this->useCentralQueueConnection(),
                $this->forgetQueueConnections(),
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

            // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

            // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    protected function tenantCreatedListener(): Closure
    {
        return function (Events\TenantCreated $event): void {
            foreach ([Jobs\CreateDatabase::class, Jobs\MigrateDatabase::class] as $jobClass) {
                $result = app()->call([new $jobClass($event->tenant), 'handle']);

                if ($result === false) {
                    break;
                }
            }
        };
    }

    protected function tenantDeletedListener(): Closure
    {
        return function (Events\TenantDeleted $event): void {
            app()->call([new Jobs\DeleteDatabase($event->tenant), 'handle']);
        };
    }

    protected function useTenantTelescopeStorage(): Closure
    {
        return function (): void {
            if (! class_exists(TelescopeDatabaseEntriesRepository::class)) {
                return;
            }

            config()->set('telescope.storage.database.connection', 'tenant');
            $this->forgetTelescopeRepositories();
        };
    }

    protected function useCentralTelescopeStorage(): Closure
    {
        return function (): void {
            if (! class_exists(TelescopeDatabaseEntriesRepository::class)) {
                return;
            }

            config()->set(
                'telescope.storage.database.connection',
                config('tenancy.database.central_connection', config('database.default', 'central'))
            );
            $this->forgetTelescopeRepositories();
        };
    }

    protected function attachTenantSentryContext(): Closure
    {
        return function (Events\TenancyInitialized $event): void {
            if (function_exists('Sentry\\configureScope')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($event): void {
                    $scope->setTag('tenant_id', (string) $event->tenancy->tenant?->id);
                });
            }
        };
    }

    protected function detachTenantSentryContext(): Closure
    {
        return function (Events\TenancyEnded $event): void {
            if (function_exists('Sentry\\configureScope')) {
                \Sentry\configureScope(function (\Sentry\State\Scope $scope): void {
                    $scope->removeTag('tenant_id');
                });
            }
        };
    }

    protected function restoreFallbackTenantConnection(): Closure
    {
        return function (): void {
            if (! config('database.connections.tenant')) {
                $central = config('tenancy.database.central_connection', 'central');
                config(['database.connections.tenant' => config("database.connections.{$central}")]);
            }
        };
    }

    protected function useTenantQueueConnection(): Closure
    {
        return function (): void {
            config()->set('queue.default', 'tenant');
        };
    }

    protected function useCentralQueueConnection(): Closure
    {
        return function (): void {
            config()->set('queue.default', env('QUEUE_CONNECTION', 'database'));
        };
    }

    protected function forgetQueueConnections(): Closure
    {
        return function (): void {
            if ($this->app->resolved('queue')) {
                try {
                    $queue = $this->app->make('queue');
                    $ref = new \ReflectionProperty($queue, 'connections');
                    $ref->setAccessible(true);
                    $ref->setValue($queue, []);
                } catch (\Throwable) {
                }
            }
        };
    }

    protected function forgetTelescopeRepositories(): void
    {
        foreach ([
            TelescopeEntriesRepository::class,
            TelescopeClearableRepository::class,
            TelescopePrunableRepository::class,
            TelescopeDatabaseEntriesRepository::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
            // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
