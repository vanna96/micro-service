<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionPricingService
{
    public function price(array $payloadLines): array
    {
        $cartLines = $this->buildCartLines($payloadLines);
        $subtotal = round($cartLines->sum('line_subtotal'), 2);
        $winner = $this->resolveWinningPromotion($cartLines, $subtotal);
        $discounts = $winner['line_discounts'] ?? [];
        $responseLines = $cartLines->map(function (array $line) use ($discounts) {
            $discountAmount = round((float) ($discounts[$line['item_id']] ?? 0), 2);
            $lineTotal = round(max(0, $line['line_subtotal'] - $discountAmount), 2);

            return [
                'item_id' => $line['item_id'],
                'sku' => $line['sku'],
                'name' => $line['name'],
                'quantity' => $line['quantity'],
                'unit_price' => (float) $line['unit_price'],
                'line_subtotal' => (float) $line['line_subtotal'],
                'discount_amount' => (float) $discountAmount,
                'line_total' => (float) $lineTotal,
            ];
        })->values();

        $discountTotal = round($responseLines->sum('discount_amount'), 2);

        return [
            'items' => $responseLines,
            'subtotal' => (float) $subtotal,
            'discount_total' => (float) $discountTotal,
            'final_total' => (float) round(max(0, $subtotal - $discountTotal), 2),
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

    protected function buildCartLines(array $payloadLines): Collection
    {
        $aggregated = collect($payloadLines)
            ->groupBy('item_id')
            ->map(fn (Collection $lines, $itemId) => [
                'item_id' => (int) $itemId,
                'quantity' => (int) $lines->sum('quantity'),
            ])
            ->filter(fn (array $line) => $line['quantity'] > 0);

        $items = Item::query()
            ->whereIn('id', $aggregated->pluck('item_id'))
            ->get()
            ->keyBy('id');

        return $aggregated->map(function (array $line) use ($items) {
            /** @var \App\Models\Item $item */
            $item = $items->get($line['item_id']);
            $unitPrice = round((float) $item->price, 2);

            return [
                'item_id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $line['quantity'],
                'unit_price' => $unitPrice,
                'line_subtotal' => round($unitPrice * $line['quantity'], 2),
            ];
        })->values();
    }

    protected function resolveWinningPromotion(Collection $cartLines, float $subtotal): ?array
    {
        $promotions = Promotion::query()
            ->currentlyActive()
            ->with('activeLines')
            ->get();

        return $promotions
            ->map(fn (Promotion $promotion) => $this->evaluatePromotion($promotion, $cartLines, $subtotal))
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
            $cartLine = $cartLines->firstWhere('item_id', $line->item_id);

            if (! $cartLine) {
                continue;
            }

            $baseTotal = $cartLine['line_subtotal'];

            if ($line->pricing_method === 'fixed') {
                $candidateTotal = round((float) ($line->fixed_price ?? 0) * $cartLine['quantity'], 2);
                $discount = round(max(0, $baseTotal - $candidateTotal), 2);
            } else {
                $discount = round($baseTotal * ((int) ($line->discount_percent ?? 0) / 100), 2);
            }

            if ($discount <= 0) {
                continue;
            }

            $discounts[$line->item_id] = round(($discounts[$line->item_id] ?? 0) + $discount, 2);
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
                $getLine->item_id => round($discountedQuantity * $buyCartLine['unit_price'], 2),
            ]);
        }

        $bundleCount = intdiv($buyCartLine['quantity'], $buyQuantity);
        $rewardQuantity = $bundleCount * $getQuantity;
        $discountedQuantity = min($getCartLine['quantity'], $rewardQuantity);

        if ($discountedQuantity <= 0) {
            return null;
        }

        return $this->buildCandidate($promotion, [
            $getLine->item_id => round($discountedQuantity * $getCartLine['unit_price'], 2),
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
                $allocated[$line['item_id']] = round($remaining, 2);
                break;
            }

            $share = round($discountTotal * ($line['line_subtotal'] / max(0.01, $cartLines->sum('line_subtotal'))), 2);
            $allocated[$line['item_id']] = $share;
            $remaining = round($remaining - $share, 2);
        }

        return $allocated;
    }
}
