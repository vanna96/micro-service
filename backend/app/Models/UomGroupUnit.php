<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rennokki\QueryCache\Traits\QueryCacheable;

class UomGroupUnit extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $table = 'uom_group_units';

    protected $fillable = [
        'uom_group_id',
        'unit_of_measure_id',
        'alternate_quantity',
        'base_quantity',
        'conversion_factor_to_base',
        'is_base_unit',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'uom_group_id' => 'integer',
        'unit_of_measure_id' => 'integer',
        'alternate_quantity' => 'decimal:6',
        'base_quantity' => 'decimal:6',
        'conversion_factor_to_base' => 'decimal:6',
        'is_base_unit' => 'boolean',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }
}
