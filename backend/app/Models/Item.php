<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Item extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'category_id',
        'branch_id',
        'price_list_id',
        'currency_id',
        'image_id',
        'sku',
        'name',
        'foreign_name',
        'branch_name',
        'description',
        'price',
        'discount_percent',
        'rating',
        'review_count',
        'stock',
        'is_premium',
        'is_featured',
        'is_new_arrival',
        'is_try_on_enabled',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'currency_id' => 'integer',
        'price' => 'decimal:8',
        'rating' => 'decimal:2',
        'is_premium' => 'boolean',
        'is_featured' => 'boolean',
        'is_new_arrival' => 'boolean',
        'is_try_on_enabled' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function galleries()
    {
        return $this->morphMany(Gallery::class, 'gallarieable');
    }

    public function galleryImages()
    {
        return $this->galleries()->where('type', 'galleries');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'image_id');
    }

    public function priceListItems()
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(ItemOptionGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        $image = $this->relationLoaded('image')
            ? $this->getRelation('image')
            : $this->image()->first();

        if ($image && filled($image->name) && Storage::disk('item')->exists($image->name)) {
            return Storage::disk('item')->url($image->name);
        }

        return null;
    }

    public function getFinalPriceAttribute(): float
    {
        $discount = max(0, min(100, (int) $this->discount_percent));

        return round_currency_amount(
            (float) $this->price * (1 - ($discount / 100)),
            $this->resolvedCurrency()
        );
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['image_id'];
    }

    private function resolvedCurrency(): ?Currency
    {
        if ($this->relationLoaded('currency')) {
            return $this->getRelation('currency');
        }

        return $this->currency()->first();
    }
}
