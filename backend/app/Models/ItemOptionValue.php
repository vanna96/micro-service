<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Rennokki\QueryCache\Traits\QueryCacheable;

class ItemOptionValue extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'item_option_group_id',
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
        'item_option_group_id' => 'integer',
        'price_adjustment' => 'decimal:8',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(ItemOptionGroup::class, 'item_option_group_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ItemVariant::class,
            'item_variant_option_values',
            'item_option_value_id',
            'item_variant_id'
        );
    }
}
