<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Currency extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'sort_order' => 'integer',
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

    public function getInputStepAttribute(): string
    {
        $decimalPlaces = max((int) $this->decimal_places, 0);

        if ($decimalPlaces === 0) {
            return '1';
        }

        return '0.' . str_repeat('0', $decimalPlaces - 1) . '1';
    }

    public function getFormatExampleAttribute(): string
    {
        $decimalPlaces = max((int) $this->decimal_places, 0);

        if ($decimalPlaces === 0) {
            return '250';
        }

        return '2.' . str_repeat('2', $decimalPlaces);
    }
}
