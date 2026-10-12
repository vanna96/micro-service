<?php

namespace App\Providers;

use App\Contracts\CatalogCacheInvalidator;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Gallery;
use App\Models\Item;
use App\Models\ItemOption;
use App\Models\ItemOptionGroup;
use App\Models\ItemOptionValue;
use App\Models\ItemUomPrice;
use App\Models\ItemVariant;
use App\Models\ItemVariation;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\RateIndexValue;
use App\Models\Slider;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Models\UomGroup;
use App\Models\UomGroupUnit;
use App\Observers\CatalogCacheObserver;
use App\Services\TaggedCatalogCacheInvalidator;
use App\Services\TenantCurrencyContext;
use App\Tenancy\FailOpenDomainTenantResolver;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\App\Services\MailNotificationService::class);
        $this->app->scoped(CatalogCacheInvalidator::class, TaggedCatalogCacheInvalidator::class);
        $this->app->scoped(TenantCurrencyContext::class);
        $this->app->bind(DomainTenantResolver::class, FailOpenDomainTenantResolver::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        DomainTenantResolver::$shouldCache = true;
        DomainTenantResolver::$cacheTTL = (int) config('query-cache.models.tenant', 3600);
        DomainTenantResolver::$cacheStore = (string) config('query-cache.store', 'redis');

        foreach ([
            Item::class,
            ItemOption::class,
            ItemOptionGroup::class,
            ItemOptionValue::class,
            ItemUomPrice::class,
            ItemVariant::class,
            ItemVariation::class,
            UnitOfMeasure::class,
            UomGroup::class,
            UomGroupUnit::class,
            Category::class,
            Branch::class,
            Slider::class,
            Currency::class,
            RateIndexValue::class,
            Promotion::class,
            PromotionItem::class,
            Gallery::class,
            Tenant::class,
        ] as $model) {
            $model::observe(CatalogCacheObserver::class);
        }

        $domainModel = config('tenancy.domain_model');
        if (is_string($domainModel) && class_exists($domainModel)) {
            $domainModel::observe(CatalogCacheObserver::class);
        }

        $this->configureCorsFromSecuritySettings();
    }

    /**
     * Dynamically configure CORS origins and settings from Security Center.
     */
    protected function configureCorsFromSecuritySettings(): void
    {
        try {
            if (class_exists(\App\Models\SecuritySetting::class)) {
                $rawOrigins = \App\Models\SecuritySetting::get('cors_allowed_origins');
                if ($rawOrigins !== null && trim((string) $rawOrigins) !== '') {
                    $origins = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $rawOrigins))));
                    if (! empty($origins)) {
                        config(['cors.allowed_origins' => $origins]);
                    }
                }

                $supportsCreds = \App\Models\SecuritySetting::get('cors_supports_credentials');
                if ($supportsCreds !== null) {
                    config(['cors.supports_credentials' => filter_var($supportsCreds, FILTER_VALIDATE_BOOLEAN)]);
                }

                $rawMethods = \App\Models\SecuritySetting::get('cors_allowed_methods');
                if ($rawMethods !== null && trim((string) $rawMethods) !== '') {
                    $methods = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $rawMethods))));
                    if (! empty($methods)) {
                        config(['cors.allowed_methods' => $methods]);
                    }
                }

                $rawHeaders = \App\Models\SecuritySetting::get('cors_allowed_headers');
                if ($rawHeaders !== null && trim((string) $rawHeaders) !== '') {
                    $headers = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $rawHeaders))));
                    if (! empty($headers)) {
                        config(['cors.allowed_headers' => $headers]);
                    }
                }

                $maxAge = \App\Models\SecuritySetting::get('cors_max_age');
                if ($maxAge !== null && is_numeric($maxAge)) {
                    config(['cors.max_age' => (int) $maxAge]);
                }
            }
        } catch (\Throwable $e) {
            // Ignore if database or table not ready during migrations/setup
        }
    }
}
