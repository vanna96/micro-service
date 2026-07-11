<?php

namespace App\Repositories;

use App\Models\Gallery;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantUserRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model = 'App\Models\User';

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->tenantUserModel()
            ->newQuery()
            ->cacheFor($this->userCacheTtl())
            ->cachePrefix($this->tenantUserListingCachePrefix())
            ->cacheTags($this->tenantUserListingCacheTags())
            ->with(['galleries', 'roles'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $this->reportCacheState($query, 'admin.tenant-users.index');

        return $query->get();
    }

    public function loadForAdminEdit(int $userId): User
    {
        $query = $this->tenantUserModel()
            ->newQuery()
            ->with(['galleries', 'profile', 'roles'])
            ->whereKey($userId);

        $this->reportCacheState($query, 'admin.tenant-users.edit');

        return $query->firstOrFail();
    }

    public function createForAdmin(array $attributes): User
    {
        $profile = $attributes['profile'] ?? null;
        $roleIds = $attributes['roles'] ?? [];
        unset($attributes['profile']);
        unset($attributes['roles']);

        $user = $this->tenantUserModel();
        $user->fill($attributes);
        $user->save();
        $this->syncProfileUpload($user, $profile);
        $user->roles()->sync($roleIds);
        User::flushQueryCache();
        Role::flushQueryCache();

        return $user;
    }

    public function updateForAdmin(User $user, array $attributes): User
    {
        $profile = $attributes['profile'] ?? null;
        $roleIds = $attributes['roles'] ?? [];
        unset($attributes['profile']);
        unset($attributes['roles']);

        $user->fill($attributes);
        $user->save();
        $this->syncProfileUpload($user, $profile);
        $user->roles()->sync($roleIds);
        User::flushQueryCache();
        Role::flushQueryCache();

        return $user;
    }

    public function deleteForAdmin(User $user): void
    {
        $this->deleteProfileImage($user);
        $user->roles()->detach();
        $user->delete();
        User::flushQueryCache();
        Role::flushQueryCache();
    }

    protected function syncProfileUpload(User $user, $profile): void
    {
        if (! $profile instanceof UploadedFile) {
            return;
        }

        $this->deleteProfileImage($user);

        $extension = strtolower($profile->getClientOriginalExtension() ?: $profile->extension() ?: 'jpg');
        $fileName = 'tenant_user_' . Str::uuid()->toString() . '.' . $extension;

        Storage::disk('user')->putFileAs('', $profile, $fileName);

        $gallery = $user->galleries()->create([
            'type' => 'thumbnail',
            'status' => 'Active',
            'name' => $fileName,
        ]);

        $user->forceFill(['profile_id' => $gallery->id])->save();
    }

    protected function deleteProfileImage(User $user): void
    {
        if (! $user->profile_id) {
            return;
        }

        $gallery = $user->galleries()->find($user->profile_id);

        if (! $gallery instanceof Gallery) {
            $user->forceFill(['profile_id' => null])->save();

            return;
        }

        if (Storage::disk('user')->exists($gallery->name)) {
            Storage::disk('user')->delete($gallery->name);
        }

        $gallery->delete();
        $user->forceFill(['profile_id' => null])->save();
    }

    protected function userCacheTtl(): int
    {
        return (int) config('query-cache.models.user', config('query-cache.default_ttl', 300));
    }

    protected function tenantUserListingCacheTags(): array
    {
        $tenantTag = tenant()
            ? 'tenant-user-listing:' . tenant()->getTenantKey()
            : 'tenant-user-listing:central';

        return [
            'admin-tenant-user-listing',
            $tenantTag,
        ];
    }

    protected function tenantUserListingCachePrefix(): string
    {
        $tenantKey = tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return (string) config('query-cache.prefix', 'micro_service_backend') . ':tenant-user-listing:' . $tenantKey;
    }

    protected function tenantUserModel(): User
    {
        /** @var \App\Models\User $model */
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
            'ttl' => $this->userCacheTtl(),
        ];

        Log::debug('Tenant user repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
