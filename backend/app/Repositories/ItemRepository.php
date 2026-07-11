<?php

namespace App\Repositories;

use App\Models\Branch;
use App\Models\Gallery;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ItemRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\Item';

    public function list()
    {
        $table = $this->itemModel()->getTable();

        return $this->select("{$table}.*")
            ->with(['branch', 'category', 'currency', 'galleries', 'image']);
    }

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->itemModel()
            ->newQuery()
            ->cacheFor($this->itemCacheTtl())
            ->cachePrefix($this->itemListingCachePrefix())
            ->cacheTags($this->itemListingCacheTags())
            ->with(['branch', 'category', 'currency', 'image'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('branch_name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('branch', function ($branchQuery) use ($search) {
                            $branchQuery->where('code', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%")
                                ->orWhere('location', 'like', "%{$search}%");
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('sort_order')
            ->orderByDesc('id');

        $this->reportCacheState($query, 'admin.items.index');

        return $query->get();
    }

    public function loadForAdminEdit(int $itemId): Item
    {
        $query = $this->itemModel()
            ->newQuery()
            ->with(['branch', 'category', 'currency', 'galleries', 'image'])
            ->whereKey($itemId);

        $this->reportCacheState($query, 'admin.items.edit');

        return $query->firstOrFail();
    }

    public function createForAdmin(array $attributes): Item
    {
        $image = $attributes['image'] ?? null;
        $galleryImages = $attributes['gallery_images'] ?? [];
        unset($attributes['image']);
        unset($attributes['gallery_images']);

        /** @var \App\Models\Item $item */
        $item = $this->itemModel();
        $item->fill($this->syncBranchAttributes($attributes));
        $item->save();
        $this->syncImageUpload($item, $image);
        $this->syncGalleryUploads($item, $galleryImages);
        Item::flushQueryCache();

        return $item;
    }

    public function updateForAdmin(Item $item, array $attributes): Item
    {
        $image = $attributes['image'] ?? null;
        $galleryImages = $attributes['gallery_images'] ?? [];
        unset($attributes['image']);
        unset($attributes['gallery_images']);

        $item->fill($this->syncBranchAttributes($attributes));
        $item->save();
        $this->syncImageUpload($item, $image);
        $this->syncGalleryUploads($item, $galleryImages);
        Item::flushQueryCache();

        return $item;
    }

    public function deleteForAdmin(Item $item): void
    {
        $this->deleteItemImage($item);
        $this->deleteItemGalleryImages($item);
        $item->delete();
        Item::flushQueryCache();
    }

    protected function itemCacheTtl(): int
    {
        return (int) config('query-cache.models.item', config('query-cache.default_ttl', 300));
    }

    protected function itemListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-item-listing:' . tenant()->getTenantKey()
            : 'tenant-item-listing:central';

        return [
            'admin-item-listing',
            $tenantTag,
        ];
    }

    protected function itemListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':item-listing:' . $tenantKey;
    }

    protected function syncImageUpload(Item $item, $image): void
    {
        $fileName = $this->storeImageAsset($image, 'item_', 'item');

        if (! $fileName) {
            return;
        }

        $this->deleteItemImage($item);

        $gallery = $item->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $item->forceFill(['image_id' => $gallery->id])->save();
    }

    protected function deleteItemImage(Item $item): void
    {
        if (! $item->image_id) {
            return;
        }

        $gallery = $item->galleries()->find($item->image_id);

        if (! $gallery instanceof Gallery) {
            $item->forceFill(['image_id' => null])->save();

            return;
        }

        if (Storage::disk('item')->exists($gallery->name)) {
            Storage::disk('item')->delete($gallery->name);
        }

        $gallery->delete();
        $item->forceFill(['image_id' => null])->save();
    }

    protected function syncGalleryUploads(Item $item, array $galleryImages): void
    {
        foreach ($galleryImages as $galleryImage) {
            $fileName = $this->storeImageAsset($galleryImage, 'item_gallery_', 'item');

            if (! $fileName) {
                continue;
            }

            $item->galleries()->create([
                'type' => 'galleries',
                'status' => 'Active',
                'name' => $fileName,
            ]);
        }
    }

    public function deleteGalleryImage(Item $item, int $galleryId): void
    {
        /** @var \App\Models\Gallery|null $gallery */
        $gallery = $item->galleries()
            ->where('type', 'galleries')
            ->find($galleryId);

        if (! $gallery instanceof Gallery) {
            return;
        }

        if (Storage::disk('item')->exists($gallery->name)) {
            Storage::disk('item')->delete($gallery->name);
        }

        $gallery->delete();
        Item::flushQueryCache();
    }

    protected function storeImageAsset($image, string $prefix, string $disk): ?string
    {
        if ($image instanceof UploadedFile) {
            $extension = strtolower($image->getClientOriginalExtension() ?: $image->extension() ?: 'jpg');
            $fileName = $prefix . Str::uuid()->toString() . '.' . $extension;

            Storage::disk($disk)->putFileAs('', $image, $fileName);

            return $fileName;
        }

        if (! is_string($image) || ! preg_match("/^data:image\/(\w+);base64,/", $image, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1] ?? 'png');
        $imageData = base64_decode(preg_replace("/^data:image\/\w+;base64,/", '', $image), true);

        if ($imageData === false) {
            return null;
        }

        $fileName = $prefix . Str::uuid()->toString() . '.' . $extension;
        Storage::disk($disk)->put($fileName, $imageData);

        return $fileName;
    }

    protected function deleteItemGalleryImages(Item $item): void
    {
        $item->galleries()
            ->where('type', 'galleries')
            ->get()
            ->each(function (Gallery $gallery) {
                if (Storage::disk('item')->exists($gallery->name)) {
                    Storage::disk('item')->delete($gallery->name);
                }

                $gallery->delete();
            });
    }

    protected function itemModel(): Item
    {
        /** @var \App\Models\Item $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function syncBranchAttributes(array $attributes): array
    {
        $branchId = $attributes['branch_id'] ?? null;

        if (! $branchId) {
            $attributes['branch_id'] = null;

            if (! array_key_exists('branch_name', $attributes)) {
                $attributes['branch_name'] = null;
            }

            return $attributes;
        }

        $branch = $this->branchModel()->newQuery()->find($branchId);

        if ($branch instanceof Branch) {
            $attributes['branch_name'] = $branch->name;
        }

        return $attributes;
    }

    protected function branchModel(): Branch
    {
        $model = new Branch();

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

        Log::debug('Query cache state', [
            'context' => $context,
            'cache_hit' => $cacheHit,
            'cache_key' => $cacheKey,
            'ttl' => $this->itemCacheTtl(),
        ]);
    }
}
