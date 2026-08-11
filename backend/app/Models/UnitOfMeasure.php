<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rennokki\QueryCache\Traits\QueryCacheable;

class UnitOfMeasure extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $table = 'units_of_measure';

    protected $fillable = [
        'uom_group_id',
        'code',
        'name',
        'foreign_name',
        'symbol',
        'alternate_quantity',
        'base_quantity',
        'conversion_factor_to_base',
        'is_base_unit',
        'decimal_places',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'uom_group_id' => 'integer',
        'alternate_quantity' => 'decimal:6',
        'base_quantity' => 'decimal:6',
        'conversion_factor_to_base' => 'decimal:6',
        'is_base_unit' => 'boolean',
        'decimal_places' => 'integer',
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
        return $this->belongsTo(UomGroup::class, 'uom_group_id');
    }
}
