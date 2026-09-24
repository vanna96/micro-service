<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    /**
     * GET /mobile/cart
     * Return the user's current saved cart with full product info.
     */
    public function index(Request $request): JsonResponse
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        $cartItems = CartItem::query()
            ->with([
                'item.category',
                'item.branch',
                'item.currency',
                'item.image',
                'item.galleries',
                'item.uomGroup.units' => fn ($q) => $q->where('status', 'Active')->orderBy('sort_order'),
                'item.uomGroup.units.unit',
                'item.uomPrices',
                'item.optionGroups' => fn ($q) => $q->where('status', 'Active'),
                'item.optionGroups.values' => fn ($q) => $q->where('status', 'Active'),
                'item.variants' => fn ($q) => $q->where('status', 'Active'),
                'item.variants.optionValues',
            ])
            ->where('user_id', $centralUser->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cartItems->map(fn (CartItem $ci) => [
                'item_id' => (int) $ci->item_id,
                'quantity' => (int) $ci->quantity,
                'variant_id' => $ci->variant_id ? (int) $ci->variant_id : null,
                'uom_id' => $ci->uom_id ? (int) $ci->uom_id : null,
                'option_value_ids' => $ci->option_value_ids ?? [],
                'product' => $ci->item ? $this->mobileItemPayload($ci->item) : null,
            ])->values(),
        ]);
    }

    /**
     * POST /mobile/cart/sync
     * Replace the server-side cart with the payload sent by the app.
     * Payload: { items: [{ item_id, quantity, variant_id?, uom_id?, option_value_ids? }] }
     */
    public function sync(Request $request): JsonResponse
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists((new Item)->getTable(), 'id')->where(function ($query) {
                    $query->where('status', 'Active')->where('sale', true);
                }),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:0'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.uom_id' => ['nullable', 'integer'],
            'items.*.option_value_ids' => ['nullable', 'array'],
            'items.*.option_value_ids.*' => ['integer'],
        ], [
            'items.*.item_id.exists' => 'One or more items are no longer available for sale.',
        ]);

        $connection = (new CartItem())->getConnection();
        $savedCount = $connection->transaction(function () use ($centralUser, $validated): int {
            CartItem::query()->where('user_id', $centralUser->id)->delete();

            $savedCount = 0;
            foreach ($validated['items'] as $line) {
                $quantity = (int) ($line['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                CartItem::query()->create([
                    'user_id' => $centralUser->id,
                    'item_id' => (int) $line['item_id'],
                    'quantity' => $quantity,
                    'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
                    'uom_id' => isset($line['uom_id']) ? (int) $line['uom_id'] : null,
                    'option_value_ids' => array_values(array_unique($line['option_value_ids'] ?? [])),
                ]);
                $savedCount++;
            }

            return $savedCount;
        });

        return response()->json([
            'success' => true,
            'message' => 'Cart synced successfully.',
            'data' => [
                'count' => $savedCount,
            ],
        ]);
    }

    /**
     * DELETE /mobile/cart
     * Clear the user's entire cart from the database.
     */
    public function clear(Request $request): JsonResponse
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        CartItem::query()->where('user_id', $centralUser->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cart cleared successfully.',
        ]);
    }
}
