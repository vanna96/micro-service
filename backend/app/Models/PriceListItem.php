<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class PriceListItem extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'price_list_id',
        'item_id',
        'pricing_method',
        'fixed_price',
        'discount_percent',
        'status',
    ];

    protected $casts = [
        'fixed_price' => 'decimal:8',
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

    public function priceList()
    {
        return $this->belongsTo(PriceList::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function getResolvedPriceAttribute(): float
    {
        $currency = $this->resolvedCurrency();

        if ($this->pricing_method === 'fixed') {
            return round_currency_amount((float) ($this->fixed_price ?? 0), $currency);
        }

        return round_currency_amount((float) ($this->item?->price ?? 0), $currency);
    }

    public function getResolvedFinalPriceAttribute(): float
    {
        $currency = $this->resolvedCurrency();

        if ($this->pricing_method === 'fixed') {
            return round_currency_amount((float) ($this->fixed_price ?? 0), $currency);
        }

        $basePrice = (float) ($this->item?->price ?? 0);
        $discount = max(0, min(100, (int) $this->discount_percent));

        return round_currency_amount($basePrice * (1 - ($discount / 100)), $currency);
    }

    public function activitySubjectLabel(): string
    {
        $itemName = trim((string) optional($this->item)->name);

        if ($itemName !== '') {
            return $itemName;
        }

        return 'Price List Item #' . $this->getKey();
    }

    private function resolvedCurrency(): ?Currency
    {
        $item = $this->relationLoaded('item')
            ? $this->getRelation('item')
            : $this->item()->with('currency')->first();

        if (! $item) {
            return null;
        }

        if ($item->relationLoaded('currency')) {
            return $item->getRelation('currency');
        }

        return $item->currency()->first();
    }
}
