<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class RateIndexValue extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'dataset_type',
        'year',
        'month',
        'day',
        'currency_id',
        'value',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'day' => 'integer',
        'currency_id' => 'integer',
        'value' => 'decimal:8',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (tenant()) {
            $this->setConnection(tenant()->database_connection_name ?: 'tenant');
        } else {
            $this->setConnection('central');
        }
    }

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
