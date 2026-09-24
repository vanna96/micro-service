<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemUomPrice extends Model
{
    protected $fillable = [
        'item_id',
        'unit_of_measure_id',
        'reduce_by_percent',
        'price',
        'is_auto',
        'is_active',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'unit_of_measure_id' => 'integer',
        'reduce_by_percent' => 'decimal:2',
        'price' => 'decimal:8',
        'is_auto' => 'boolean',
        'is_active' => 'boolean',
    ];

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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_id');
    }

    public function resolvedPrice(float $itemPrice, float $conversionFactor, ?Currency $currency = null): float
    {
        $basePrice = $itemPrice * $conversionFactor;

        if (! $this->is_auto && $this->price !== null) {
            return round_currency_amount(max(0, (float) $this->price), $currency);
        }

        $reduction = max(0, min(100, (float) $this->reduce_by_percent));

        return round_currency_amount($basePrice * (1 - ($reduction / 100)), $currency);
    }
}
