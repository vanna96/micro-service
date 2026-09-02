<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rennokki\QueryCache\Traits\QueryCacheable;

class ItemVariation extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'name',
        'foreign_name',
        'type',
        'selection_type',
        'is_required',
        'min_selections',
        'max_selections',
        'status',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'min_selections' => 'integer',
        'max_selections' => 'integer',
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

    public function options(): HasMany
    {
        return $this->hasMany(ItemOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function itemOptionGroups(): HasMany
    {
        return $this->hasMany(ItemOptionGroup::class);
    }
}
