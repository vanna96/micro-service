<?php

namespace App\Repositories;

use App\Cache\CatalogCacheTags;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Promotion;
use App\Models\Slider;
use App\Services\CatalogQueryCache;
use App\Services\TenantCurrencyContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class CatalogReadRepository
{
    public function __construct(
        private CatalogQueryCache $cache,
        private TenantCurrencyContext $currencies
    ) {
    }

    public function activeBranches(): Collection
    {
        return $this->cache->run(
            CatalogCacheTags::BRANCHES,
            $this->metadataTtl(),
            fn (bool $cached) => Branch::query()
                ->where('status', 'Active')
                ->orderBy('sort_order')
                ->orderBy('name'),
            fn ($query) => $query->get(),
            [CatalogCacheTags::BOOTSTRAP]
        );
    }

    public function banners(array $placements, ?int $limit = null): Collection
    {
        return $this->cache->run(
            CatalogCacheTags::BANNERS,
            $this->metadataTtl(),
            function (bool $cached) use ($placements, $limit) {
                $query = Slider::query()
                    ->with($this->simpleRelation('image', CatalogCacheTags::BANNERS, $cached))
                    ->where('status', 'Active')
                    ->whereIn('placement', $placements)
                    ->orderBy('sort_order')
                    ->orderByDesc('id');

                return $limit ? $query->limit($limit) : $query;
            },
            fn ($query) => $query->get(),
            [CatalogCacheTags::BOOTSTRAP]
        );
    }

    public function categories(string $search, int $perPage, int $page): LengthAwarePaginator
    {
        $cacheSearch = $this->cacheableSearch($search, $perPage, $page);

        return $this->cache->run(
            CatalogCacheTags::CATEGORIES,
            $this->metadataTtl(),
            function (bool $cached) use ($search) {
                return Category::query()
                    ->with($this->simpleRelation('image', CatalogCacheTags::CATEGORIES, $cached))
                    ->where('status', 'Active')
                    ->when($search !== '', function ($query) use ($search) {
                        $query->where(function ($innerQuery) use ($search) {
                            $innerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%")
                                ->orWhereHas('items', function ($itemQuery) use ($search) {
                                    $itemQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('foreign_name', 'like', "%{$search}%")
                                        ->orWhere('sku', 'like', "%{$search}%");
                                });
                        });
                    })
                    ->orderByDesc('id');
            },
            fn ($query) => $query->paginate($perPage, ['*'], 'page', $page),
            [CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP],
            $search === '' || $cacheSearch
        );
    }

    public function featuredCategories(int $limit = 12): Collection
    {
        return $this->cache->run(
            CatalogCacheTags::CATEGORIES,
            $this->metadataTtl(),
            fn (bool $cached) => Category::query()
                ->with($this->simpleRelation('image', CatalogCacheTags::CATEGORIES, $cached))
                ->where('status', 'Active')
                ->orderByDesc('id')
                ->limit($limit),
            fn ($query) => $query->get(),
            [CatalogCacheTags::BOOTSTRAP]
        );
    }

    public function products(array $filters): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = max(1, (int) ($filters['per_page'] ?? 20));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $cache = $search === '' || $this->cacheableSearch($search, $perPage, $page);

        return $this->cache->run(
            CatalogCacheTags::ITEMS,
            $this->itemTtl(),
            function (bool $cached) use ($filters, $search) {
                $query = $this->productQuery($cached)
                    ->when(! empty($filters['category_id']), fn ($query) => $query->where('category_id', (int) $filters['category_id']))
                    ->when(! empty($filters['branch_id']), fn ($query) => $query->where('branch_id', (int) $filters['branch_id']))
                    ->when(! empty($filters['featured']), fn ($query) => $query->where('is_featured', true))
                    ->when(! empty($filters['new_arrival']), fn ($query) => $query->where('is_new_arrival', true))
                    ->when(! empty($filters['premium']), fn ($query) => $query->where('is_premium', true))
                    ->when(! empty($filters['try_on']), fn ($query) => $query->where('is_try_on_enabled', true))
                    ->when($search !== '', function ($query) use ($search) {
                        $query->where(function ($innerQuery) use ($search) {
                            $innerQuery->where('sku', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhereHas('category', function ($categoryQuery) use ($search) {
                                    $categoryQuery->where('name', 'like', "%{$search}%")
                                        ->orWhere('foreign_name', 'like', "%{$search}%");
                                });
                        });
                    });

                match ((string) ($filters['sort'] ?? 'latest')) {
                    'price_asc' => $query->orderBy('price'),
                    'price_desc' => $query->orderByDesc('price'),
                    'name_asc' => $query->orderBy('name'),
                    'name_desc' => $query->orderByDesc('name'),
                    default => $query->orderByDesc('id'),
                };

                return $query;
            },
            fn ($query) => $query->paginate($perPage, ['*'], 'page', $page),
            [CatalogCacheTags::PROMOTIONS, CatalogCacheTags::CURRENCIES],
            $cache
        );
    }

    public function product(int $itemId): Item
    {
        return $this->cache->run(
            CatalogCacheTags::ITEMS,
            $this->itemTtl(),
            fn (bool $cached) => $this->productQuery($cached)->whereKey($itemId),
            fn ($query) => $query->firstOrFail(),
            [CatalogCacheTags::PROMOTIONS, CatalogCacheTags::CURRENCIES]
        );
    }

    /** @return array<string, Collection> */
    public function bootstrapProducts(?int $branchId): array
    {
        $sections = [
            'featured_products' => fn (Builder $query) => $query->where('is_featured', true)->orderBy('sort_order')->orderByDesc('id'),
            'new_arrivals' => fn (Builder $query) => $query->where('is_new_arrival', true)->orderByDesc('id'),
            'best_sellers' => fn (Builder $query) => $query->orderByDesc('review_count')->orderByDesc('rating')->orderByDesc('id'),
            'recommended_products' => fn (Builder $query) => $query
                ->where(fn ($inner) => $inner->where('is_premium', true)->orWhere('is_featured', true))
                ->orderByDesc('is_premium')
                ->orderBy('sort_order')
                ->orderByDesc('id'),
        ];

        $idsBySection = [];
        foreach ($sections as $name => $orderAndFilter) {
            $idsBySection[$name] = $this->cache->run(
                CatalogCacheTags::BOOTSTRAP,
                $this->itemTtl(),
                function (bool $cached) use ($branchId, $orderAndFilter) {
                    $query = Item::query()
                        ->where('status', 'Active')
                        ->where('sale', true)
                        ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));

                    return $orderAndFilter($query)->limit(8);
                },
                fn ($query) => $query->pluck('id'),
                [CatalogCacheTags::ITEMS]
            );
        }

        $allIds = collect($idsBySection)->flatten()->map(fn ($id) => (int) $id)->unique()->values();
        if ($allIds->isEmpty()) {
            return collect($sections)->mapWithKeys(fn ($value, $key) => [$key => new Collection])->all();
        }

        $items = $this->cache->run(
            CatalogCacheTags::BOOTSTRAP,
            $this->itemTtl(),
            fn (bool $cached) => $this->productQuery($cached)->whereIn('id', $allIds),
            fn ($query) => $query->get()->keyBy('id'),
            [CatalogCacheTags::ITEMS, CatalogCacheTags::PROMOTIONS, CatalogCacheTags::CURRENCIES]
        );

        return collect($idsBySection)->mapWithKeys(function (SupportCollection $ids, string $name) use ($items) {
            $ordered = $ids->map(fn ($id) => $items->get((int) $id))->filter()->values();

            return [$name => new Collection($ordered->all())];
        })->all();
    }

    public function activePromotionsForCatalog(): Collection
    {
        $now = Carbon::now();
        $now->setSecond((int) floor($now->second / 30) * 30)->setMicrosecond(0);

        return $this->cache->run(
            CatalogCacheTags::PROMOTIONS,
            (int) config('query-cache.models.promotion', 30),
            function (bool $cached) use ($now) {
                return Promotion::query()
                    ->currentlyActive($now)
                    ->with($this->promotionRelations($cached));
            },
            fn ($query) => $query->get(),
            [CatalogCacheTags::ITEMS, CatalogCacheTags::BOOTSTRAP]
        );
    }

    public function currencies(): Collection
    {
        return $this->currencies->activeCurrencies();
    }

    private function productQuery(bool $cached): Builder
    {
        return Item::query()
            ->with($this->productRelations($cached))
            ->where('status', 'Active')
            ->where('sale', true);
    }

    private function productRelations(bool $cached): array
    {
        $relation = function (string $resource = CatalogCacheTags::ITEMS) use ($cached) {
            return function ($query) use ($resource, $cached) {
                if ($cached) {
                    $this->cache->apply($query, $resource, $this->itemTtl(), [CatalogCacheTags::BOOTSTRAP]);
                } else {
                    $query->dontCache();
                }
            };
        };

        return [
            'category' => $relation(CatalogCacheTags::CATEGORIES),
            'branch' => $relation(CatalogCacheTags::BRANCHES),
            'currency' => $relation(CatalogCacheTags::CURRENCIES),
            'image' => $relation(),
            'galleries' => $relation(),
            'uomGroup' => $relation(),
            'uomGroup.units' => function ($query) use ($cached) {
                $query->where('status', 'Active')->orderBy('sort_order');
                $cached
                    ? $this->cache->apply($query, CatalogCacheTags::ITEMS, $this->itemTtl(), [CatalogCacheTags::BOOTSTRAP])
                    : $query->dontCache();
            },
            'uomGroup.units.unit' => $relation(),
            'uomPrices' => $relation(),
            'optionGroups' => function ($query) use ($cached) {
                $query->where('status', 'Active');
                $cached
                    ? $this->cache->apply($query, CatalogCacheTags::ITEMS, $this->itemTtl(), [CatalogCacheTags::BOOTSTRAP])
                    : $query->dontCache();
            },
            'optionGroups.values' => function ($query) use ($cached) {
                $query->where('status', 'Active');
                $cached
                    ? $this->cache->apply($query, CatalogCacheTags::ITEMS, $this->itemTtl(), [CatalogCacheTags::BOOTSTRAP])
                    : $query->dontCache();
            },
            'variants' => function ($query) use ($cached) {
                $query->where('status', 'Active');
                $cached
                    ? $this->cache->apply($query, CatalogCacheTags::ITEMS, $this->itemTtl(), [CatalogCacheTags::BOOTSTRAP])
                    : $query->dontCache();
            },
            'variants.optionValues' => $relation(),
        ];
    }

    private function promotionRelations(bool $cached): array
    {
        return [
            'activeLines' => function ($query) use ($cached) {
                $cached
                    ? $this->cache->apply($query, CatalogCacheTags::PROMOTIONS, $this->itemTtl(), [CatalogCacheTags::ITEMS])
                    : $query->dontCache();
            },
            'activeLines.item' => function ($query) use ($cached) {
                $cached
                    ? $this->cache->apply($query, CatalogCacheTags::ITEMS, $this->itemTtl(), [CatalogCacheTags::PROMOTIONS])
                    : $query->dontCache();
            },
            'activeLines.item.currency' => function ($query) use ($cached) {
                $cached
                    ? $this->cache->apply($query, CatalogCacheTags::CURRENCIES, $this->metadataTtl(), [CatalogCacheTags::PROMOTIONS])
                    : $query->dontCache();
            },
        ];
    }

    private function simpleRelation(string $relation, string $resource, bool $cached): array
    {
        return [$relation => function ($query) use ($resource, $cached) {
            $cached
                ? $this->cache->apply($query, $resource, $this->metadataTtl(), [CatalogCacheTags::BOOTSTRAP])
                : $query->dontCache();
        }];
    }

    private function cacheableSearch(string $search, int $perPage, int $page): bool
    {
        $length = mb_strlen($search);

        return $length >= 2 && $length <= 64 && $page <= 10 && $perPage <= 50;
    }

    private function itemTtl(): int
    {
        return (int) config('query-cache.models.item', 30);
    }

    private function metadataTtl(): int
    {
        return (int) config('query-cache.default_ttl', 300);
    }
}
