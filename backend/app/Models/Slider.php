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
        'media_type',
        'media_url',
        'badge',
        'badge_bg',
        'badge_color',
        'discount',
        'gradient',
        'icon',
        'tag',
        'target_url',
        'recommended_dimensions',
        'sort_order',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    public function toPromoSlidePayload(): array
    {
        $computedType = (string) ($this->media_type ?: ($this->image_id ? 'image' : ($this->media_url ? 'video' : 'gradient')));
        if ($computedType === 'image') {
            $rawMediaUrl = (string) ($this->image_url ?: ($this->media_url ?: ''));
        } else {
            $rawMediaUrl = (string) ($this->media_url ?: ($this->image_url ?: ''));
        }
        if ($rawMediaUrl && preg_match('#^https?://[^/]+/uploads/#', $rawMediaUrl)) {
            $rawMediaUrl = preg_replace('#^https?://[^/]+/uploads/#', '/uploads/', $rawMediaUrl);
        }

        return [
            'id' => (string) ($this->id ?: ''),
            'type' => (string) ($this->media_type ?: ($this->image_id ? 'image' : ($this->media_url ? 'video' : 'gradient'))),
            'badge' => (string) ($this->badge ?: ($this->subtitle ?: 'PROMOTION')),
            'badgeBg' => (string) ($this->badge_bg ?: 'rgba(255, 255, 255, 0.95)'),
            'badgeColor' => (string) ($this->badge_color ?: '#0f172a'),
            'title' => (string) ($this->title ?: 'Special Store Offer'),
            'subtitle' => (string) ($this->subtitle ?: ''),
            'discount' => (string) ($this->discount ?: ''),
            'mediaUrl' => $rawMediaUrl,
            'media_url' => $rawMediaUrl,
            'image_url' => $rawMediaUrl,
            'image' => $rawMediaUrl,
            'gradient' => (string) ($this->gradient ?: 'linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4338ca 100%)'),
            'icon' => (string) ($this->icon ?: ($this->media_type === 'video' ? 'ri-video-line' : 'ri-gift-line')),
            'tag' => (string) ($this->tag ?: 'Available at current station'),
            'placement' => (string) ($this->placement ?: 'second_screen'),
            'sort_order' => (int) $this->sort_order,
            'status' => (string) ($this->status ?: 'Active'),
        ];
    }

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
            $url = Storage::disk('slider')->url($image->name);
            if (preg_match('#^https?://[^/]+/uploads/#', $url)) {
                return preg_replace('#^https?://[^/]+/uploads/#', '/uploads/', $url);
            }
            return $url;
        }

        return null;
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['image_id'];
    }
}
