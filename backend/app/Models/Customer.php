<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_VENDOR = 'vendor';
    public const TYPES = [self::TYPE_CUSTOMER, self::TYPE_VENDOR];

    protected $fillable = [
        'profile_id',
        'price_list_id',
        'code',
        'type',
        'name',
        'username',
        'email',
        'facebook_id',
        'facebook_avatar_url',
        'google_id',
        'google_avatar_url',
        'password',
        'first_name',
        'last_name',
        'country_code',
        'phone',
        'gender',
        'dob',
        'address',
        'notes',
        'status',
    ];

    protected $attributes = [
        'type' => self::TYPE_CUSTOMER,
        'status' => 'Active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code_hash',
    ];

    protected $casts = [
        'profile_id' => 'integer',
        'price_list_id' => 'integer',
        'dob' => 'date',
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'email_verification_sent_at' => 'datetime',
        'email_verification_attempts' => 'integer',
    ];

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (tenant()) {
            $this->setConnection(tenant()->database_connection_name ?: 'tenant');
        } else {
            $this->setConnection('central');
        }
    }

    public function galleries()
    {
        return $this->morphMany(Gallery::class, 'gallarieable');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'profile_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function getProfileImageUrlAttribute(): ?string
    {
        $profile = $this->relationLoaded('profile')
            ? $this->getRelation('profile')
            : $this->profile()->first();

        if ($profile && filled($profile->name) && Storage::disk('customer')->exists($profile->name)) {
            return Storage::disk('customer')->url($profile->name);
        }

        if (filled($this->facebook_avatar_url)) {
            return (string) $this->facebook_avatar_url;
        }

        return filled($this->google_avatar_url) ? (string) $this->google_avatar_url : null;
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CUSTOMER);
    }

    public function scopeVendors(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_VENDOR);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return $type ? $query->where('type', $type) : $query;
    }

    public function isCustomer(): bool
    {
        return ($this->type ?? self::TYPE_CUSTOMER) === self::TYPE_CUSTOMER;
    }

    public function isVendor(): bool
    {
        return ($this->type ?? null) === self::TYPE_VENDOR;
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['profile_id'];
    }
}
