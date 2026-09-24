<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSalePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pos_sale_id',
        'method',
        'currency_code',
        'currency_symbol',
        'decimal_places',
        'amount',
        'exchange_rate',
        'amount_base',
        'provider',
        'reference',
        'metadata',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'amount' => 'decimal:8',
        'exchange_rate' => 'decimal:8',
        'amount_base' => 'decimal:8',
        'metadata' => 'array',
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
}
