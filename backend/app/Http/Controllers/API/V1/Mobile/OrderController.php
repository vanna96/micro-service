<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Order;
use App\Services\PromotionPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function __construct(protected PromotionPricingService $promotionPricing) {}

    public function index(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);
        $search = trim((string) $request->get('search', ''));

        $orders = Order::query()
            ->with(['address', 'items'])
            ->where('user_id', $centralUser->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('order_number', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'success' => true,
            'data' => collect($orders->items())
                ->map(fn (Order $order) => $this->mobileOrderPayload($order))
                ->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $order)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        $orderModel = Order::query()
            ->with(['address', 'items'])
            ->where('user_id', $centralUser->id)
            ->findOrFail((int) $order);

        return response()->json([
            'success' => true,
            'data' => $this->mobileOrderPayload($orderModel),
        ]);
    }

    public function store(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);
        $this->ensureTenantUserMirror($centralUser);

        $validated = $request->validate([
            'address_id' => ['nullable', 'integer', Rule::exists((new Address())->getTable(), 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists((new Item())->getTable(), 'id')],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', 'max:64'],
            'delivery_method' => ['nullable', 'string', 'max:64'],
        ]);

        $address = null;
        if (! empty($validated['address_id'])) {
            $address = Address::query()
                ->where('user_id', $centralUser->id)
                ->findOrFail((int) $validated['address_id']);
        }

        $pricing = $this->promotionPricing->price($validated['items']);
        $itemSnapshots = Item::query()
            ->with(['image', 'currency'])
            ->whereIn('id', collect($pricing['items'])->pluck('item_id')->values())
            ->get()
            ->keyBy('id');

        $currencyCode = optional(optional($itemSnapshots->first())->currency)->code;
        $totalItems = (int) collect($pricing['items'])->sum('quantity');

        $order = DB::transaction(function () use ($centralUser, $address, $validated, $pricing, $itemSnapshots, $currencyCode, $totalItems) {
            $order = Order::query()->create([
                'user_id' => $centralUser->id,
                'address_id' => $address?->id,
                'order_number' => $this->generateOrderNumber(),
                'status' => 'Placed',
                'payment_status' => 'Pending',
                'payment_method' => $validated['payment_method'] ?? 'Cash on Delivery',
                'delivery_method' => $validated['delivery_method'] ?? 'Home Delivery',
                'currency_code' => $currencyCode,
                'total_items' => $totalItems,
                'subtotal' => (float) $pricing['subtotal'],
                'discount_total' => (float) $pricing['discount_total'],
                'total' => (float) $pricing['final_total'],
                'note' => $validated['note'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($pricing['items'] as $line) {
                $item = $itemSnapshots->get($line['item_id']);

                $order->items()->create([
                    'item_id' => (int) $line['item_id'],
                    'sku' => (string) ($line['sku'] ?? ''),
                    'name' => (string) $line['name'],
                    'image_url' => $item?->image_url,
                    'quantity' => (int) $line['quantity'],
                    'unit_price' => (float) $line['unit_price'],
                    'line_subtotal' => (float) $line['line_subtotal'],
                    'discount_amount' => (float) $line['discount_amount'],
                    'line_total' => (float) $line['line_total'],
                ]);
            }

            Notification::query()->create([
                'user_id' => $centralUser->id,
                'type' => 'Order',
                'title' => 'Order placed',
                'message' => 'Your order ' . $order->order_number . ' was placed successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

            return $order;
        });

        $order->load(['address', 'items']);

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully.',
            'data' => $this->mobileOrderPayload($order),
        ], 201);
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
    }
}
