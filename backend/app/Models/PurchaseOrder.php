<?php

namespace App\Models;

use App\Models\Concerns\LogsTenantActivity;
use App\Models\Concerns\UsesQueryCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Rennokki\QueryCache\Traits\QueryCacheable;

class PurchaseOrder extends Model
{
    use HasFactory, QueryCacheable, UsesQueryCache, LogsTenantActivity;

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ORDERED = 'Ordered';
    public const STATUS_RECEIVED = 'Received';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ORDERED,
        self::STATUS_RECEIVED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'po_number',
        'vendor_id',
        'branch_id',
        'order_date',
        'expected_date',
        'status',
        'currency_id',
        'currency_code',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'notes',
        'stock_received_at',
        'created_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_date' => 'date',
        'subtotal' => 'float',
        'tax_amount' => 'float',
        'shipping_amount' => 'float',
        'discount_amount' => 'float',
        'total_amount' => 'float',
        'stock_received_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'currency_code' => 'USD',
        'subtotal' => 0,
        'tax_amount' => 0,
        'shipping_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
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

    protected function getCacheBaseTags(): array
    {
        return $this->buildQueryCacheBaseTags();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'vendor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ORDERED);
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RECEIVED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED || $this->stock_received_at !== null;
    }

    public function canReceive(): bool
    {
        return ! $this->isReceived() && $this->status !== self::STATUS_CANCELLED;
    }

    public function canEdit(): bool
    {
        return ! $this->isReceived();
    }

    public function canCancel(): bool
    {
        return ! $this->isReceived() && $this->status !== self::STATUS_CANCELLED;
    }

    public function canMarkOrdered(): bool
    {
        return ! $this->isReceived() && in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CANCELLED], true);
    }

    public function canMarkDraft(): bool
    {
        return ! $this->isReceived() && in_array($this->status, [self::STATUS_ORDERED, self::STATUS_CANCELLED], true);
    }

    public function recalculateTotals(): void
    {
        $this->subtotal = (float) $this->items()->sum('subtotal');
        $this->total_amount = max(0, $this->subtotal + (float) $this->tax_amount + (float) $this->shipping_amount - (float) $this->discount_amount);
        $this->save();
    }
}
