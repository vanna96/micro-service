<?php

namespace App\Repositories;

use App\Models\Gallery;
use App\Models\Item;
use App\Models\Promotion;
use App\Models\PromotionItem;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PromotionRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\Promotion';

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->promotionModel()
            ->newQuery()
            ->cacheFor($this->promotionCacheTtl())
            ->cachePrefix($this->promotionListingCachePrefix())
            ->cacheTags($this->promotionListingCacheTags())
            ->with(['image'])
            ->withCount('activeLines')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('start_at')
            ->orderByDesc('id');

        $this->reportCacheState($query, 'admin.promotions.index');

        return $query->get();
    }

    public function loadForAdminEdit(int $promotionId): Promotion
    {
        $query = $this->promotionModel()
            ->newQuery()
            ->with([
                'image',
                'lines' => fn ($lineQuery) => $lineQuery->with('item')->orderByDesc('id'),
                'activeLines',
            ])
            ->whereKey($promotionId);

        $this->reportCacheState($query, 'admin.promotions.edit');

        return $query->firstOrFail();
    }

    public function getItemOptions(): Collection
    {
        $query = $this->itemModel()
            ->newQuery()
            ->with('currency')
            ->where('status', 'Active')
            ->orderBy('name');

        $this->reportCacheState($query, 'admin.promotions.item-options');

        return $query->get();
    }

    public function createForAdmin(array $attributes): Promotion
    {
        return DB::connection($this->tenantConnectionName())->transaction(function () use ($attributes) {
            $image = $attributes['image'] ?? null;
            unset($attributes['image']);
            $attributes = $this->normalizePromotionAttributes($attributes);
            $promotion = $this->promotionModel();
            $promotion->fill($attributes);
            $promotion->save();
            $this->syncImageUpload($promotion, $image);

            $this->flushRelatedCaches();

            return $promotion;
        });
    }

    public function updateForAdmin(Promotion $promotion, array $attributes): Promotion
    {
        return DB::connection($this->tenantConnectionName())->transaction(function () use ($promotion, $attributes) {
            $image = $attributes['image'] ?? null;
            unset($attributes['image']);
            $attributes = $this->normalizePromotionAttributes($attributes);
            $promotion->fill($attributes);
            $promotion->save();
            $this->syncImageUpload($promotion, $image);

            $this->flushRelatedCaches();

            return $promotion;
        });
    }

    public function deleteForAdmin(Promotion $promotion): void
    {
        $this->deletePromotionImage($promotion);
        $promotion->delete();

        $this->flushRelatedCaches();
    }

    public function createLineForAdmin(Promotion $promotion, array $attributes): PromotionItem
    {
        $attributes = $this->normalizeLineAttributes($promotion, $attributes);

        $line = $promotion->lines()->create($attributes);

        $this->flushRelatedCaches();

        return $line;
    }

    public function updateLineForAdmin(PromotionItem $line, array $attributes): PromotionItem
    {
        $attributes['item_id'] = $attributes['item_id'] ?? $line->item_id;
        $attributes = $this->normalizeLineAttributes($line->promotion, $attributes);

        $line->fill($attributes);
        $line->save();

        $this->flushRelatedCaches();

        return $line;
    }

    public function deleteLineForAdmin(PromotionItem $line): void
    {
        $line->delete();

        $this->flushRelatedCaches();
    }

    protected function normalizePromotionAttributes(array $attributes): array
    {
        $type = $attributes['type'] ?? Promotion::TYPE_ITEM_PRICE;

        if ($type === Promotion::TYPE_SUBTOTAL_DISCOUNT && array_key_exists('threshold_amount', $attributes)) {
            $attributes['threshold_amount'] = format_currency_input(
                $attributes['threshold_amount'],
                tenant_base_currency(),
                2
            );
        }

        $attributes['threshold_amount'] = $type === Promotion::TYPE_SUBTOTAL_DISCOUNT
            ? ($attributes['threshold_amount'] ?? null)
            : null;
        $attributes['reward_discount_percent'] = $type === Promotion::TYPE_SUBTOTAL_DISCOUNT
            ? ($attributes['reward_discount_percent'] ?? null)
            : null;
        $attributes['buy_quantity'] = $type === Promotion::TYPE_BOGO
            ? ($attributes['buy_quantity'] ?? null)
            : null;
        $attributes['get_quantity'] = $type === Promotion::TYPE_BOGO
            ? ($attributes['get_quantity'] ?? null)
            : null;

        return $attributes;
    }

    protected function normalizeLineAttributes(Promotion $promotion, array $attributes): array
    {
        if ($promotion->type === Promotion::TYPE_BOGO) {
            $attributes['line_role'] = $attributes['line_role'] ?? Promotion::BOGO_ROLE_BUY;
            $attributes['pricing_method'] = null;
            $attributes['fixed_price'] = null;
            $attributes['discount_percent'] = null;

            return $attributes;
        }

        if ($promotion->type !== Promotion::TYPE_ITEM_PRICE) {
            $attributes['line_role'] = 'item';
            $attributes['pricing_method'] = null;
            $attributes['fixed_price'] = null;
            $attributes['discount_percent'] = null;

            return $attributes;
        }

        $attributes['line_role'] = 'item';

        if (array_key_exists('fixed_price', $attributes) && ($attributes['pricing_method'] ?? null) === 'fixed') {
            $item = ! empty($attributes['item_id'])
                ? $this->itemModel()->newQuery()->with('currency')->find($attributes['item_id'])
                : null;
            $attributes['fixed_price'] = format_currency_input($attributes['fixed_price'], $item?->currency, 2);
        }

        if (($attributes['pricing_method'] ?? null) === 'fixed') {
            $attributes['discount_percent'] = null;
        }

        if (($attributes['pricing_method'] ?? null) === 'discount') {
            $attributes['fixed_price'] = null;
        }

        return $attributes;
    }

    protected function flushRelatedCaches(): void
    {
        Promotion::flushQueryCache();
        PromotionItem::flushQueryCache();
        Item::flushQueryCache();
    }

    protected function syncImageUpload(Promotion $promotion, $image): void
    {
        if (! $image instanceof UploadedFile) {
            return;
        }

        $this->deletePromotionImage($promotion);

        $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');
        $fileName = 'promotion_' . Str::uuid()->toString() . '.' . $extension;

        Storage::disk('promotion')->putFileAs('', $image, $fileName);

        $gallery = $promotion->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $promotion->forceFill(['image_id' => $gallery->id])->save();
    }

    protected function deletePromotionImage(Promotion $promotion): void
    {
        if (! $promotion->image_id) {
            return;
        }

        $gallery = $promotion->galleries()->find($promotion->image_id);

        if (! $gallery instanceof Gallery) {
            $promotion->forceFill(['image_id' => null])->save();

            return;
        }

        if (Storage::disk('promotion')->exists($gallery->name)) {
            Storage::disk('promotion')->delete($gallery->name);
        }

        $gallery->delete();
        $promotion->forceFill(['image_id' => null])->save();
    }

    protected function promotionCacheTtl(): int
    {
        return (int) config('query-cache.models.promotion', config('query-cache.default_ttl', 300));
    }

    protected function promotionListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-promotion-listing:' . tenant()->getTenantKey()
            : 'tenant-promotion-listing:central';

        return [
            'admin-promotion-listing',
            $tenantTag,
        ];
    }

    protected function promotionListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':promotion-listing:' . $tenantKey;
    }

    protected function tenantConnectionName(): string
    {
        if (! tenant()) {
            return 'central';
        }

        return tenant()->database_connection_name ?: 'tenant';
    }

    protected function promotionModel(): Promotion
    {
        /** @var \App\Models\Promotion $model */
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
            'ttl' => $this->promotionCacheTtl(),
        ];

        Log::debug('Promotion repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
