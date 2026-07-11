<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\PromotionPricingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartPricingController extends Controller
{
    public function __construct(protected PromotionPricingService $promotionPricing) {}

    public function price(Request $request)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists((new Item())->getTable(), 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->promotionPricing->price($validated['items']),
            200
        );
    }
}
