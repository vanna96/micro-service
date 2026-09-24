<?php

namespace App\Http\Controllers\API\V1\Mobile;

use App\Events\PosStockUpdatedEvent;
use App\Http\Controllers\API\V1\Mobile\Concerns\BuildsMobilePayloads;
use App\Http\Controllers\API\V1\Mobile\Concerns\InteractsWithMobileUsers;
use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramOrderNotificationJob;
use App\Models\CartItem;
use App\Models\Item;
use App\Models\Notification;
use App\Models\PosSale;
use App\Services\PromotionPricingService;
use App\Services\SaleStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use BuildsMobilePayloads;
    use InteractsWithMobileUsers;

    public function __construct(
        protected PromotionPricingService $promotionPricing,
        protected SaleStockService $saleStock
    ) {
    }

    public function index(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);
        $search = trim((string) $request->get('search', ''));

        $sales = PosSale::query()
            ->with(['items.item'])
            ->where('user_id', $centralUser->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('items', function ($itemQuery) use ($search) {
                            $itemQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 20);

        return response()->json([
            'success' => true,
            'data' => collect($sales->items())
                ->map(fn (PosSale $sale) => $this->mobilePosSalePayload($sale))
                ->values(),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                'last_page' => $sales->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $order)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);

        $sale = PosSale::query()
            ->with(['items.item', 'payments'])
            ->where('user_id', $centralUser->id)
            ->findOrFail((int) $order);

        return response()->json([
            'success' => true,
            'data' => $this->mobilePosSalePayload($sale),
        ]);
    }

    public function store(Request $request)
    {
        $centralUser = $this->currentCentralUser($request);
        $this->ensureTenantAccess($centralUser);
        $this->ensureTenantUserMirror($centralUser);

        $validated = $request->validate([
            'address_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists((new Item())->getTable(), 'id')->where(function ($query) {
                    $query->where('status', 'Active')->where('sale', true);
                }),
            ],
            'items.*.variant_id' => ['nullable', 'integer', 'min:1'],
            'items.*.uom_id' => ['nullable', 'integer', 'min:1'],
            'items.*.option_value_ids' => ['nullable', 'array'],
            'items.*.option_value_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', Rule::in(['Cash', 'Card', 'QR / UPI', 'Bank'])],
            'delivery_method' => ['nullable', 'string', Rule::in(['Home Delivery', 'Pickup'])],
            'currency_mode' => ['nullable', 'string', Rule::in(['native', 'base'])],
            'expected_subtotal' => ['nullable', 'numeric', 'min:0'],
            'expected_discount_total' => ['nullable', 'numeric', 'min:0'],
            'expected_total' => ['nullable', 'numeric', 'min:0'],
            'expected_promotion_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'items.required' => 'Your cart is empty. Please add items before placing an order.',
            'items.*.item_id.exists' => 'One or more items in your cart are no longer available in the store catalog. Please remove them and try again.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
        ]);

        $saleFrom = 'mobile';
        $paymentMethod = (string) ($validated['payment_method'] ?? 'Cash');
        $normalizeToBase = ($validated['currency_mode'] ?? 'base') === 'base';
        $baseCurrency = tenant_base_currency();
        $invoiceNumber = $this->generateInvoiceNumber();

        [$sale, $stockItemIds] = DB::transaction(function () use (
            $centralUser,
            $validated,
            $saleFrom,
            $paymentMethod,
            $baseCurrency,
            $invoiceNumber,
            $normalizeToBase
        ) {
            $pricing = $this->promotionPricing->price(
                $validated['items'],
                $normalizeToBase,
                true
            );
            $this->assertExpectedPricing($validated, $pricing);
            $stockItemIds = $this->saleStock->reserve($pricing['items']);

            $currencyCode = $normalizeToBase
                ? ($baseCurrency?->code ?? 'USD')
                : 'USD';
            $totalItems = (int) collect($pricing['items'])->sum('quantity');
            $promoId = data_get($pricing, 'applied_promotion.id');
            $posSale = PosSale::query()->create([
                'client_token' => (string) Str::uuid(),
                'sale_key' => (string) Str::uuid(),
                'status' => 'completed',
                'reference' => $invoiceNumber,
                'invoice_number' => $invoiceNumber,
                'user_id' => $centralUser->id,
                'sale_from' => $saleFrom,
                'customer_name' => $centralUser->name ?? 'Mobile Customer',
                'order_type' => $validated['delivery_method'] ?? 'Home Delivery',
                'base_currency_code' => $currencyCode,
                'item_count' => $totalItems,
                'discount_type' => 'fixed',
                'discount_value' => (float) $pricing['discount_total'],
                'promotion_id' => is_numeric($promoId) ? (int) $promoId : null,
                'promotion_name' => data_get($pricing, 'applied_promotion.name'),
                'promotion_discount_base' => (float) $pricing['discount_total'],
                'tax_percent' => 0,
                'subtotal_base' => (float) $pricing['subtotal'],
                'discount_base' => (float) $pricing['discount_total'],
                'tax_base' => 0,
                'service_fee_base' => 0,
                'total_base' => (float) $pricing['final_total'],
                'payment_method' => $paymentMethod,
                'cash_received_base' => (float) $pricing['final_total'],
                'change_base' => 0,
                'completed_at' => now(),
                'stock_deducted_at' => now(),
                'notes' => $validated['note'] ?? null,
                'snapshot' => [
                    'invoiceNumber' => $invoiceNumber,
                    'sale_from' => $saleFrom,
                    'user' => [
                        'id' => $centralUser->id,
                        'name' => $centralUser->name ?? '',
                        'email' => $centralUser->email ?? '',
                    ],
                    'items' => $pricing['items'],
                ],
            ]);

            foreach ($pricing['items'] as $line) {
                $posSale->items()->create([
                    'item_id' => (int) $line['item_id'],
                    'item_variant_id' => $line['item_variant_id'] ? (int) $line['item_variant_id'] : null,
                    'sku' => (string) ($line['sku'] ?? ''),
                    'name' => (string) $line['name'],
                    'uom_code' => $line['uom_code'],
                    'uom_name' => $line['uom_name'],
                    'selected_options' => $line['selected_options'],
                    'quantity' => (float) $line['quantity'],
                    'currency_code' => $currencyCode,
                    'unit_price' => (float) $line['unit_price'],
                    'exchange_rate' => 1.0,
                    'unit_price_base' => (float) $line['unit_price'],
                    'line_subtotal_base' => (float) $line['line_subtotal'],
                    'discount_base' => (float) $line['discount_amount'],
                    'tax_base' => 0,
                    'line_total_base' => (float) $line['line_total'],
                ]);
            }

            $posSale->payments()->create([
                'method' => $paymentMethod,
                'currency_code' => $currencyCode,
                'currency_symbol' => $baseCurrency?->symbol ?? '$',
                'decimal_places' => 2,
                'amount' => (float) $pricing['final_total'],
                'exchange_rate' => 1.0,
                'amount_base' => (float) $pricing['final_total'],
            ]);

            Notification::query()->create([
                'user_id' => $centralUser->id,
                'type' => 'Order',
                'title' => 'Order placed',
                'message' => 'Your order '.$invoiceNumber.' was placed successfully.',
                'data' => [
                    'sale_id' => $posSale->id,
                    'invoice_number' => $invoiceNumber,
                ],
            ]);

            CartItem::query()
                ->where('user_id', $centralUser->id)
                ->delete();

            return [$posSale, $stockItemIds];
        });

        SendTelegramOrderNotificationJob::dispatch('pos_sale', $sale->id);
        try {
            broadcast(new PosStockUpdatedEvent(
                $stockItemIds,
                $sale->client_token,
                tenant()?->id
            ));
        } catch (\Throwable) {
            // The sale and stock reservation are already committed. A realtime
            // broadcast failure must not turn a successful checkout into an error.
        }

        $sale->load(['items.item', 'payments']);

        return response()->json([
            'success' => true,
            'message' => 'Sale placed successfully.',
            'data' => $this->mobilePosSalePayload($sale),
        ], 201);
    }

    private function assertExpectedPricing(array $validated, array $pricing): void
    {
        $errors = [];
        $amountChecks = [
            'expected_subtotal' => 'subtotal',
            'expected_discount_total' => 'discount_total',
            'expected_total' => 'final_total',
        ];

        foreach ($amountChecks as $expectedKey => $actualKey) {
            if (array_key_exists($expectedKey, $validated)
                && abs((float) $validated[$expectedKey] - (float) $pricing[$actualKey]) > 0.01) {
                $errors[$expectedKey] = 'Cart pricing changed. Review the latest price before checking out.';
            }
        }

        if (array_key_exists('expected_promotion_id', $validated)) {
            $expectedPromotionId = $validated['expected_promotion_id'] !== null
                ? (int) $validated['expected_promotion_id']
                : null;
            $actualPromotionId = data_get($pricing, 'applied_promotion.id');
            $actualPromotionId = $actualPromotionId !== null ? (int) $actualPromotionId : null;

            if ($expectedPromotionId !== $actualPromotionId) {
                $errors['expected_promotion_id'] = 'The applied promotion changed. Review the cart before checking out.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
