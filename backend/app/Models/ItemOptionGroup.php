<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rennokki\QueryCache\Traits\QueryCacheable;

class ItemOptionGroup extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'item_id',
        'item_variation_id',
        'name',
        'foreign_name',
        'type',
        'selection_type',
        'is_required',
        'min_selections',
        'max_selections',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'item_variation_id' => 'integer',
        'is_required' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
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

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ItemVariation::class, 'item_variation_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ItemOptionValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
