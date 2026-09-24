<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\PromotionPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartPricingController extends Controller
{
    public function __construct(protected PromotionPricingService $promotionPricing)
    {
    }

    public function price(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists((new Item())->getTable(), 'id')],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.uom_id' => ['nullable', 'integer', 'min:1'],
            'items.*.option_value_ids' => ['nullable', 'array'],
            'items.*.option_value_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(
            $this->promotionPricing->price($validated['items']),
            200
        );
    }
}
