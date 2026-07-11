<?php

namespace App\Repositories;

use App\Models\Branch;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class BranchRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\Branch';

    public function list()
    {
        $table = $this->branchModel()->getTable();

        return $this->select("{$table}.*");
    }

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->branchModel()
            ->newQuery()
            ->cacheFor($this->branchCacheTtl())
            ->cachePrefix($this->branchListingCachePrefix())
            ->cacheTags($this->branchListingCacheTags())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('foreign_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        $this->reportCacheState($query, 'admin.branches.index');

        return $query->get();
    }

    public function getOptions(?int $selectedBranchId = null): Collection
    {
        $branchKeyName = $this->branchModel()->getKeyName();

        $query = $this->branchModel()
            ->newQuery()
            ->where(function ($innerQuery) use ($selectedBranchId, $branchKeyName) {
                $innerQuery->where('status', 'Active');

                if ($selectedBranchId) {
                    $innerQuery->orWhere($branchKeyName, $selectedBranchId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name');

        $this->reportCacheState($query, 'admin.branches.options');

        return $query->get();
    }

    public function loadForAdminEdit(int $branchId): Branch
    {
        $query = $this->branchModel()
            ->newQuery()
            ->whereKey($branchId);

        $this->reportCacheState($query, 'admin.branches.edit');

        return $query->firstOrFail();
    }

    public function createForAdmin(array $attributes): Branch
    {
        $branch = $this->branchModel();
        $branch->fill($attributes);
        $branch->save();
        Branch::flushQueryCache();

        return $branch;
    }

    public function updateForAdmin(Branch $branch, array $attributes): Branch
    {
        $branch->fill($attributes);
        $branch->save();

        $this->syncBranchLabelOnItems($branch);

        Branch::flushQueryCache();
        Item::flushQueryCache();

        return $branch;
    }

    public function deleteForAdmin(Branch $branch): void
    {
        $branch->items()->update([
            'branch_id' => null,
            'branch_name' => null,
        ]);

        $branch->delete();

        Branch::flushQueryCache();
        Item::flushQueryCache();
    }

    protected function branchCacheTtl(): int
    {
        return (int) config('query-cache.models.branch', config('query-cache.default_ttl', 300));
    }

    protected function branchListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-branch-listing:' . tenant()->getTenantKey()
            : 'tenant-branch-listing:central';

        return [
            'admin-branch-listing',
            $tenantTag,
        ];
    }

    protected function branchListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':branch-listing:' . $tenantKey;
    }

    protected function branchModel(): Branch
    {
        /** @var \App\Models\Branch $model */
        $model = $this->createModel();

        if (tenant()) {
            $model->setConnection(tenant()->database_connection_name);
        }

        return $model;
    }

    protected function syncBranchLabelOnItems(Branch $branch): void
    {
        $branch->items()->update([
            'branch_name' => $branch->name,
        ]);
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
            'ttl' => $this->branchCacheTtl(),
        ];

        Log::debug('Branch repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
