<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rennokki\QueryCache\Traits\QueryCacheable;

class OrderItem extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'item_id',
        'sku',
        'name',
        'image_url',
        'quantity',
        'unit_price',
        'line_subtotal',
        'discount_amount',
        'line_total',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'float',
        'line_subtotal' => 'float',
        'discount_amount' => 'float',
        'line_total' => 'float',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
