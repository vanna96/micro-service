<?php

namespace App\Http\Controllers\API\V1\Mobile\Concerns;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Notification;
use App\Models\PosSale;
use App\Models\Slider;
use App\Models\User;

trait BuildsMobilePayloads
{
    protected function mobileUserPayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'username' => (string) ($user->username ?? ''),
            'first_name' => (string) ($user->first_name ?? ''),
            'last_name' => (string) ($user->last_name ?? ''),
            'country_code' => (string) ($user->country_code ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'gender' => (string) ($user->gender ?? ''),
            'dob' => $user->dob ? date('Y-m-d', strtotime((string) $user->dob)) : '',
            'email' => (string) ($user->email ?? ''),
            'status' => (string) ($user->status ?? 'Active'),
            'profile_image_url' => $user->profile_image_url,
            'created_at' => optional($user->created_at)->toIso8601String(),
        ];
    }

    protected function mobileBranchPayload(Branch $branch): array
    {
        return [
            'id' => (int) $branch->id,
            'code' => (string) $branch->code,
            'name' => (string) $branch->name,
            'foreign_name' => (string) ($branch->foreign_name ?? ''),
            'location' => (string) ($branch->location ?? ''),
            'sort_order' => (int) ($branch->sort_order ?? 0),
            'status' => (string) ($branch->status ?? 'Active'),
        ];
    }

    protected function mobileBannerPayload(Slider $banner): array
    {
        $payload = $banner->toPromoSlidePayload();
        $origin = rtrim(request()->getSchemeAndHttpHost(), '/');
        $mediaUrl = trim((string) ($payload['mediaUrl'] ?? ''));

        if ($mediaUrl !== '' && str_starts_with($mediaUrl, '//')) {
            $mediaUrl = parse_url($origin, PHP_URL_SCHEME).':'.$mediaUrl;
        } elseif ($mediaUrl !== '' && ! preg_match('#^https?://#i', $mediaUrl)) {
            $mediaUrl = $origin.'/'.ltrim($mediaUrl, '/');
        }

        $payload['image_url'] = $mediaUrl;
        unset($payload['mediaUrl'], $payload['media_url'], $payload['image']);

        return $payload;
    }

    protected function mobileCategoryPayload(Category $category): array
    {
        return [
            'id' => (int) $category->id,
            'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
            'name' => (string) $category->name,
            'foreign_name' => (string) ($category->foreign_name ?? ''),
            'thumbnail_url' => $category->image_url,
            'image_url' => $category->image_url,
            'status' => (string) ($category->status ?? 'Active'),
        ];
    }

    protected function mobileItemPayload(Item $item, array $promotions = []): array
    {
        return [
            'id' => (int) $item->id,
            'sku' => (string) $item->sku,
            'name' => (string) $item->name,
            'item_type' => (string) ($item->item_type ?? 'uom'),
            'foreign_name' => (string) ($item->foreign_name ?? ''),
            'description' => (string) ($item->description ?? ''),
            'price' => (float) $item->price,
            'formatted_price' => (string) format_currency_amount($item->price, $item->currency),
            'rating' => $item->rating !== null ? (float) $item->rating : null,
            'review_count' => (int) ($item->review_count ?? 0),
            'stock' => (int) ($item->stock ?? 0),
            'status' => (string) ($item->status ?? 'Active'),
            'is_premium' => (bool) ($item->is_premium ?? false),
            'is_featured' => (bool) ($item->is_featured ?? false),
            'is_new_arrival' => (bool) ($item->is_new_arrival ?? false),
            'is_try_on_enabled' => (bool) ($item->is_try_on_enabled ?? false),
            'stock_control' => (bool) ($item->stock_control ?? true),
            'purchase' => (bool) ($item->purchase ?? true),
            'sale' => (bool) ($item->sale ?? true),
            'promotions' => array_values($promotions),
            'branch' => $item->branch ? [
                'id' => (int) $item->branch->id,
                'name' => (string) $item->branch->name,
                'foreign_name' => (string) ($item->branch->foreign_name ?? ''),
                'location' => (string) ($item->branch->location ?? ''),
            ] : null,
            'category' => $item->category ? [
                'id' => (int) $item->category->id,
                'name' => (string) $item->category->name,
                'foreign_name' => (string) ($item->category->foreign_name ?? ''),
            ] : null,
            'currency' => $item->currency ? [
                'id' => (int) $item->currency->id,
                'code' => (string) $item->currency->code,
                'symbol' => (string) ($item->currency->symbol ?? $item->currency->code),
                'decimal_places' => (int) ($item->currency->decimal_places ?? 2),
            ] : null,
            'uom_group' => $item->relationLoaded('uomGroup') && $item->uomGroup ? [
                'id' => (int) $item->uomGroup->id,
                'code' => (string) $item->uomGroup->code,
                'name' => (string) $item->uomGroup->name,
                'foreign_name' => (string) ($item->uomGroup->foreign_name ?? ''),
                'units' => $item->uomGroup->relationLoaded('units')
                    ? $item->uomGroup->units
                        ->filter(fn ($groupUnit) => $groupUnit->unit)
                        ->map(function ($groupUnit) use ($item) {
                            $conversionFactor = (float) $groupUnit->conversion_factor_to_base;
                            $basePrice = round_currency_amount((float) $item->price * $conversionFactor, $item->currency);
                            $itemUomPrice = $item->relationLoaded('uomPrices')
                                ? $item->uomPrices->firstWhere('unit_of_measure_id', $groupUnit->unit_of_measure_id)
                                : null;

                            $isActive = (bool) ($itemUomPrice ? $itemUomPrice->is_active : true);

                            return [
                                'id' => (int) $groupUnit->unit->id,
                                'name' => (string) $groupUnit->unit->name,
                                'foreign_name' => (string) ($groupUnit->unit->foreign_name ?? ''),
                                'code' => (string) $groupUnit->unit->code,
                                'symbol' => (string) ($groupUnit->unit->symbol ?: $groupUnit->unit->code),
                                'conversion_factor_to_base' => $conversionFactor,
                                'is_base_unit' => (bool) $groupUnit->is_base_unit,
                                'base_price' => $basePrice,
                                'reduce_by_percent' => (float) ($itemUomPrice?->reduce_by_percent ?? 0),
                                'price' => $itemUomPrice
                                    ? $itemUomPrice->resolvedPrice((float) $item->price, $conversionFactor, $item->currency)
                                    : $basePrice,
                                'is_auto' => (bool) ($itemUomPrice?->is_auto ?? true),
                                'is_active' => $isActive,
                            ];
                        })
                        ->filter(fn (array $unit): bool => $unit['is_active'])
                        ->values()->all()
                    : [],
            ] : null,
            'thumbnail_url' => $item->image_url,
            'image_url' => $item->image_url,
            'has_variations' => $item->relationLoaded('optionGroups')
                ? $item->optionGroups->where('type', 'variant')->isNotEmpty()
                : false,
            'option_groups' => $item->relationLoaded('optionGroups')
                ? $item->optionGroups->map(fn ($group) => [
                    'id' => (int) $group->id,
                    'name' => (string) $group->name,
                    'foreign_name' => (string) ($group->foreign_name ?? ''),
                    'type' => (string) $group->type,
                    'selection_type' => (string) $group->selection_type,
                    'is_required' => (bool) $group->is_required,
                    'min_selections' => (int) $group->min_selections,
                    'max_selections' => $group->max_selections !== null ? (int) $group->max_selections : null,
                    'values' => $group->values->map(fn ($value) => [
                        'id' => (int) $value->id,
                        'name' => (string) $value->name,
                        'foreign_name' => (string) ($value->foreign_name ?? ''),
                        'color_hex' => $value->color_hex,
                        'price_adjustment' => (float) $value->price_adjustment,
                        'is_default' => (bool) $value->is_default,
                    ])->values()->all(),
                ])->values()->all()
                : [],
            'variants' => $item->relationLoaded('variants')
                ? $item->variants->map(fn ($variant) => [
                    'id' => (int) $variant->id,
                    'sku' => (string) $variant->sku,
                    'barcode' => $variant->barcode,
                    'name' => (string) ($variant->name ?? ''),
                    'price' => $variant->price !== null ? (float) $variant->price : null,
                    'resolved_price' => (float) ($variant->price ?? $item->price),
                    'stock' => (int) $variant->stock,
                    'is_default' => (bool) $variant->is_default,
                    'option_value_ids' => $variant->optionValues->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ])->values()->all()
                : [],
            'galleries' => $item->relationLoaded('galleries')
                ? $item->galleries
                    ->where('type', 'galleries')
                    ->values()
                    ->map(fn ($gallery) => [
                        'id' => (int) $gallery->id,
                        'image_url' => \Storage::disk('item')->url($gallery->name),
                    ])->values()->all()
                : [],
        ];
    }

    protected function mobileAddressPayload(Address $address): array
    {
        return [
            'id' => (int) $address->id,
            'label' => (string) $address->label,
            'recipient_name' => (string) $address->recipient_name,
            'country_code' => (string) $address->code,
            'phone' => (string) $address->phone,
            'address_line' => (string) $address->address_line,
            'city' => (string) $address->city,
            'note' => (string) ($address->note ?? ''),
            'latitude' => $address->latitude !== null ? (float) $address->latitude : null,
            'longitude' => $address->longitude !== null ? (float) $address->longitude : null,
            'is_default' => (bool) $address->is_default,
        ];
    }

        protected function mobileOrderPayload(PosSale $sale, bool $includeItems = true): array
        {
            return $this->mobilePosSalePayload($sale, $includeItems);
        }

    protected function mobilePosSalePayload(PosSale $sale, bool $includeItems = true): array
    {
        $items = $includeItems && $sale->relationLoaded('items')
            ? $sale->items->map(fn ($line) => [
                'id' => (int) $line->id,
                'product_id' => $line->item_id ? (int) $line->item_id : 0,
                'item_id' => $line->item_id ? (int) $line->item_id : 0,
                'sku' => (string) ($line->sku ?? ''),
                'name' => (string) $line->name,
                'foreign_name' => (string) ($line->item?->foreign_name ?? ''),
                'quantity' => (int) $line->quantity,
                'unit_price' => (float) $line->unit_price_base,
                'discount_amount' => (float) $line->discount_base,
                'line_total' => (float) $line->line_total_base,
                'image' => (string) ($line->item?->image_url ?? ''),
                'image_url' => (string) ($line->item?->image_url ?? ''),
                'thumbnail_url' => (string) ($line->item?->image_url ?? ''),
            ])->values()->all()
            : [];

        return [
            'id' => (int) $sale->id,
            'order_number' => (string) ($sale->invoice_number ?: $sale->reference ?: ('SALE-'.$sale->id)),
            'sale_from' => (string) ($sale->sale_from ?? 'mobile'),
            'status' => (string) ($sale->status === 'completed' ? 'Completed' : $sale->status),
            'payment_status' => (string) ($sale->status === 'completed' ? 'Paid' : 'Pending'),
            'payment_method' => (string) ($sale->payment_method ?? 'Cash'),
            'delivery_method' => (string) ($sale->order_type ?? 'Home Delivery'),
            'currency_code' => (string) ($sale->base_currency_code ?? 'USD'),
            'total_items' => (int) $sale->item_count,
            'subtotal' => (float) $sale->subtotal_base,
            'discount_total' => (float) $sale->discount_base,
            'total' => (float) $sale->total_base,
            'total_amount' => (float) $sale->total_base,
            'note' => (string) ($sale->notes ?? ''),
            'created_at' => optional($sale->completed_at ?? $sale->created_at)->toIso8601String(),
            'placed_at' => optional($sale->completed_at ?? $sale->created_at)->toIso8601String(),
            'lines' => $items,
            'items' => $items,
        ];
    }

    protected function mobileNotificationPayload(Notification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'type' => (string) $notification->type,
            'title' => (string) $notification->title,
            'message' => (string) $notification->message,
            'data' => $notification->data ?? [],
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ];
    }
}
