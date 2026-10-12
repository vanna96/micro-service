<?php

namespace App\Observers;

use App\Cache\CatalogCacheTags;
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
use Illuminate\Database\Eloquent\Model;

class CatalogCacheObserver
{
    public function __construct(private CatalogCacheInvalidator $invalidator)
    {
    }

    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        $resources = $this->resourcesFor($model);
        if ($resources === []) {
            return;
        }

        $this->invalidator->invalidateAfterCommit(
            $resources,
            $model->getConnectionName(),
            $this->tenantIdFor($model)
        );

        if ($model instanceof Tenant || $model instanceof \Stancl\Tenancy\Contracts\Domain) {
            $this->invalidator->invalidateAfterCommit(
                [CatalogCacheTags::STORES, CatalogCacheTags::TENANTS],
                $model->getConnectionName(),
                null
            );
        }
    }

    /** @return array<int, string> */
    private function resourcesFor(Model $model): array
    {
        if ($model instanceof Item
            || $model instanceof ItemOption
            || $model instanceof ItemOptionGroup
            || $model instanceof ItemOptionValue
            || $model instanceof ItemUomPrice
            || $model instanceof ItemVariant
            || $model instanceof ItemVariation
            || $model instanceof UnitOfMeasure
            || $model instanceof UomGroup
            || $model instanceof UomGroupUnit) {
            return [CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP];
        }

        if ($model instanceof Category) {
            return [CatalogCacheTags::CATEGORIES, CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP];
        }

        if ($model instanceof Branch) {
            return [CatalogCacheTags::BRANCHES, CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP];
        }

        if ($model instanceof Slider) {
            return [CatalogCacheTags::BANNERS, CatalogCacheTags::BOOTSTRAP];
        }

        if ($model instanceof Currency || $model instanceof RateIndexValue) {
            return [CatalogCacheTags::CURRENCIES, CatalogCacheTags::RATES, CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP];
        }

        if ($model instanceof Promotion || $model instanceof PromotionItem) {
            return [CatalogCacheTags::PROMOTIONS, CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP];
        }

        if ($model instanceof Gallery) {
            return match ($model->getAttribute('gallarieable_type')) {
                Item::class => [CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP],
                Category::class => [CatalogCacheTags::CATEGORIES, CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP],
                Slider::class => [CatalogCacheTags::BANNERS, CatalogCacheTags::BOOTSTRAP],
                Promotion::class => [CatalogCacheTags::PROMOTIONS, CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP],
                default => [],
            };
        }

        if ($model instanceof Tenant || $model instanceof \Stancl\Tenancy\Contracts\Domain) {
            return [CatalogCacheTags::STORES, CatalogCacheTags::TENANTS, CatalogCacheTags::BOOTSTRAP];
        }

        return [];
    }

    private function tenantIdFor(Model $model): ?string
    {
        if ($model instanceof Tenant) {
            return (string) $model->getTenantKey();
        }

        if ($model instanceof \Stancl\Tenancy\Contracts\Domain) {
            return (string) $model->getAttribute('tenant_id');
        }

        return tenant() ? (string) tenant()->getTenantKey() : null;
    }
}
