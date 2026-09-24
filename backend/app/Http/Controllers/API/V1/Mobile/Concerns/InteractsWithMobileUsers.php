<?php

namespace App\Http\Controllers\API\V1\Mobile\Concerns;

use App\Models\Gallery;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

trait InteractsWithMobileUsers
{
    protected function centralUserModel(): User
    {
        $user = new User();
        $user->setConnection('central');

        return $user;
    }

    protected function centralUserQuery(): Builder
    {
        return $this->centralUserModel()->newQuery();
    }

    protected function centralUsersTable(): string
    {
        return 'central.' . $this->centralUserModel()->getTable();
    }

    protected function tenantUserQuery(): Builder
    {
        return (new User())->newQuery();
    }

    protected function findCentralUserByLogin(string $login): ?User
    {
        $query = $this->centralUserQuery()->with('tenants');

        foreach ($this->loginFieldCandidates($login) as $candidate) {
            $user = (clone $query)->where(
                Arr::first(array_keys($candidate)),
                Arr::first($candidate)
            )->first();

            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }

    protected function findTenantUserByLogin(string $login): ?User
    {
        foreach ($this->loginFieldCandidates($login) as $candidate) {
            $user = $this->tenantUserQuery()
                ->where(Arr::first(array_keys($candidate)), Arr::first($candidate))
                ->first();

            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }

    protected function currentCentralUser(Request $request): User
    {
        return $this->centralUserQuery()->findOrFail($request->user()->id);
    }

    protected function ensureTenantAccess(User $centralUser): void
    {
        abort_unless(
            $centralUser->tenants()->where('tenants.id', tenant('id'))->exists(),
            403,
            'Unauthorized tenant access'
        );
    }

    protected function attachUserToCurrentTenant(User $centralUser): void
    {
        /** @var \App\Models\Tenant|null $tenant */
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            abort(422, 'Tenant context is required.');
        }

        $centralUser->tenants()->syncWithoutDetaching([$tenant->id]);
    }

    protected function synchronizeTenantUserToCentral(User $tenantUser): User
    {
        $centralUser = $this->findCentralMirrorForTenantUser($tenantUser);
        $attributes = $this->mobileSyncAttributes($tenantUser);

        if ($centralUser) {
            $centralUser->fill($attributes);
            $centralUser->save();
        } else {
            $centralUser = $this->centralUserModel();

            // Preserve tenant user ids when possible so tenant-side mobile data
            // remains aligned with the authenticated central token user.
            if (! $this->centralUserQuery()->whereKey($tenantUser->getKey())->exists()) {
                $centralUser->forceFill(['id' => $tenantUser->getKey()]);
            }

            $centralUser->fill($attributes);
            $centralUser->save();
        }

        $this->attachUserToCurrentTenant($centralUser);
        $this->syncProfileGalleryBetweenUsers($tenantUser, $centralUser);

        return $centralUser->fresh(['tenants']);
    }

    protected function ensureTenantUserMirror(User $centralUser): User
    {
        $tenantUser = $this->tenantUserQuery()->find($centralUser->id);

        if ($tenantUser && $this->tenantProfileShouldRepairCentral($centralUser, $tenantUser)) {
            $centralUser = $this->synchronizeTenantUserToCentral($tenantUser);
        }

        $attributes = $this->mobileSyncAttributes($centralUser);

        if ($tenantUser) {
            $tenantUser->fill($attributes);
            $tenantUser->save();
            $this->syncMirroredProfileGallery($centralUser, $tenantUser);

            return $tenantUser->fresh(['galleries', 'profile']);
        }

        $tenantUser = new User();
        $tenantUser->forceFill(array_merge([
            'id' => $centralUser->id,
        ], $attributes));
        $tenantUser->save();

        $this->syncMirroredProfileGallery($centralUser, $tenantUser);

        return $tenantUser->fresh(['galleries', 'profile']);
    }

    protected function mobileSyncAttributes(User $user): array
    {
        return [
            'name' => $user->name,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'country_code' => $user->country_code,
            'phone' => $user->phone,
            'gender' => $user->gender,
            'dob' => $user->dob,
            'email' => $user->email,
            'password' => $user->password,
            'status' => $user->status ?: 'Active',
        ];
    }

    protected function syncMirroredProfileGallery(User $centralUser, User $tenantUser): void
    {
        $this->syncProfileGalleryBetweenUsers($centralUser, $tenantUser);
    }

    protected function syncProfileGalleryBetweenUsers(User $sourceUser, User $destinationUser): void
    {
        $destinationGalleries = $destinationUser->galleries()->get();

        if (! $sourceUser->profile_id) {
            $destinationGalleries->each->delete();

            if ($destinationUser->profile_id !== null) {
                $destinationUser->forceFill(['profile_id' => null])->save();
            }

            return;
        }

        $sourceGallery = $this->profileGalleryForUser($sourceUser);

        // Avoid destroying the mirrored profile when the source user points to a
        // stale gallery id. A later repair pass can restore consistency safely.
        if (! $sourceGallery instanceof Gallery) {
            return;
        }

        $mirroredGallery = $destinationGalleries->firstWhere('name', $sourceGallery->name);

        if (! $mirroredGallery instanceof Gallery) {
            $mirroredGallery = $destinationUser->galleries()->create([
                'type' => $sourceGallery->type,
                'status' => $sourceGallery->status,
                'name' => $sourceGallery->name,
            ]);
        } else {
            $mirroredGallery->forceFill([
                'type' => $sourceGallery->type,
                'status' => $sourceGallery->status,
                'name' => $sourceGallery->name,
            ])->save();
        }

        $destinationGalleries
            ->filter(fn (Gallery $gallery) => $gallery->id !== $mirroredGallery->id)
            ->each
            ->delete();

        if ((int) $destinationUser->profile_id !== (int) $mirroredGallery->id) {
            $destinationUser->forceFill(['profile_id' => $mirroredGallery->id])->save();
        }
    }

    protected function centralProfileGallery(User $centralUser): ?Gallery
    {
        return $this->profileGalleryForUser($centralUser);
    }

    protected function profileGalleryForUser(User $user): ?Gallery
    {
        if (! $user->profile_id) {
            return null;
        }

        $profile = $user->relationLoaded('profile')
            ? $user->getRelation('profile')
            : null;

        if ($profile instanceof Gallery && (int) $profile->id === (int) $user->profile_id) {
            return $profile;
        }

        return $user->galleries()
            ->whereKey($user->profile_id)
            ->first();
    }

    protected function tenantProfileShouldRepairCentral(User $centralUser, User $tenantUser): bool
    {
        $tenantGallery = $this->profileGalleryForUser($tenantUser);

        if (
            ! $tenantGallery instanceof Gallery ||
            ! filled($tenantGallery->name) ||
            ! Storage::disk('user')->exists($tenantGallery->name)
        ) {
            return false;
        }

        $centralGallery = $this->centralProfileGallery($centralUser);

        if (! $centralGallery instanceof Gallery || ! filled($centralGallery->name)) {
            return true;
        }

        return ! Storage::disk('user')->exists($centralGallery->name);
    }

    protected function findCentralMirrorForTenantUser(User $tenantUser): ?User
    {
        $centralById = $this->centralUserQuery()->with('tenants')->find($tenantUser->getKey());

        if ($centralById instanceof User) {
            return $centralById;
        }

        foreach ([
            ['username', $tenantUser->username],
            ['email', $tenantUser->email],
            ['phone', $tenantUser->phone],
        ] as [$field, $value]) {
            if (! filled($value)) {
                continue;
            }

            $centralUser = $this->centralUserQuery()
                ->with('tenants')
                ->where($field, $value)
                ->first();

            if ($centralUser instanceof User) {
                return $centralUser;
            }
        }

        return null;
    }

    protected function loginFieldCandidates(string $login): array
    {
        $login = trim($login);

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return [['email' => $login]];
        }

        $normalizedPhone = preg_replace('/\D+/', '', $login) ?: '';
        if (
            preg_match('/^\+?[0-9]{8,15}$/', $login) ||
            ($normalizedPhone !== '' && preg_match('/^[0-9]{8,15}$/', $normalizedPhone))
        ) {
            $phones = array_values(array_unique(array_filter([
                $normalizedPhone,
                ltrim($normalizedPhone, '0'),
                $login,
            ])));

            return array_map(static fn (string $phone) => ['phone' => $phone], $phones);
        }

        return [['username' => $login]];
    }

    protected function issueMobileToken(User $centralUser, Request $request): array
    {
        $accessExpiresMinutes = 60;
        $refreshExpiresDays = 30;

        $tokenResult = $centralUser->createToken('mobile-auth', ['*'], now()->addMinutes($accessExpiresMinutes));
        $plainTextToken = $tokenResult->plainTextToken;

        $tokenResult->accessToken->forceFill([
            'tenant_id'   => null,
            'device_name' => 'mobile',
            'device_ip'   => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ])->save();

        $refreshTokenResult = $centralUser->createToken('mobile-refresh', ['issue-token'], now()->addDays($refreshExpiresDays));
        $plainTextRefreshToken = $refreshTokenResult->plainTextToken;

        $refreshTokenResult->accessToken->forceFill([
            'tenant_id'   => null,
            'device_name' => 'mobile',
            'device_ip'   => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ])->save();

        $expiresInSeconds = $accessExpiresMinutes * 60;

        return [
            'token'           => $plainTextToken,
            'access_token'    => $plainTextToken,
            'refresh_token'   => $plainTextRefreshToken,
            'token_type'      => 'Bearer',
            'expires_in'      => $expiresInSeconds,
            'timeout'         => $expiresInSeconds,
            'timeout_minutes' => $accessExpiresMinutes,
        ];
    }

    protected function findPersonalAccessToken(string $tokenString): ?\Laravel\Sanctum\PersonalAccessToken
    {
        foreach (array_unique(['central', config('database.default')]) as $connection) {
            try {
                $model = (new \Laravel\Sanctum\PersonalAccessToken())->setConnection($connection);
                if (! str_contains($tokenString, '|')) {
                    $instance = $model->newQuery()->where('token', hash('sha256', $tokenString))->first();
                } else {
                    [$id, $plain] = explode('|', $tokenString, 2);
                    $instance = $model->newQuery()->find($id);
                    if ($instance && ! hash_equals($instance->token, hash('sha256', $plain))) {
                        $instance = null;
                    }
                }

                if ($instance instanceof \Laravel\Sanctum\PersonalAccessToken) {
                    return $instance;
                }
            } catch (\Throwable $e) {
                // Try next connection
            }
        }

        return \Laravel\Sanctum\PersonalAccessToken::findToken($tokenString);
    }
}
