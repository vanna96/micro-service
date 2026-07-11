<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Rennokki\QueryCache\Traits\QueryCacheable;

class Promotion extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    public const TYPE_ITEM_PRICE = 'item_price';
    public const TYPE_SUBTOTAL_DISCOUNT = 'subtotal_discount';
    public const TYPE_BOGO = 'bogo';
    public const TYPE_BOGO_SAME_SKU = self::TYPE_BOGO;
    public const BOGO_ROLE_BUY = 'buy';
    public const BOGO_ROLE_GET = 'get';

    protected $fillable = [
        'code',
        'name',
        'image_id',
        'type',
        'description',
        'start_at',
        'end_at',
        'threshold_amount',
        'reward_discount_percent',
        'buy_quantity',
        'get_quantity',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'threshold_amount' => 'decimal:2',
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
        return $this->hasMany(PromotionItem::class);
    }

    public function galleries()
    {
        return $this->morphMany(Gallery::class, 'gallarieable');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'image_id');
    }

    public function activeLines()
    {
        return $this->lines()->where('status', 'Active');
    }

    public function usesItemLines(): bool
    {
        return in_array($this->type, [self::TYPE_ITEM_PRICE, self::TYPE_BOGO], true);
    }

    public function supportsLinePricing(): bool
    {
        return $this->type === self::TYPE_ITEM_PRICE;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_ITEM_PRICE => 'Item Price',
            self::TYPE_SUBTOTAL_DISCOUNT => 'Subtotal Discount',
            self::TYPE_BOGO => 'Buy X Get Y',
            default => 'Unknown',
        };
    }

    public function scopeCurrentlyActive(Builder $query, CarbonInterface|string|null $at = null): Builder
    {
        $at = $at instanceof CarbonInterface ? $at : ($at ? Carbon::parse($at) : now());

        return $query
            ->where('status', 'Active')
            ->where('start_at', '<=', $at)
            ->where('end_at', '>=', $at);
    }

    public function isCurrentlyActiveAt(CarbonInterface|string|null $at = null): bool
    {
        $at = $at instanceof CarbonInterface ? $at : ($at ? Carbon::parse($at) : now());

        return $this->status === 'Active'
            && $this->start_at !== null
            && $this->end_at !== null
            && $this->start_at->lte($at)
            && $this->end_at->gte($at);
    }

    public function getScheduleSummaryAttribute(): string
    {
        $start = $this->start_at?->format('d M Y, h:i A') ?: '-';
        $end = $this->end_at?->format('d M Y, h:i A') ?: '-';

        return $start . ' - ' . $end;
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->typeLabel();
    }

    public function getRuleSummaryAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ITEM_PRICE => 'Attached item pricing rules',
            self::TYPE_SUBTOTAL_DISCOUNT => sprintf(
                'Spend $%s, get %d%% off',
                number_format((float) ($this->threshold_amount ?? 0), 2),
                (int) ($this->reward_discount_percent ?? 0)
            ),
            self::TYPE_BOGO => sprintf(
                'Buy %d of the trigger item, get %d of the reward item free',
                (int) ($this->buy_quantity ?? 0),
                (int) ($this->get_quantity ?? 0)
            ),
            default => '-',
        };
    }

    public function getImageUrlAttribute(): ?string
    {
        $image = $this->relationLoaded('image')
            ? $this->getRelation('image')
            : $this->image()->first();

        if ($image && filled($image->name) && Storage::disk('promotion')->exists($image->name)) {
            return Storage::disk('promotion')->url($image->name);
        }

        return null;
    }

    protected function activityLogIgnoredOnlyAttributes(): array
    {
        return ['image_id'];
    }
}
