<?php
namespace App\Repositories;

use App\Models\Gallery;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use App\Repositories\RepositoryBase;

/** @package App\Repositories */
class UserRepository extends RepositoryBase
{
    protected $repositoryId = 'rinvex.repository.id';
    protected $model        = 'App\Models\User';

    public function list()
    {
        $table = $this->createModel()->getTable();
        $query = $this->select("{$table}.*")
                    ->cacheFor($this->userCacheTtl())
                    ->cacheTags($this->userCacheTags())
                    ->with(['galleries', 'tenants']);
        return $query;
    }

    public function getAdminListing(string $search = ''): Collection
    {
        $query = $this->createModel()
            ->newQuery()
            ->cacheFor($this->userCacheTtl())
            ->cacheTags($this->userCacheTags())
            ->with('tenants')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id');

        $this->reportCacheState($query, 'admin.users.index');

        return $query->get();
    }

    public function getTenantOptions(): Collection
    {
        $query = Tenant::query()
            ->cacheFor($this->tenantCacheTtl())
            ->cacheTags($this->tenantCacheTags())
            ->orderBy('id');

        $this->reportCacheState($query, 'admin.users.tenant_options');

        return $query->get();
    }

    public function loadForAdminEdit(User $user): User
    {
        $query = $this->createModel()
            ->newQuery()
            ->cacheFor($this->userCacheTtl())
            ->cacheTags($this->userCacheTags())
            ->with(['tenants', 'galleries', 'profile'])
            ->whereKey($user->getKey());

        $this->reportCacheState($query, 'admin.users.edit');

        return $query->firstOrFail();
    }

    public function createForAdmin(array $attributes): User
    {
        $tenantIds = $attributes['tenants'] ?? [];
        $profile = $attributes['profile'] ?? null;
        unset($attributes['tenants']);
        unset($attributes['profile']);

        /** @var \App\Models\User $user */
        $user = $this->createModel();
        $user->fill($attributes);
        $user->save();
        $this->syncProfileUpload($user, $profile);
        $user->tenants()->sync($tenantIds);
        User::flushQueryCache();
        Tenant::flushQueryCache();

        return $user;
    }

    public function updateForAdmin(User $user, array $attributes): User
    {
        $tenantIds = $attributes['tenants'] ?? [];
        $profile = $attributes['profile'] ?? null;
        unset($attributes['tenants']);
        unset($attributes['profile']);

        $user->fill($attributes);
        $user->save();
        $this->syncProfileUpload($user, $profile);
        $user->tenants()->sync($tenantIds);
        User::flushQueryCache();
        Tenant::flushQueryCache();

        return $user;
    }

    public function deleteForAdmin(User $user): void
    {
        $this->deleteProfileImage($user);
        $user->tenants()->detach();
        $user->delete();
        User::flushQueryCache();
        Tenant::flushQueryCache();
    }

    protected function userCacheTtl(): int
    {
        return (int) config('query-cache.models.user', config('query-cache.default_ttl', 300));
    }

    protected function tenantCacheTtl(): int
    {
        return (int) config('query-cache.models.tenant', config('query-cache.default_ttl', 300));
    }

    protected function userCacheTags(): array
    {
        return ['users', 'admin-users'];
    }

    protected function tenantCacheTags(): array
    {
        return ['tenants', 'admin-tenants'];
    }

    protected function syncProfileUpload(User $user, $profile): void
    {
        if (! $profile instanceof UploadedFile) {
            return;
        }

        $this->deleteProfileImage($user);

        $extension = strtolower($profile->getClientOriginalExtension() ?: $profile->extension() ?: 'jpg');
        $fileName = 'user_' . Str::uuid()->toString() . '.' . $extension;

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
            'ttl' => $context === 'admin.users.tenant_options' ? $this->tenantCacheTtl() : $this->userCacheTtl(),
        ];

        Log::debug('User repository query cache state', $payload);

        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class) && app()->bound('debugbar')) {
            \Barryvdh\Debugbar\Facades\Debugbar::addMessage($payload, 'query-cache');
        }
    }
}
