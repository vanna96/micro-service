<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rennokki\QueryCache\Traits\QueryCacheable;

class ItemOption extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'item_variation_id',
        'name',
        'foreign_name',
        'sku_suffix',
        'color_hex',
        'price_adjustment',
        'is_default',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'item_variation_id' => 'integer',
        'price_adjustment' => 'float',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection(tenant() ? tenant()->database_connection_name : 'central');
    }

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ItemVariation::class, 'item_variation_id');
    }
}
