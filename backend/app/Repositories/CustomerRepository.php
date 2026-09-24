<?php

namespace App\Repositories;

use App\Models\Customer;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';

    protected $model = 'App\Models\Customer';

    public function getAdminListing(string $search = '', ?string $type = null): Collection
    {
        $query = $this->customerModel()
            ->newQuery()
            ->cacheFor($this->customerCacheTtl())
            ->cachePrefix($this->customerListingCachePrefix())
            ->cacheTags($this->customerListingCacheTags())
            ->with(['profile', 'priceList'])
            ->when(in_array($type, Customer::TYPES, true), function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('priceList', fn ($priceListQuery) => $priceListQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name')
            ->orderByDesc('id');

        $this->reportCacheState($query, 'admin.customers.index');

        return $query->get();
    }

    public function getTypeCounts(string $search = ''): array
    {
        $baseQuery = $this->customerModel()
            ->newQuery()
            ->cacheFor($this->customerCacheTtl())
            ->cachePrefix($this->customerListingCachePrefix())
            ->cacheTags($this->customerListingCacheTags())
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('priceList', fn ($priceListQuery) => $priceListQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"));
                });
            });

        return [
            'all' => (clone $baseQuery)->count(),
            Customer::TYPE_CUSTOMER => (clone $baseQuery)->where('type', Customer::TYPE_CUSTOMER)->count(),
            Customer::TYPE_VENDOR => (clone $baseQuery)->where('type', Customer::TYPE_VENDOR)->count(),
        ];
    }

    public function loadForAdminEdit(int $customerId): Customer
    {
        $query = $this->customerModel()
            ->newQuery()
            ->with(['galleries', 'profile', 'priceList'])
            ->whereKey($customerId);

        $this->reportCacheState($query, 'admin.customers.edit');

        return $query->firstOrFail();
    }

    public function createForAdmin(array $attributes): Customer
    {
        $profile = $attributes['profile'] ?? null;
        unset($attributes['profile']);

        $customer = $this->customerModel();
        $customer->fill($attributes);
        $customer->save();
        $this->syncProfileUpload($customer, $profile);
        Customer::flushQueryCache();

        return $customer;
    }

    public function updateForAdmin(Customer $customer, array $attributes): Customer
    {
        $profile = $attributes['profile'] ?? null;
        unset($attributes['profile']);

        $customer->fill($attributes);
        $customer->save();
        $this->syncProfileUpload($customer, $profile);
        Customer::flushQueryCache();

        return $customer;
    }

    public function deleteForAdmin(Customer $customer): void
    {
        $this->deleteProfileImage($customer);
        $customer->delete();
        Customer::flushQueryCache();
    }

    protected function syncProfileUpload(Customer $customer, $profile): void
    {
        if (! $profile instanceof UploadedFile) {
            return;
        }

        $this->deleteProfileImage($customer);

        $extension = strtolower($profile->getClientOriginalExtension() ?: $profile->extension() ?: 'jpg');
        $fileName = 'customer_'.Str::uuid()->toString().'.'.$extension;

        Storage::disk('customer')->putFileAs('', $profile, $fileName);

        $gallery = $customer->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $customer->forceFill(['profile_id' => $gallery->id])->save();
    }

    protected function deleteProfileImage(Customer $customer): void
    {
        if (! $customer->profile_id) {
            return;
        }

        $gallery = $customer->galleries()->find($customer->profile_id);

        if (! $gallery instanceof Gallery) {
            $customer->forceFill(['profile_id' => null])->save();

            return;
        }

        if (Storage::disk('customer')->exists($gallery->name)) {
            Storage::disk('customer')->delete($gallery->name);
        }

        $gallery->delete();
        $customer->forceFill(['profile_id' => null])->save();
    }

    protected function customerCacheTtl(): int
    {
        return (int) config('query-cache.models.customer', config('query-cache.default_ttl', 300));
    }

    protected function customerListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-customer-listing:'.tenant()->getTenantKey()
            : 'tenant-customer-listing:central';

        return [
            'admin-customer-listing',
            $tenantTag,
        ];
    }

    protected function customerListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend').':customer-listing:'.$tenantKey;
    }

    protected function customerModel(): Customer
    {
        /** @var \App\Models\Customer $model */
        $model = $this->createModel();

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
            'ttl' => $this->customerCacheTtl(),
        ];

        Log::debug('Customer repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
