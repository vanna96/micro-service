<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Rennokki\QueryCache\Traits\QueryCacheable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, QueryCacheable, UsesQueryCache, LogsTenantActivity;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'profile_id',
        'email',
        'password',    
        'username', 
        'first_name',
        'last_name',
        'country_code',
        'phone',
        'gender',
        'dob',
        'status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'profile_id' => 'integer',
        'email_verified_at' => 'datetime',
    ];

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (tenant()) {
            $this->setConnection(tenant()->database_connection_name);

            return;
        }

        if (app()->bound('session')) {
            $session = app('session');
            $tenantScope = (string) $session->get('auth_user_scope', '');
            $tenantId = (string) $session->get('auth_tenant_id', '');

            if ($tenantScope === 'tenant' && $tenantId !== '') {
                $tenantModel = Tenant::query()
                    ->where('id', $tenantId)
                    ->where('status', 'Active')
                    ->first();

                if ($tenantModel) {
                    if (! tenant()) {
                        tenancy()->initialize($tenantModel);
                    }

                    if (tenant() && filled(tenant()->database_connection_name)) {
                        $this->setConnection(tenant()->database_connection_name);

                        return;
                    }
                }

                if ($tenantModel && filled($tenantModel->database_connection_name)) {
                    $this->setConnection($tenantModel->database_connection_name);

                    return;
                }
            }
        }

        $this->setConnection('central');
    }
    
    public function galleries()
    {
        return $this->morphMany(Gallery::class, 'gallarieable');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'profile_id');
    }

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'user_tenants', 'user_id', 'tenant_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_user', 'user_id', 'role_id')
            ->join('permission_role', 'role_user.role_id', '=', 'permission_role.role_id')
            ->whereColumn('permissions.id', 'permission_role.permission_id')
            ->select('permissions.*')
            ->distinct();
    }

    public function getProfileImageUrlAttribute(): string
    {
        $profile = $this->relationLoaded('profile')
            ? $this->getRelation('profile')
            : $this->profile()->first();

        if ($profile && filled($profile->name)) {
            try {
                if (Storage::disk('user')->exists($profile->name)) {
                    return Storage::disk('user')->url($profile->name);
                }
            } catch (\Throwable $exception) {
                return Storage::disk('user')->url($profile->name);
            }
        }

        return global_asset('minible/assets/images/users/avatar-4.jpg');
    }

    protected function activityLogName(): string
    {
        return tenant() ? 'tenant_user' : 'user';
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['profile_id'];
    }

    public function hasTenantPermission(string $permission): bool
    {
        if (! tenant()) {
            return false;
        }

        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })
            ->exists();
    }
}
