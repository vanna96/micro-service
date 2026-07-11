<?php
namespace App\Repositories;

use App\Models\Category;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Repositories\RepositoryBase;

/** @package App\Repositories */
class CategoryRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\Category';

    public function list()
    {
        $table = $this->categoryModel()->getTable();
        $query = $this->select("{$table}.*")
            ->with(['galleries', 'parent']);
        return $query;
    }

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->categoryModel()
            ->newQuery()
            ->cacheFor($this->categoryCacheTtl())
            ->cachePrefix($this->categoryListingCachePrefix())
            ->cacheTags($this->categoryListingCacheTags())
            ->with(['parent', 'image'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('parent', function ($parentQuery) use ($search) {
                            $parentQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('foreign_name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id');

        $this->reportCacheState($query, 'admin.categories.index');

        return $query->get();
    }

    public function getParentOptions(?Category $ignoreCategory = null): Collection
    {
        $query = $this->categoryModel()
            ->newQuery()
            ->orderBy('name');

        if ($ignoreCategory instanceof Category && $ignoreCategory->exists) {
            $query->whereKeyNot($ignoreCategory->getKey());
        }

        $this->reportCacheState($query, 'admin.categories.parent_options');

        return $query->get();
    }

    public function loadForAdminEdit(int $categoryId): Category
    {
        $query = $this->categoryModel()
            ->newQuery()
            ->with(['parent', 'galleries', 'image'])
            ->whereKey($categoryId);

        $this->reportCacheState($query, 'admin.categories.edit');

        return $query->firstOrFail();
    }

    public function createForAdmin(array $attributes): Category
    {
        $image = $attributes['image'] ?? null;
        unset($attributes['image']);

        /** @var \App\Models\Category $category */
        $category = $this->categoryModel();
        $category->fill($attributes);
        $category->save();
        $this->syncImageUpload($category, $image);
        Category::flushQueryCache();

        return $category;
    }

    public function updateForAdmin(Category $category, array $attributes): Category
    {
        $image = $attributes['image'] ?? null;
        unset($attributes['image']);

        $category->fill($attributes);
        $category->save();
        $this->syncImageUpload($category, $image);
        Category::flushQueryCache();

        return $category;
    }

    public function deleteForAdmin(Category $category): void
    {
        $this->deleteCategoryImage($category);
        $this->categoryModel()->newQuery()->where('parent_id', $category->getKey())->update(['parent_id' => null]);
        $category->delete();
        Category::flushQueryCache();
    }

    protected function categoryCacheTtl(): int
    {
        return (int) config('query-cache.models.category', config('query-cache.default_ttl', 300));
    }

    protected function categoryListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-category-listing:' . tenant()->getTenantKey()
            : 'tenant-category-listing:central';

        return [
            'admin-category-listing',
            $tenantTag,
        ];
    }

    protected function categoryListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':category-listing:' . $tenantKey;
    }

    protected function syncImageUpload(Category $category, $image): void
    {
        $fileName = $this->storeImageAsset($image, 'category_', 'category');

        if (!$fileName) {
            return;
        }

        $this->deleteCategoryImage($category);

        $gallery = $category->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $category->forceFill(['image_id' => $gallery->id])->save();
    }

    protected function deleteCategoryImage(Category $category): void
    {
        if (!$category->image_id) {
            return;
        }

        $gallery = $category->galleries()->find($category->image_id);

        if (!$gallery instanceof Gallery) {
            $category->forceFill(['image_id' => null])->save();

            return;
        }

        if (Storage::disk('category')->exists($gallery->name)) {
            Storage::disk('category')->delete($gallery->name);
        }

        $gallery->delete();
        $category->forceFill(['image_id' => null])->save();
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

    protected function categoryModel(): Category
    {
        /** @var \App\Models\Category $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function reportCacheState(EloquentBuilder $query, string $context): void
    {
        if (!config('app.debug')) {
            return;
        }

        $baseQuery = $query->getQuery();

        if (!method_exists($baseQuery, 'getCacheKey') || !method_exists($baseQuery, 'getCache')) {
            return;
        }

        $cacheKey = $baseQuery->getCacheKey('get');
        $cacheHit = $baseQuery->getCache()->has($cacheKey);
        $payload = [
            'context' => $context,
            'cache_hit' => $cacheHit,
            'cache_key' => $cacheKey,
            'ttl' => $this->categoryCacheTtl(),
        ];

        Log::debug('Category repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }

}
