<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_token',
        'sale_key',
        'status',
        'reference',
        'invoice_number',
        'customer_id',
        'user_id',
        'sale_from',
        'customer_code',
        'customer_name',
        'order_type',
        'base_currency_code',
        'item_count',
        'discount_type',
        'discount_value',
        'promotion_id',
        'promotion_name',
        'promotion_discount_base',
        'tax_percent',
        'subtotal_base',
        'discount_base',
        'tax_base',
        'service_fee_base',
        'total_base',
        'payment_method',
        'cash_received_base',
        'change_base',
        'completed_at',
        'stock_deducted_at',
        'notes',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'customer_id' => 'integer',
        'user_id' => 'integer',
        'promotion_id' => 'integer',
        'item_count' => 'integer',
        'discount_value' => 'decimal:8',
        'promotion_discount_base' => 'decimal:8',
        'tax_percent' => 'decimal:8',
        'subtotal_base' => 'decimal:8',
        'discount_base' => 'decimal:8',
        'tax_base' => 'decimal:8',
        'service_fee_base' => 'decimal:8',
        'total_base' => 'decimal:8',
        'cash_received_base' => 'decimal:8',
        'change_base' => 'decimal:8',
        'completed_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosSalePayment::class);
    }
}
