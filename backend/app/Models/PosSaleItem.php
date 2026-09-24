<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_sale_id',
        'item_id',
        'item_variant_id',
        'sku',
        'name',
        'uom_code',
        'uom_name',
        'selected_options',
        'quantity',
        'currency_code',
        'unit_price',
        'exchange_rate',
        'unit_price_base',
        'line_subtotal_base',
        'discount_base',
        'tax_base',
        'line_total_base',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'item_variant_id' => 'integer',
        'selected_options' => 'array',
        'quantity' => 'decimal:8',
        'unit_price' => 'decimal:8',
        'exchange_rate' => 'decimal:8',
        'unit_price_base' => 'decimal:8',
        'line_subtotal_base' => 'decimal:8',
        'discount_base' => 'decimal:8',
        'tax_base' => 'decimal:8',
        'line_total_base' => 'decimal:8',
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

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PosSale::class, 'pos_sale_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id');
    }
}
