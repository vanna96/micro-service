<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rennokki\QueryCache\Traits\QueryCacheable;

class PromotionItem extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    protected $fillable = [
        'promotion_id',
        'item_id',
        'line_role',
        'pricing_method',
        'fixed_price',
        'discount_percent',
        'status',
    ];

    protected $casts = [
        'fixed_price' => 'decimal:2',
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

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function getResolvedFinalPriceAttribute(): float
    {
        if ($this->promotion && $this->promotion->type !== Promotion::TYPE_ITEM_PRICE) {
            return (float) ($this->item?->price ?? 0);
        }

        if ($this->pricing_method === 'fixed') {
            return (float) ($this->fixed_price ?? 0);
        }

        $basePrice = (float) ($this->item?->price ?? 0);
        $discount = max(0, min(100, (int) $this->discount_percent));

        return round($basePrice * (1 - ($discount / 100)), 2);
    }

    public function getPricingSummaryAttribute(): string
    {
        if ($this->promotion && $this->promotion->type === Promotion::TYPE_BOGO) {
            return $this->line_role_label;
        }

        if ($this->pricing_method === 'fixed') {
            return '$' . number_format((float) ($this->fixed_price ?? 0), 2);
        }

        return (int) ($this->discount_percent ?? 0) . '% off';
    }

    public function getLineRoleLabelAttribute(): string
    {
        return match ($this->line_role) {
            Promotion::BOGO_ROLE_BUY => 'Buy item',
            Promotion::BOGO_ROLE_GET => 'Reward item',
            default => 'Priced item',
        };
    }

    public function activitySubjectLabel(): string
    {
        $itemName = trim((string) optional($this->item)->name);

        if ($itemName !== '') {
            return $itemName;
        }

        return 'Promotion Item #' . $this->getKey();
    }
}
