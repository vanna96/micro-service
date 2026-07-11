<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'category_id' => $this->category_id,
            'currency_id' => $this->currency_id,
            'branch' => $this->branch?->name,
            'category' => $this->category?->name,
            'currency' => $this->currency?->code,
            'sku' => $this->sku,
            'name' => $this->name,
            'foreign_name' => $this->foreign_name,
            'branch_name' => $this->branch?->name ?: $this->branch_name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'formatted_price' => format_currency_amount($this->price, $this->currency),
            'discount_percent' => (int) $this->discount_percent,
            'final_price' => $this->final_price,
            'formatted_final_price' => format_currency_amount($this->final_price, $this->currency),
            'rating' => $this->rating !== null ? (float) $this->rating : null,
            'review_count' => (int) $this->review_count,
            'stock' => (int) $this->stock,
            'status' => $this->status,
            'is_premium' => (bool) $this->is_premium,
            'is_featured' => (bool) $this->is_featured,
            'is_new_arrival' => (bool) $this->is_new_arrival,
            'is_try_on_enabled' => (bool) $this->is_try_on_enabled,
            'sort_order' => (int) $this->sort_order,
            'thumbnail_url' => $this->image_url,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at?->format('d M, Y'),
            'galleries' => $this->galleries
                ->where('type', 'galleries')
                ->values()
                ->map(function ($gallery) {
                return [
                    'id' => $gallery->id,
                    'name' => $gallery->name,
                    'image_url' => \Storage::disk('item')->url($gallery->name),
                    'created_at' => $gallery->created_at?->format('d M, Y'),
                ];
            }),
        ];
    }
}
