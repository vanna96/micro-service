<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Customer extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'profile_id',
        'price_list_id',
        'code',
        'name',
        'email',
        'phone',
        'address',
        'notes',
        'status',
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

        return null;
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['profile_id'];
    }
}
