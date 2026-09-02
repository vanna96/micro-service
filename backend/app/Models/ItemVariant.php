<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Rennokki\QueryCache\Traits\QueryCacheable;

class ItemVariant extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'item_id',
        'sku',
        'barcode',
        'name',
        'price',
        'stock',
        'is_default',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'price' => 'decimal:8',
        'stock' => 'integer',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
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

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemOptionValue::class,
            'item_variant_option_values',
            'item_variant_id',
            'item_option_value_id'
        )->with('group')->orderBy('item_option_values.sort_order')->orderBy('item_option_values.id');
    }

    public function getResolvedPriceAttribute(): float
    {
        return (float) ($this->price ?? $this->item?->price ?? 0);
    }
}
