<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Builder;
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
        'item_type',
        'uom_group_id',
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
        'rating',
        'review_count',
        'stock',
        'stock_control',
        'purchase',
        'sale',
        'is_purchase',
        'is_sale',
        'is_premium',
        'is_featured',
        'is_new_arrival',
        'is_try_on_enabled',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'uom_group_id' => 'integer',
        'currency_id' => 'integer',
        'price' => 'decimal:8',
        'rating' => 'decimal:2',
        'stock_control' => 'boolean',
        'purchase' => 'boolean',
        'sale' => 'boolean',
        'is_purchase' => 'boolean',
        'is_sale' => 'boolean',
        'is_premium' => 'boolean',
        'is_featured' => 'boolean',
        'is_new_arrival' => 'boolean',
        'is_try_on_enabled' => 'boolean',
    ];

    protected $attributes = [
        'purchase' => true,
        'sale' => true,
        'stock_control' => true,
        'status' => 'Active',
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

    public function uomGroup(): BelongsTo
    {
        return $this->belongsTo(UomGroup::class);
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

    public function uomPrices(): HasMany
    {
        return $this->hasMany(ItemUomPrice::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        $image = $this->relationLoaded('image')
            ? $this->getRelation('image')
            : $this->image()->first();

        if ($image && str_starts_with((string) $image->name, 'assets/') && is_file(public_path($image->name))) {
            $baseUrl = app()->runningInConsole()
                ? rtrim((string) config('app.url'), '/')
                : request()->getSchemeAndHttpHost();

            return $baseUrl.'/'.ltrim($image->name, '/');
        }

        if ($image && filled($image->name) && Storage::disk('item')->exists($image->name)) {
            return Storage::disk('item')->url($image->name);
        }

        return null;
    }

    public function getIsPurchaseAttribute(): bool
    {
        return (bool) ($this->attributes['purchase'] ?? true);
    }

    public function setIsPurchaseAttribute($value): void
    {
        $this->attributes['purchase'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function getIsSaleAttribute(): bool
    {
        return (bool) ($this->attributes['sale'] ?? true);
    }

    public function setIsSaleAttribute($value): void
    {
        $this->attributes['sale'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function scopePurchase(Builder $query): Builder
    {
        return $query->where('purchase', true);
    }

    public function scopeSale(Builder $query): Builder
    {
        return $query->where('sale', true);
    }

    public function scopeForPurchase(Builder $query): Builder
    {
        return $query->where('purchase', true);
    }

    public function scopeForSale(Builder $query): Builder
    {
        return $query->where('sale', true);
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['image_id'];
    }
}
