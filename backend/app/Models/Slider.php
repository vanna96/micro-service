<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Slider extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'image_id',
        'title',
        'subtitle',
        'placement',
        'target_url',
        'recommended_dimensions',
        'sort_order',
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

    public function image()
    {
        return $this->belongsTo(Gallery::class, 'image_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $image = $this->relationLoaded('image')
            ? $this->getRelation('image')
            : $this->image()->first();

        if ($image && filled($image->name) && Storage::disk('slider')->exists($image->name)) {
            return Storage::disk('slider')->url($image->name);
        }

        return null;
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['image_id'];
    }
}
