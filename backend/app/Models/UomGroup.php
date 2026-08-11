<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rennokki\QueryCache\Traits\QueryCacheable;

class UomGroup extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $table = 'uom_groups';

    protected $fillable = [
        'code',
        'name',
        'foreign_name',
        'description',
        'base_unit_id',
        'status',
    ];

    protected $casts = [
        'base_unit_id' => 'integer',
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

    public function units(): HasMany
    {
        return $this->hasMany(UomGroupUnit::class, 'uom_group_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_unit_id');
    }
}
