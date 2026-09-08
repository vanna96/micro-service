<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class PriceList extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'code',
        'name',
        'description',
        'header_pricing_method',
        'header_fixed_price',
        'header_discount_percent',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'header_fixed_price' => 'decimal:8',
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

    public function lines()
    {
        return $this->hasMany(PriceListItem::class);
    }

    public function activeLines()
    {
        return $this->lines()->where('status', 'Active');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function getHeaderPricingSummaryAttribute(): ?string
    {
        if ($this->header_pricing_method === 'fixed' && $this->header_fixed_price !== null) {
            return format_currency_amount($this->header_fixed_price, tenant_base_currency()).' for all items';
        }

        if ($this->header_pricing_method === 'discount' && $this->header_discount_percent !== null) {
            return (int) $this->header_discount_percent.'% off all items';
        }

        return null;
    }
}
