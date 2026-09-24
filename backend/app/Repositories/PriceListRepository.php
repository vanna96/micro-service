<?php

namespace App\Repositories;

use App\Models\Item;
use App\Models\PriceList;
use App\Models\PriceListItem;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceListRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\PriceList';

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->priceListModel()
            ->newQuery()
            ->cacheFor($this->priceListCacheTtl())
            ->cachePrefix($this->priceListListingCachePrefix())
            ->cacheTags($this->priceListListingCacheTags())
            ->withCount('activeLines')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('header_pricing_method', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_default')
            ->orderBy('name');

        $this->reportCacheState($query, 'admin.price-lists.index');

        return $query->get();
    }

    public function loadForAdminEdit(int $priceListId): PriceList
    {
        $query = $this->priceListModel()
            ->newQuery()
            ->with([
                'lines' => fn ($lineQuery) => $lineQuery->with('item.currency')->orderByDesc('id'),
                'activeLines',
            ])
            ->whereKey($priceListId);

        $this->reportCacheState($query, 'admin.price-lists.edit');

        return $query->firstOrFail();
    }

    public function getItemOptions(): Collection
    {
        $query = $this->itemModel()
            ->newQuery()
            ->with('currency')
            ->where('status', 'Active')
            ->orderBy('name');

        $this->reportCacheState($query, 'admin.price-lists.item-options');

        return $query->get();
    }

    public function getOptions(?int $selectedPriceListId = null): Collection
    {
        $priceListKeyName = $this->priceListModel()->getKeyName();

        $query = $this->priceListModel()
            ->newQuery()
            ->where(function ($innerQuery) use ($selectedPriceListId, $priceListKeyName) {
                $innerQuery->where('status', 'Active');

                if ($selectedPriceListId) {
                    $innerQuery->orWhere($priceListKeyName, $selectedPriceListId);
                }
            })
            ->orderByDesc('is_default')
            ->orderBy('name');

        $this->reportCacheState($query, 'admin.price-lists.options');

        return $query->get();
    }

    public function createForAdmin(array $attributes): PriceList
    {
        return DB::connection($this->tenantConnectionName())->transaction(function () use ($attributes) {
            $attributes = $this->normalizePriceListAttributes($attributes);

            if ($attributes['is_default']) {
                $this->unsetDefaultPriceLists();
            }

            $priceList = $this->priceListModel();
            $priceList->fill($attributes);
            $priceList->save();

            PriceList::flushQueryCache();

            return $priceList;
        });
    }

    public function updateForAdmin(PriceList $priceList, array $attributes): PriceList
    {
        return DB::connection($this->tenantConnectionName())->transaction(function () use ($priceList, $attributes) {
            $attributes = $this->normalizePriceListAttributes($attributes);

            if ($attributes['is_default']) {
                $this->unsetDefaultPriceLists($priceList->id);
            }

            $priceList->fill($attributes);
            $priceList->save();

            PriceList::flushQueryCache();

            return $priceList;
        });
    }

    public function deleteForAdmin(PriceList $priceList): void
    {
        $priceList->delete();

        PriceList::flushQueryCache();
        PriceListItem::flushQueryCache();
    }

    public function createLineForAdmin(PriceList $priceList, array $attributes): PriceListItem
    {
        $attributes = $this->normalizeLineAttributes($attributes);

        $line = $priceList->lines()->create($attributes);

        PriceList::flushQueryCache();
        PriceListItem::flushQueryCache();

        return $line;
    }

    public function updateLineForAdmin(PriceListItem $line, array $attributes): PriceListItem
    {
        $attributes['item_id'] = $attributes['item_id'] ?? $line->item_id;
        $attributes = $this->normalizeLineAttributes($attributes);

        $line->fill($attributes);
        $line->save();

        PriceList::flushQueryCache();
        PriceListItem::flushQueryCache();

        return $line;
    }

    public function deleteLineForAdmin(PriceListItem $line): void
    {
        $line->delete();

        PriceList::flushQueryCache();
        PriceListItem::flushQueryCache();
    }

    protected function normalizePriceListAttributes(array $attributes): array
    {
        $attributes['is_default'] = (bool) ($attributes['is_default'] ?? false);

        if (($attributes['header_pricing_method'] ?? null) === 'fixed') {
            $attributes['header_fixed_price'] = format_currency_input(
                $attributes['header_fixed_price'] ?? null,
                tenant_base_currency(),
                2
            );
            $attributes['header_discount_percent'] = null;
        } elseif (($attributes['header_pricing_method'] ?? null) === 'discount') {
            $attributes['header_fixed_price'] = null;
        } else {
            $attributes['header_pricing_method'] = null;
            $attributes['header_fixed_price'] = null;
            $attributes['header_discount_percent'] = null;
        }

        if (($attributes['status'] ?? 'Active') !== 'Active') {
            $attributes['is_default'] = false;
        }

        return $attributes;
    }

    protected function normalizeLineAttributes(array $attributes): array
    {
        if (($attributes['pricing_method'] ?? null) === 'fixed') {
            $item = ! empty($attributes['item_id'])
                ? $this->itemModel()->newQuery()->with('currency')->find($attributes['item_id'])
                : null;
            $attributes['fixed_price'] = format_currency_input($attributes['fixed_price'] ?? null, $item?->currency, 2);
            $attributes['discount_percent'] = null;
        }

        if (($attributes['pricing_method'] ?? null) === 'discount') {
            $attributes['fixed_price'] = null;
        }

        return $attributes;
    }

    protected function unsetDefaultPriceLists(?int $ignoreId = null): void
    {
        $query = $this->priceListModel()
            ->newQuery()
            ->where('is_default', true);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        $query->update(['is_default' => false]);
    }

    protected function priceListCacheTtl(): int
    {
        return (int) config('query-cache.models.price_list', config('query-cache.default_ttl', 300));
    }

    protected function priceListListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-price-list-listing:' . tenant()->getTenantKey()
            : 'tenant-price-list-listing:central';

        return [
            'admin-price-list-listing',
            $tenantTag,
        ];
    }

    protected function priceListListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':price-list-listing:' . $tenantKey;
    }

    protected function tenantConnectionName(): string
    {
        if (! tenant()) {
            return 'central';
        }

        return tenant()->database_connection_name ?: 'tenant';
    }

    protected function priceListModel(): PriceList
    {
        /** @var \App\Models\PriceList $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function itemModel(): Item
    {
        $model = new Item();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function reportCacheState(EloquentBuilder $query, string $context): void
    {
        if (! config('app.debug')) {
            return;
        }

        $baseQuery = $query->getQuery();

        if (! method_exists($baseQuery, 'getCacheKey') || ! method_exists($baseQuery, 'getCache')) {
            return;
        }

        $cacheKey = $baseQuery->getCacheKey('get');
        $cacheHit = $baseQuery->getCache()->has($cacheKey);
        $payload = [
            'context' => $context,
            'cache_hit' => $cacheHit,
            'cache_key' => $cacheKey,
            'ttl' => $this->priceListCacheTtl(),
        ];

        Log::debug('Price list repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
