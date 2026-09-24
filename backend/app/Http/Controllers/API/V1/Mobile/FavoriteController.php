<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FavoriteController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function index(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        $favorites = Favorite::query()
            ->with([
                'item.category',
                'item.branch',
                'item.currency',
                'item.image',
                'item.galleries',
                'item.uomGroup.units' => fn ($query) => $query->where('status', 'Active')->orderBy('sort_order'),
                'item.uomGroup.units.unit',
                'item.uomPrices',
                'item.optionGroups' => fn ($query) => $query->where('status', 'Active'),
                'item.optionGroups.values' => fn ($query) => $query->where('status', 'Active'),
                'item.variants' => fn ($query) => $query->where('status', 'Active'),
                'item.variants.optionValues',
            ])
            ->where('user_id', $centralUser->id)
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'ids' => $favorites->pluck('item_id')->map(fn ($id) => (int) $id)->values(),
                'products' => $favorites
                    ->filter(fn (Favorite $favorite) => $favorite->item)
                    ->map(fn (Favorite $favorite) => $this->mobileItemPayload($favorite->item))
                    ->values(),
            ],
        ]);
    }

    public function toggle(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        $validated = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists((new Item())->getTable(), 'id')],
        ]);

        $favorite = Favorite::query()
            ->where('user_id', $centralUser->id)
            ->where('item_id', (int) $validated['item_id'])
            ->first();

        if ($favorite) {
            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Favorite removed successfully.',
                'data' => [
                    'item_id' => (int) $validated['item_id'],
                    'is_favorite' => false,
                ],
            ]);
        }

        Favorite::query()->create([
            'user_id' => $centralUser->id,
            'item_id' => (int) $validated['item_id'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Favorite added successfully.',
            'data' => [
                'item_id' => (int) $validated['item_id'],
                'is_favorite' => true,
            ],
        ]);
    }
}
