<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\RateIndexValue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PromotionPricingService
{
    public function __construct(protected ItemConfigurationService $itemConfiguration)
    {
    }

    public function price(
        array $payloadLines,
        bool $normalizeToBaseCurrency = false,
        bool $lockForUpdate = false
    ): array {
        $cartLines = $this->buildCartLines($payloadLines, $lockForUpdate);
        if ($normalizeToBaseCurrency) {
            $cartLines = $this->normalizeCartLinesToBaseCurrency($cartLines);
        }
        $subtotal = round($cartLines->sum('line_subtotal'), 2);
        $winner = $this->resolveWinningPromotion(
            $cartLines,
            $subtotal,
            $normalizeToBaseCurrency,
            $lockForUpdate
        );
        $cartLines = $winner['cart_lines'] ?? $cartLines;
        $subtotal = round($cartLines->sum('line_subtotal'), 2);
        $discounts = $winner['line_discounts'] ?? [];
        $responseLines = $cartLines->map(function (array $line) use ($discounts) {
            $discountAmount = round((float) ($discounts[$line['line_key']] ?? 0), 2);
            $lineTotal = round(max(0, $line['line_subtotal'] - $discountAmount), 2);

            return [
                'item_id' => $line['item_id'],
                'item_variant_id' => $line['item_variant_id'],
                'uom_id' => $line['uom_id'],
                'uom_code' => $line['uom_code'],
                'uom_name' => $line['uom_name'],
                'uom_conversion_factor' => (float) $line['uom_conversion_factor'],
                'sku' => $line['sku'],
                'name' => $line['name'],
                'selected_options' => $line['selected_options'],
                'quantity' => $line['quantity'],
                'unit_price' => (float) $line['unit_price'],
                'option_total' => (float) $line['option_total'],
                'line_subtotal' => (float) $line['line_subtotal'],
                'discount_amount' => (float) $discountAmount,
                'line_total' => (float) $lineTotal,
            ];
        })->values();

        $discountTotal = round($responseLines->sum('discount_amount'), 2);

        return [
            'currency_mode' => $normalizeToBaseCurrency ? 'base' : 'native',
            'items' => $responseLines,
            'subtotal' => (float) $subtotal,
            'discount_total' => (float) $discountTotal,
            'final_total' => (float) round(max(0, $subtotal - $discountTotal), 2),
            'auto_add_items' => $winner['auto_add_items'] ?? [],
            'applied_promotion' => $winner ? [
                'id' => $winner['promotion']->id,
                'code' => $winner['promotion']->code,
                'name' => $winner['promotion']->name,
                'type' => $winner['promotion']->type,
                'summary' => $winner['promotion']->rule_summary,
                'savings' => (float) round($winner['discount_total'], 2),
            ] : null,
        ];
    }

    /**
     * Build lightweight, display-only promotion metadata for catalog cards.
     * Final eligibility and savings are still calculated by price().
     */
    public function catalogPromotionPreviews(Collection $items): array
    {
        $itemsById = $items->keyBy('id');

        if ($itemsById->isEmpty()) {
            return [];
        }

        $previewsByItem = $itemsById
            ->mapWithKeys(fn (Item $item) => [(int) $item->id => []])
            ->all();

        $promotions = Promotion::query()
            ->currentlyActive()
            ->with(['activeLines.item.currency'])
            ->get();

        foreach ($promotions as $promotion) {
            if ($promotion->type === Promotion::TYPE_SUBTOTAL_DISCOUNT) {
                if ((float) ($promotion->threshold_amount ?? 0) <= 0
                    || (int) ($promotion->reward_discount_percent ?? 0) <= 0) {
                    continue;
                }

                foreach ($itemsById->keys() as $itemId) {
                    $previewsByItem[(int) $itemId][] = $this->catalogPromotionPreview($promotion);
                }

                continue;
            }

            if ($promotion->type === Promotion::TYPE_BOGO
                && ! $this->hasCompleteActiveBogoRule($promotion)) {
                continue;
            }

            foreach ($promotion->activeLines as $line) {
                $itemId = (int) $line->item_id;

                if (! array_key_exists($itemId, $previewsByItem) || ! $line->item || $line->item->status !== 'Active') {
                    continue;
                }

                $preview = $this->catalogPromotionPreview($promotion, $line);

                if ($promotion->type === Promotion::TYPE_ITEM_PRICE
                    && ($preview['promotional_price'] === null
                        || $preview['promotional_price'] >= (float) $line->item->price)) {
                    continue;
                }

                $previewsByItem[$itemId][] = $preview;
            }
        }

        return collect($previewsByItem)
            ->map(fn (array $previews) => collect($previews)
                ->sortBy(fn (array $preview) => sprintf(
                    '%d-%010d',
                    match ($preview['type']) {
                        Promotion::TYPE_ITEM_PRICE => 0,
                        Promotion::TYPE_BOGO => 1,
                        default => 2,
                    },
                    $preview['id']
                ))
                ->values()
                ->all())
            ->all();
    }

    protected function catalogPromotionPreview(Promotion $promotion, mixed $line = null): array
    {
        $promotionalPrice = null;
        $discountPercent = null;
        $role = $line?->line_role;
        $buyLine = $promotion->type === Promotion::TYPE_BOGO
            ? $promotion->activeLines->firstWhere('line_role', Promotion::BOGO_ROLE_BUY)
            : null;
        $getLine = $promotion->type === Promotion::TYPE_BOGO
            ? $promotion->activeLines->firstWhere('line_role', Promotion::BOGO_ROLE_GET)
            : null;

        if ($promotion->type === Promotion::TYPE_ITEM_PRICE && $line) {
            $discountPercent = $line->pricing_method === 'discount'
                ? (int) ($line->discount_percent ?? 0)
                : null;
            $promotionalPrice = $line->pricing_method === 'fixed'
                ? (float) ($line->fixed_price ?? 0)
                : round_currency_amount(
                    (float) ($line->item?->price ?? 0) * (1 - ($discountPercent / 100)),
                    $line->item?->currency
                );
        }

        $label = match ($promotion->type) {
            Promotion::TYPE_ITEM_PRICE => $discountPercent
                ? $discountPercent.'% OFF'
                : 'PROMO PRICE',
            Promotion::TYPE_SUBTOTAL_DISCOUNT => sprintf(
                '%d%% OFF %s+',
                (int) ($promotion->reward_discount_percent ?? 0),
                $this->compactCurrencyAmount((float) ($promotion->threshold_amount ?? 0))
            ),
            Promotion::TYPE_BOGO => $role === Promotion::BOGO_ROLE_GET
                ? sprintf('GET %d FREE', (int) ($promotion->get_quantity ?? 0))
                : sprintf(
                    'BUY %d GET %d',
                    (int) ($promotion->buy_quantity ?? 0),
                    (int) ($promotion->get_quantity ?? 0)
                ),
            default => 'PROMOTION',
        };

        return [
            'id' => (int) $promotion->id,
            'code' => (string) $promotion->code,
            'name' => (string) $promotion->name,
            'type' => (string) $promotion->type,
            'role' => $role,
            'label' => $label,
            'summary' => (string) $promotion->rule_summary,
            'promotional_price' => $promotionalPrice,
            'discount_percent' => $discountPercent,
            'threshold_amount' => $promotion->threshold_amount !== null
                ? (float) $promotion->threshold_amount
                : null,
            'reward_discount_percent' => $promotion->reward_discount_percent !== null
                ? (int) $promotion->reward_discount_percent
                : null,
            'buy_quantity' => $promotion->buy_quantity !== null ? (int) $promotion->buy_quantity : null,
            'get_quantity' => $promotion->get_quantity !== null ? (int) $promotion->get_quantity : null,
            'buy_item_id' => $buyLine ? (int) $buyLine->item_id : null,
            'get_item_id' => $getLine ? (int) $getLine->item_id : null,
            'buy_item_name' => $buyLine?->item?->name,
            'get_item_name' => $getLine?->item?->name,
        ];
    }

    protected function compactCurrencyAmount(float $amount): string
    {
        $currency = tenant_base_currency();
        $prefix = (string) ($currency?->symbol ?: $currency?->code ?: '');
        $separator = preg_match('/^[A-Za-z]{2,}$/', $prefix) ? ' ' : '';
        $decimals = abs($amount - round($amount)) < 0.00000001
            ? 0
            : max(0, (int) ($currency?->decimal_places ?? 2));

        return $prefix.$separator.number_format($amount, $decimals);
    }

    protected function hasCompleteActiveBogoRule(Promotion $promotion): bool
    {
        return (int) ($promotion->buy_quantity ?? 0) > 0
            && (int) ($promotion->get_quantity ?? 0) > 0
            && $promotion->activeLines->contains(fn ($line) => $line->line_role === Promotion::BOGO_ROLE_BUY
                && $line->item
                && $line->item->status === 'Active')
            && $promotion->activeLines->contains(fn ($line) => $line->line_role === Promotion::BOGO_ROLE_GET
                && $line->item
                && $line->item->status === 'Active');
    }

    protected function buildCartLines(array $payloadLines, bool $lockForUpdate = false): Collection
    {
        $payload = collect($payloadLines)->values();

        $items = Item::query()
            ->with(['currency', 'uomPrices', 'optionGroups.values', 'variants.optionValues', 'uomGroup.units.unit'])
            ->whereIn('id', $payload->pluck('item_id')->unique())
            ->when($lockForUpdate, fn ($query) => $query->dontCache()->lockForUpdate())
            ->get()
            ->keyBy('id');

        return $payload
            ->map(function (array $line) use ($items): array {
                /** @var \App\Models\Item $item */
                $item = $items->get($line['item_id']);
                $configuration = $this->itemConfiguration->resolve(
                    $item,
                    isset($line['variant_id']) ? (int) $line['variant_id'] : null,
                    $line['option_value_ids'] ?? [],
                    isset($line['uom_id']) ? (int) $line['uom_id'] : null
                );

                if (array_key_exists('unit_price', $line) && $line['unit_price'] !== null) {
                    $configuration['unit_price'] = round_currency_amount(
                        max(0, (float) $line['unit_price']),
                        $item->currency
                    );
                    $configuration['line_key'] .= ':price:'.$configuration['unit_price'];
                }

                return array_merge(
                    [
                        'item_id' => $item->id,
                        'currency_id' => $item->currency_id,
                        'currency_code' => $item->currency?->code,
                    ],
                    $configuration,
                    ['quantity' => (int) $line['quantity']]
                );
            })
            ->groupBy('line_key')
            ->map(function (Collection $lines): array {
                $line = $lines->first();
                $quantity = (int) $lines->sum('quantity');

                return array_merge($line, [
                    'quantity' => $quantity,
                    'line_subtotal' => round($line['unit_price'] * $quantity, 2),
                ]);
            })
            ->values();
    }

    protected function normalizeCartLinesToBaseCurrency(Collection $cartLines): Collection
    {
        $baseCurrency = tenant_base_currency();
        if (! $baseCurrency) {
            throw ValidationException::withMessages([
                'base_currency' => 'Set an active base currency before calculating promotions.',
            ]);
        }

        $tenantSettings = is_array(tenant()?->general_settings) ? tenant()->general_settings : [];
        $tenantTimezone = (string) ($tenantSettings['timezone'] ?? config('app.timezone', 'UTC'));
        $today = Carbon::now($tenantTimezone);
        $baseCode = strtoupper((string) $baseCurrency->code);
        $foreignCurrencyIds = $cartLines
            ->filter(fn (array $line): bool => filled($line['currency_id'] ?? null)
                && strtoupper((string) ($line['currency_code'] ?? '')) !== $baseCode)
            ->pluck('currency_id')
            ->unique()
            ->values();
        $rates = RateIndexValue::query()
            ->where('dataset_type', 'exchange_rate')
            ->where('year', $today->year)
            ->where('month', $today->month)
            ->where('day', $today->day)
            ->whereIn('currency_id', $foreignCurrencyIds)
            ->get()
            ->keyBy('currency_id');

        return $cartLines->map(function (array $line) use ($baseCode, $rates): array {
            $currencyCode = strtoupper((string) ($line['currency_code'] ?? $baseCode));
            $isBaseCurrency = blank($line['currency_id'] ?? null) || $currencyCode === $baseCode;
            $rate = $isBaseCurrency ? 1.0 : (float) optional($rates->get($line['currency_id']))->value;

            if (! ($rate > 0)) {
                throw ValidationException::withMessages([
                    'items' => "Today's exchange rate is not configured for {$currencyCode}.",
                ]);
            }

            foreach (['base_price', 'option_total', 'unit_price', 'line_subtotal'] as $amountKey) {
                $line[$amountKey] = round((float) $line[$amountKey] / $rate, 8);
            }
            $line['currency_rate'] = $rate;

            return $line;
        });
    }

    protected function resolveWinningPromotion(
        Collection $cartLines,
        float $subtotal,
        bool $normalizeToBaseCurrency = false,
        bool $lockForUpdate = false
    ): ?array {
        $relations = [
            'activeLines.item.currency',
            'activeLines.item.optionGroups.values',
            'activeLines.item.variants.optionValues',
            'activeLines.item.uomGroup.units.unit',
            'activeLines.item.uomPrices',
        ];
        $promotionQuery = Promotion::query()->currentlyActive();

        if ($lockForUpdate) {
            $promotions = $promotionQuery
                ->dontCache()
                ->lockForUpdate()
                ->get();

            $promotionLines = PromotionItem::query()
                ->dontCache()
                ->whereIn('promotion_id', $promotions->pluck('id'))
                ->where('status', 'Active')
                ->lockForUpdate()
                ->get();

            Item::query()
                ->dontCache()
                ->whereIn('id', $promotionLines->pluck('item_id')->unique())
                ->lockForUpdate()
                ->get();

            $promotions->load($relations);
        } else {
            $promotions = $promotionQuery->with($relations)->get();
        }

        return $promotions
            ->map(function (Promotion $promotion) use ($cartLines, $subtotal, $normalizeToBaseCurrency) {
                $candidateLines = $cartLines;
                $autoAddItems = [];

                if ($promotion->type === Promotion::TYPE_BOGO) {
                    [$candidateLines, $autoAddItems] = $this->withMissingBogoReward(
                        $promotion,
                        $cartLines,
                        $normalizeToBaseCurrency
                    );
                }

                $candidate = $this->evaluatePromotion(
                    $promotion,
                    $candidateLines,
                    $promotion->type === Promotion::TYPE_BOGO
                        ? round($candidateLines->sum('line_subtotal'), 2)
                        : $subtotal
                );

                if (! $candidate) {
                    return null;
                }

                $candidate['cart_lines'] = $candidateLines;
                $candidate['auto_add_items'] = $autoAddItems;

                return $candidate;
            })
            ->filter(fn (?array $candidate) => $candidate && $candidate['discount_total'] > 0)
            ->sort(function (array $left, array $right) {
                if ($left['discount_total'] !== $right['discount_total']) {
                    return $right['discount_total'] <=> $left['discount_total'];
                }

                $leftTimestamp = $left['promotion']->start_at?->getTimestamp() ?? 0;
                $rightTimestamp = $right['promotion']->start_at?->getTimestamp() ?? 0;

                if ($leftTimestamp !== $rightTimestamp) {
                    return $rightTimestamp <=> $leftTimestamp;
                }

                return $right['promotion']->id <=> $left['promotion']->id;
            })
            ->first();
    }

    protected function withMissingBogoReward(
        Promotion $promotion,
        Collection $cartLines,
        bool $normalizeToBaseCurrency
    ): array {
        $buyQuantity = (int) ($promotion->buy_quantity ?? 0);
        $getQuantity = (int) ($promotion->get_quantity ?? 0);
        $buyLine = $promotion->activeLines->firstWhere('line_role', Promotion::BOGO_ROLE_BUY);
        $getLine = $promotion->activeLines->firstWhere('line_role', Promotion::BOGO_ROLE_GET);

        if ($buyQuantity <= 0 || $getQuantity <= 0 || ! $buyLine || ! $getLine) {
            return [$cartLines, []];
        }

        // A same-item BOGO needs paid/free quantity provenance to avoid repeatedly
        // adding units, so it continues to use the explicit cart quantity flow.
        if ((int) $buyLine->item_id === (int) $getLine->item_id) {
            return [$cartLines, []];
        }

        $purchasedQuantity = (int) $cartLines
            ->where('item_id', (int) $buyLine->item_id)
            ->sum('quantity');
        $requiredRewardQuantity = intdiv($purchasedQuantity, $buyQuantity) * $getQuantity;
        $existingRewardQuantity = (int) $cartLines
            ->where('item_id', (int) $getLine->item_id)
            ->sum('quantity');
        $missingRewardQuantity = max(0, $requiredRewardQuantity - $existingRewardQuantity);

        if ($missingRewardQuantity <= 0) {
            return [$cartLines, []];
        }

        $rewardItem = $getLine->item;

        if (! $rewardItem
            || $rewardItem->status !== 'Active'
            || ! $rewardItem->sale
            || ($rewardItem->stock_control && (int) $rewardItem->stock < $missingRewardQuantity)) {
            return [$cartLines, []];
        }

        $candidateLines = $cartLines->values();
        $existingRewardIndex = $candidateLines->search(
            fn (array $line) => (int) $line['item_id'] === (int) $rewardItem->id
        );

        if ($existingRewardIndex !== false) {
            $rewardCartLine = $candidateLines->get($existingRewardIndex);
            $rewardCartLine['quantity'] += $missingRewardQuantity;
            $rewardCartLine['line_subtotal'] = round(
                $rewardCartLine['unit_price'] * $rewardCartLine['quantity'],
                8
            );
            $candidateLines->put($existingRewardIndex, $rewardCartLine);
            $rewardUomId = $rewardCartLine['uom_id'];
        } else {
            try {
                $variantGroups = $rewardItem->optionGroups->where('status', 'Active')->where('type', 'variant');

                if ($variantGroups->isNotEmpty()) {
                    return [$cartLines, []];
                }

                $baseUom = $rewardItem->uomGroup?->units
                    ->where('status', 'Active')
                    ->first(fn ($unit) => $unit->is_base_unit && $unit->unit?->status === 'Active');
                $rewardUomId = $baseUom?->unit_of_measure_id;
                $configuration = $this->itemConfiguration->resolve(
                    $rewardItem,
                    null,
                    [],
                    $rewardUomId ? (int) $rewardUomId : null
                );
            } catch (ValidationException) {
                return [$cartLines, []];
            }

            $rewardCartLine = array_merge([
                'item_id' => $rewardItem->id,
                'currency_id' => $rewardItem->currency_id,
                'currency_code' => $rewardItem->currency?->code,
            ], $configuration, [
                'quantity' => $missingRewardQuantity,
                'line_subtotal' => round($configuration['unit_price'] * $missingRewardQuantity, 8),
            ]);

            if ($normalizeToBaseCurrency) {
                $rewardCartLine = $this->normalizeCartLinesToBaseCurrency(collect([$rewardCartLine]))->first();
            }

            $candidateLines->push($rewardCartLine);
        }

        return [$candidateLines->values(), [[
            'promotion_id' => (int) $promotion->id,
            'promotion_name' => (string) $promotion->name,
            'item_id' => (int) $rewardItem->id,
            'uom_id' => $rewardUomId ? (int) $rewardUomId : null,
            'quantity' => $missingRewardQuantity,
        ]]];
    }

    protected function evaluatePromotion(Promotion $promotion, Collection $cartLines, float $subtotal): ?array
    {
        return match ($promotion->type) {
            Promotion::TYPE_ITEM_PRICE => $this->evaluateItemPricePromotion($promotion, $cartLines),
            Promotion::TYPE_SUBTOTAL_DISCOUNT => $this->evaluateSubtotalPromotion($promotion, $cartLines, $subtotal),
            Promotion::TYPE_BOGO => $this->evaluateBogoPromotion($promotion, $cartLines),
            default => null,
        };
    }

    protected function evaluateItemPricePromotion(Promotion $promotion, Collection $cartLines): ?array
    {
        $discounts = [];

        foreach ($promotion->activeLines as $line) {
            $cartLines->where('item_id', $line->item_id)->each(function (array $cartLine) use ($line, &$discounts) {
                $baseTotal = $cartLine['line_subtotal'];

                if ($line->pricing_method === 'fixed') {
                    $configuredFixedPrice = (
                        (float) ($line->fixed_price ?? 0) * (float) ($cartLine['uom_conversion_factor'] ?? 1)
                    ) / (float) ($cartLine['currency_rate'] ?? 1) + (float) $cartLine['option_total'];
                    $candidateTotal = round($configuredFixedPrice * $cartLine['quantity'], 2);
                    $discount = round(max(0, $baseTotal - $candidateTotal), 2);
                } else {
                    $discount = round($baseTotal * ((int) ($line->discount_percent ?? 0) / 100), 2);
                }

                if ($discount > 0) {
                    $discounts[$cartLine['line_key']] = round(($discounts[$cartLine['line_key']] ?? 0) + $discount, 2);
                }
            });
        }

        return $this->buildCandidate($promotion, $discounts);
    }

    protected function evaluateSubtotalPromotion(Promotion $promotion, Collection $cartLines, float $subtotal): ?array
    {
        $thresholdAmount = (float) ($promotion->threshold_amount ?? 0);

        if ($subtotal <= 0 || $subtotal < $thresholdAmount) {
            return null;
        }

        $discountTotal = round($subtotal * ((int) ($promotion->reward_discount_percent ?? 0) / 100), 2);

        if ($discountTotal <= 0) {
            return null;
        }

        return $this->buildCandidate($promotion, $this->prorateDiscountAcrossLines($cartLines, $discountTotal));
    }

    protected function evaluateBogoPromotion(Promotion $promotion, Collection $cartLines): ?array
    {
        $buyQuantity = (int) ($promotion->buy_quantity ?? 0);
        $getQuantity = (int) ($promotion->get_quantity ?? 0);
        $buyLine = $promotion->activeLines->firstWhere('line_role', Promotion::BOGO_ROLE_BUY);
        $getLine = $promotion->activeLines->firstWhere('line_role', Promotion::BOGO_ROLE_GET);

        if ($buyQuantity <= 0 || $getQuantity <= 0 || ! $buyLine || ! $getLine) {
            return null;
        }

        $buyCartLine = $cartLines->firstWhere('item_id', $buyLine->item_id);
        $getCartLine = $cartLines->firstWhere('item_id', $getLine->item_id);

        if (! $buyCartLine || ! $getCartLine) {
            return null;
        }

        if ($buyLine->item_id === $getLine->item_id) {
            $bundleQuantity = $buyQuantity + $getQuantity;

            if ($bundleQuantity <= 0) {
                return null;
            }

            $freeQuantity = intdiv($buyCartLine['quantity'], $bundleQuantity) * $getQuantity;
            $discountedQuantity = min($buyCartLine['quantity'], $freeQuantity);

            if ($discountedQuantity <= 0) {
                return null;
            }

            return $this->buildCandidate($promotion, [
                $buyCartLine['line_key'] => round($discountedQuantity * $buyCartLine['unit_price'], 2),
            ]);
        }

        $bundleCount = intdiv($buyCartLine['quantity'], $buyQuantity);
        $rewardQuantity = $bundleCount * $getQuantity;
        $discountedQuantity = min($getCartLine['quantity'], $rewardQuantity);

        if ($discountedQuantity <= 0) {
            return null;
        }

        return $this->buildCandidate($promotion, [
            $getCartLine['line_key'] => round($discountedQuantity * $getCartLine['unit_price'], 2),
        ]);
    }

    protected function buildCandidate(Promotion $promotion, array $discounts): ?array
    {
        $discountTotal = round(array_sum($discounts), 2);

        if ($discountTotal <= 0) {
            return null;
        }

        return [
            'promotion' => $promotion,
            'discount_total' => $discountTotal,
            'line_discounts' => $discounts,
        ];
    }

    protected function prorateDiscountAcrossLines(Collection $cartLines, float $discountTotal): array
    {
        $lineCount = $cartLines->count();

        if ($lineCount === 0) {
            return [];
        }

        $allocated = [];
        $remaining = $discountTotal;

        foreach ($cartLines->values() as $index => $line) {
            if ($index === $lineCount - 1) {
                $allocated[$line['line_key']] = round($remaining, 2);
                break;
            }

            $share = round($discountTotal * ($line['line_subtotal'] / max(0.01, $cartLines->sum('line_subtotal'))), 2);
            $allocated[$line['line_key']] = $share;
            $remaining = round($remaining - $share, 2);
        }

        return $allocated;
    }
}
