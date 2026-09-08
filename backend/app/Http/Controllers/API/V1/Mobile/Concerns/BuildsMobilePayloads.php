<?php

namespace App\Http\Controllers\API\V1\Mobile\Concerns;

use App\Models\Address;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
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

    protected function mobileBannerPayload(Slider $slider): array
    {
        return [
            'id' => (int) $slider->id,
            'title' => (string) ($slider->title ?? ''),
            'subtitle' => (string) ($slider->subtitle ?? ''),
            'placement' => (string) ($slider->placement ?? ''),
            'target_url' => (string) ($slider->target_url ?? ''),
            'image_url' => $slider->image_url,
            'sort_order' => (int) ($slider->sort_order ?? 0),
            'status' => (string) ($slider->status ?? 'Active'),
        ];
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

    protected function mobileItemPayload(Item $item): array
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
            'discount_percent' => (int) ($item->discount_percent ?? 0),
            'final_price' => (float) $item->final_price,
            'formatted_final_price' => (string) format_currency_amount($item->final_price, $item->currency),
            'rating' => $item->rating !== null ? (float) $item->rating : null,
            'review_count' => (int) ($item->review_count ?? 0),
            'stock' => (int) ($item->stock ?? 0),
            'status' => (string) ($item->status ?? 'Active'),
            'is_premium' => (bool) ($item->is_premium ?? false),
            'is_featured' => (bool) ($item->is_featured ?? false),
            'is_new_arrival' => (bool) ($item->is_new_arrival ?? false),
            'is_try_on_enabled' => (bool) ($item->is_try_on_enabled ?? false),
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
            ] : null,
            'uom_group' => $item->relationLoaded('uomGroup') && $item->uomGroup ? [
                'id' => (int) $item->uomGroup->id,
                'code' => (string) $item->uomGroup->code,
                'name' => (string) $item->uomGroup->name,
                'units' => $item->uomGroup->relationLoaded('units')
                    ? $item->uomGroup->units
                        ->filter(fn ($groupUnit) => $groupUnit->unit)
                        ->map(fn ($groupUnit) => [
                            'id' => (int) $groupUnit->unit->id,
                            'name' => (string) $groupUnit->unit->name,
                            'code' => (string) $groupUnit->unit->code,
                            'symbol' => (string) ($groupUnit->unit->symbol ?: $groupUnit->unit->code),
                            'conversion_factor_to_base' => (float) $groupUnit->conversion_factor_to_base,
                            'is_base_unit' => (bool) $groupUnit->is_base_unit,
                            'price' => round((float) $item->price * (float) $groupUnit->conversion_factor_to_base, 2),
                        ])->values()->all()
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

    protected function mobileOrderItemPayload(OrderItem $line): array
    {
        return [
            'id' => (int) $line->id,
            'item_id' => $line->item_id ? (int) $line->item_id : null,
            'item_variant_id' => $line->item_variant_id ? (int) $line->item_variant_id : null,
            'sku' => (string) ($line->sku ?? ''),
            'name' => (string) $line->name,
            'image_url' => $line->image_url,
            'selected_options' => $line->selected_options ?? [],
            'quantity' => (int) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'option_total' => (float) $line->option_total,
            'line_subtotal' => (float) $line->line_subtotal,
            'discount_amount' => (float) $line->discount_amount,
            'line_total' => (float) $line->line_total,
        ];
    }

    protected function mobileOrderPayload(Order $order, bool $includeItems = true): array
    {
        return [
            'id' => (int) $order->id,
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'payment_status' => (string) $order->payment_status,
            'payment_method' => (string) $order->payment_method,
            'delivery_method' => (string) $order->delivery_method,
            'currency_code' => (string) ($order->currency_code ?? ''),
            'total_items' => (int) $order->total_items,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'total' => (float) $order->total,
            'note' => (string) ($order->note ?? ''),
            'placed_at' => optional($order->placed_at ?? $order->created_at)->toIso8601String(),
            'address' => $order->relationLoaded('address') && $order->address
                ? $this->mobileAddressPayload($order->address)
                : null,
            'items' => $includeItems && $order->relationLoaded('items')
                ? $order->items->map(fn (OrderItem $line) => $this->mobileOrderItemPayload($line))->values()->all()
                : [],
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
