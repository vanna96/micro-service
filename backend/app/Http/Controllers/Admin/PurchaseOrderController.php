<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Tenant;
use App\Repositories\PurchaseOrderRepository;
use App\Services\PurchaseOrderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PurchaseOrderController extends Controller
{
    public function __construct(
        protected PurchaseOrderRepository $purchaseOrders,
        protected PurchaseOrderService $purchaseOrderService,
    ) {
        $this->middleware('admin.permission:purchase_orders.view')->only(['index', 'show', 'searchItems', 'getExchangeRates']);
        $this->middleware('admin.permission:purchase_orders.create')->only(['create', 'store']);
        $this->middleware('admin.permission:purchase_orders.edit')->only(['edit', 'update', 'cancel', 'markOrdered', 'markDraft']);
        $this->middleware('admin.permission:purchase_orders.receive')->only(['receive']);
        $this->middleware('admin.permission:purchase_orders.delete')->only(['destroy']);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));
        $vendorId = $request->integer('vendor_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $selectedTenant = $this->requiredTenant($request);

        $orders = $this->purchaseOrders->getAdminListing(
            search: $search,
            status: $status ?: null,
            vendorId: $vendorId,
            branchId: $branchId
        );
        $statusCounts = $this->purchaseOrders->getStatusCounts($search);

        $vendors = Customer::query()->vendors()->where('status', 'Active')->orderBy('name')->get();
        $branches = Branch::query()->where('status', 'Active')->orderBy('name')->get();

        return view('admin.purchase-orders.index', [
            'orders' => $orders,
            'statusCounts' => $statusCounts,
            'vendors' => $vendors,
            'branches' => $branches,
            'search' => $search,
            'selectedStatus' => $status,
            'selectedVendorId' => $vendorId,
            'selectedBranchId' => $branchId,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $defaultPoNumber = $this->purchaseOrderService->generatePoNumber();

        $vendors = Customer::query()->vendors()->where('status', 'Active')->orderBy('name')->get();
        $branches = Branch::query()->where('status', 'Active')->orderBy('sort_order')->orderBy('name')->get();
        $currencies = Currency::query()->where('status', 'Active')->orderBy('sort_order')->get();
        $baseCurrency = tenant_base_currency() ?: $currencies->first();

        // Do not preload all 10k items - only load items from old('items') if validation redirected back
        $oldItemIds = collect(old('items', []))->pluck('item_id')->filter()->unique()->values();
        $items = $oldItemIds->isNotEmpty()
            ? Item::query()
                ->with(['uomGroup.units.unit', 'variants', 'category', 'currency'])
                ->purchase()
                ->where('stock_control', true)
                ->whereIn('id', $oldItemIds)
                ->get()
            : collect();

        $purchaseOrder = new PurchaseOrder([
            'po_number' => $defaultPoNumber,
            'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_DRAFT,
            'currency_id' => $baseCurrency?->id,
            'currency_code' => $baseCurrency?->code ?: 'USD',
        ]);

        $orderDate = $purchaseOrder->order_date ? $purchaseOrder->order_date->format('Y-m-d') : now()->toDateString();
        $exchangeRates = app(\App\Repositories\RateIndexRepository::class)->getRatesForDate($orderDate, $baseCurrency);

        return view('admin.purchase-orders.create', [
            'purchaseOrder' => $purchaseOrder,
            'vendors' => $vendors,
            'branches' => $branches,
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency,
            'exchangeRates' => $exchangeRates,
            'items' => $items,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requiredTenant($request);
        $validated = $this->validatePurchaseOrder($request);

        $attributes = $validated['header'];
        $attributes['created_by'] = auth()->id();
        $items = $validated['items'];

        $action = (string) $request->input('submit_action', 'save');
        if ($action === 'ordered') {
            $attributes['status'] = PurchaseOrder::STATUS_ORDERED;
        } elseif ($action === 'draft') {
            $attributes['status'] = PurchaseOrder::STATUS_DRAFT;
        } elseif ($action === 'cancel') {
            $attributes['status'] = PurchaseOrder::STATUS_CANCELLED;
        }

        $po = $this->purchaseOrders->createWithItems($attributes, $items);

        // If user submitted form with 'receive' action (or selected Received status), immediately stock in
        if ($action === 'receive' || $attributes['status'] === PurchaseOrder::STATUS_RECEIVED) {
            try {
                $this->purchaseOrderService->receive($po);
                return redirect()
                    ->route('admin.purchase-orders.show', ['purchase_order' => $po->id])
                    ->with('status', "Purchase order #{$po->po_number} created and inventory stocked in successfully.");
            } catch (Throwable $e) {
                return redirect()
                    ->route('admin.purchase-orders.show', ['purchase_order' => $po->id])
                    ->with('warning', "Purchase order created, but stock-in encountered an issue: {$e->getMessage()}");
            }
        }

        $statusLabel = $po->status;
        return redirect()
            ->route('admin.purchase-orders.show', ['purchase_order' => $po->id])
            ->with('status', "Purchase order #{$po->po_number} saved as {$statusLabel} successfully.");
    }

    public function show(Request $request, string $purchaseOrder): View
    {
        $selectedTenant = $this->requiredTenant($request);
        $po = PurchaseOrder::query()
            ->with([
                'vendor',
                'branch',
                'currency',
                'creator',
                'items.item.uomGroup.units.unit',
                'items.item.currency',
                'items.variant',
                'items.unitOfMeasure',
            ])
            ->findOrFail((int) $purchaseOrder);

        $orderDate = $po->order_date ? $po->order_date->format('Y-m-d') : now()->toDateString();
        $exchangeRates = app(\App\Repositories\RateIndexRepository::class)->getRatesForDate($orderDate, $po->currency);

        return view('admin.purchase-orders.show', [
            'order' => $po,
            'exchangeRates' => $exchangeRates,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function edit(Request $request, string $purchaseOrder): View|RedirectResponse
    {
        $selectedTenant = $this->requiredTenant($request);
        $po = PurchaseOrder::query()
            ->with(['items.item', 'items.variant', 'items.unitOfMeasure'])
            ->findOrFail((int) $purchaseOrder);

        if ($po->isReceived()) {
            return redirect()
                ->route('admin.purchase-orders.show', ['purchase_order' => $po->id])
                ->with('error', "Purchase order #{$po->po_number} has already been received and cannot be edited.");
        }

        $vendors = Customer::query()->vendors()->where('status', 'Active')->orderBy('name')->get();
        $branches = Branch::query()->where('status', 'Active')->orderBy('sort_order')->orderBy('name')->get();
        $currencies = Currency::query()->where('status', 'Active')->orderBy('sort_order')->get();
        $baseCurrency = tenant_base_currency() ?: $currencies->first();

        // Only load items present in this purchase order (and old('items') if redirected back)
        $itemIds = $po->items->pluck('item_id')
            ->concat(collect(old('items', []))->pluck('item_id'))
            ->filter()
            ->unique()
            ->values();

        $items = $itemIds->isNotEmpty()
            ? Item::query()
                ->with(['uomGroup.units.unit', 'variants', 'category', 'currency'])
                ->whereIn('id', $itemIds)
                ->get()
            : collect();

        $orderDate = $po->order_date ? $po->order_date->format('Y-m-d') : now()->toDateString();
        $exchangeRates = app(\App\Repositories\RateIndexRepository::class)->getRatesForDate($orderDate, $baseCurrency);

        return view('admin.purchase-orders.edit', [
            'purchaseOrder' => $po,
            'vendors' => $vendors,
            'branches' => $branches,
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency,
            'exchangeRates' => $exchangeRates,
            'items' => $items,
            'selectedTenant' => $selectedTenant,
        ]);
    }

    public function update(Request $request, string $purchaseOrder): RedirectResponse
    {
        $this->requiredTenant($request);
        $po = PurchaseOrder::query()->findOrFail((int) $purchaseOrder);

        if ($po->isReceived()) {
            return redirect()
                ->route('admin.purchase-orders.show', ['purchase_order' => $po->id])
                ->with('error', "Purchase order #{$po->po_number} has already been received and cannot be updated.");
        }

        $validated = $this->validatePurchaseOrder($request, $po->id);
        $action = (string) $request->input('submit_action', 'save');
        if ($action === 'ordered') {
            $validated['header']['status'] = PurchaseOrder::STATUS_ORDERED;
        } elseif ($action === 'draft') {
            $validated['header']['status'] = PurchaseOrder::STATUS_DRAFT;
        } elseif ($action === 'cancel') {
            $validated['header']['status'] = PurchaseOrder::STATUS_CANCELLED;
        }

        $updatedPo = $this->purchaseOrders->updateWithItems($po, $validated['header'], $validated['items']);

        if ($action === 'receive' || $validated['header']['status'] === PurchaseOrder::STATUS_RECEIVED) {
            try {
                $this->purchaseOrderService->receive($updatedPo);
                return redirect()
                    ->route('admin.purchase-orders.show', ['purchase_order' => $updatedPo->id])
                    ->with('status', "Purchase order #{$updatedPo->po_number} updated and inventory stocked in successfully.");
            } catch (Throwable $e) {
                return redirect()
                    ->route('admin.purchase-orders.show', ['purchase_order' => $updatedPo->id])
                    ->with('warning', "Purchase order updated, but stock-in failed: {$e->getMessage()}");
            }
        }

        $statusLabel = $updatedPo->status;
        return redirect()
            ->route('admin.purchase-orders.show', ['purchase_order' => $updatedPo->id])
            ->with('status', "Purchase order #{$updatedPo->po_number} updated as {$statusLabel} successfully.");
    }

    public function receive(Request $request, string $purchaseOrder): JsonResponse|RedirectResponse
    {
        $this->requiredTenant($request);
        $po = PurchaseOrder::query()->findOrFail((int) $purchaseOrder);

        try {
            $result = $this->purchaseOrderService->receive($po);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase order #{$po->po_number} received and inventory updated.",
                    'data' => $result,
                ]);
            }

            return redirect()
                ->route('admin.purchase-orders.show', ['purchase_order' => $po->id])
                ->with('status', "Purchase order #{$po->po_number} marked as Received. Inventory has been stocked in successfully.");
        } catch (DomainException $de) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $de->getMessage()], 422);
            }
            return redirect()
                ->back()
                ->with('error', $de->getMessage());
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => "Failed to receive order: {$e->getMessage()}"], 500);
            }
            return redirect()
                ->back()
                ->with('error', "Failed to receive order: {$e->getMessage()}");
        }
    }

    public function cancel(Request $request, string $purchaseOrder): JsonResponse|RedirectResponse
    {
        $this->requiredTenant($request);
        $po = PurchaseOrder::query()->findOrFail((int) $purchaseOrder);
        $reason = trim((string) $request->input('reason', ''));

        try {
            $this->purchaseOrderService->cancel($po, $reason ?: null);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase order #{$po->po_number} has been cancelled.",
                ]);
            }

            return redirect()
                ->back()
                ->with('status', "Purchase order #{$po->po_number} has been cancelled.");
        } catch (DomainException $de) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $de->getMessage()], 422);
            }
            return redirect()
                ->back()
                ->with('error', $de->getMessage());
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => "Failed to cancel order: {$e->getMessage()}"], 500);
            }
            return redirect()
                ->back()
                ->with('error', "Failed to cancel order: {$e->getMessage()}");
        }
    }

    public function markOrdered(Request $request, string $purchaseOrder): JsonResponse|RedirectResponse
    {
        $this->requiredTenant($request);
        $po = PurchaseOrder::query()->findOrFail((int) $purchaseOrder);

        try {
            $this->purchaseOrderService->markOrdered($po);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase order #{$po->po_number} marked as Ordered.",
                ]);
            }

            return redirect()
                ->back()
                ->with('status', "Purchase order #{$po->po_number} marked as Ordered.");
        } catch (DomainException $de) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $de->getMessage()], 422);
            }
            return redirect()
                ->back()
                ->with('error', $de->getMessage());
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => "Failed to update order: {$e->getMessage()}"], 500);
            }
            return redirect()
                ->back()
                ->with('error', "Failed to update order: {$e->getMessage()}");
        }
    }

    public function markDraft(Request $request, string $purchaseOrder): JsonResponse|RedirectResponse
    {
        $this->requiredTenant($request);
        $po = PurchaseOrder::query()->findOrFail((int) $purchaseOrder);

        try {
            $this->purchaseOrderService->markDraft($po);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Purchase order #{$po->po_number} reverted to Draft.",
                ]);
            }

            return redirect()
                ->back()
                ->with('status', "Purchase order #{$po->po_number} reverted to Draft.");
        } catch (DomainException $de) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $de->getMessage()], 422);
            }
            return redirect()
                ->back()
                ->with('error', $de->getMessage());
        } catch (Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => "Failed to update order: {$e->getMessage()}"], 500);
            }
            return redirect()
                ->back()
                ->with('error', "Failed to update order: {$e->getMessage()}");
        }
    }

    public function destroy(Request $request, string $purchaseOrder): RedirectResponse
    {
        $this->requiredTenant($request);
        $po = PurchaseOrder::query()->findOrFail((int) $purchaseOrder);

        if ($po->isReceived()) {
            return redirect()
                ->back()
                ->with('error', "Cannot delete purchase order #{$po->po_number} because inventory has already been received.");
        }

        $poNumber = $po->po_number;
        $po->delete();

        return redirect()
            ->route('admin.purchase-orders.index')
            ->with('status', "Purchase order #{$poNumber} deleted successfully.");
    }

    protected function validatePurchaseOrder(Request $request, ?int $ignoreId = null): array
    {
        $header = $request->validate([
            'po_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique((new PurchaseOrder())->getTable(), 'po_number')->ignore($ignoreId),
            ],
            'vendor_id' => [
                'nullable',
                'integer',
                Rule::exists((new Customer())->getTable(), 'id'),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists((new Branch())->getTable(), 'id'),
            ],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'status' => ['required', 'string', Rule::in(PurchaseOrder::STATUSES)],
            'currency_id' => ['nullable', 'integer', Rule::exists((new Currency())->getTable(), 'id')],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $currencyCode = 'USD';
        if (! empty($header['currency_id'])) {
            $currency = Currency::query()->find($header['currency_id']);
            $currencyCode = $currency?->code ?: 'USD';
        }
        $header['currency_code'] = strtoupper($header['currency_code'] ?? $currencyCode);
        $header['tax_amount'] = (float) ($header['tax_amount'] ?? 0);
        $header['shipping_amount'] = (float) ($header['shipping_amount'] ?? 0);
        $header['discount_amount'] = (float) ($header['discount_amount'] ?? 0);

        $itemsInput = $request->input('items', []);
        if (empty($itemsInput) || ! is_array($itemsInput)) {
            abort(422, 'Please add at least one item line to the purchase order.');
        }

        $validatedLines = [];
        foreach ($itemsInput as $index => $line) {
            $item = validator($line, [
                'item_id' => [
                    'required',
                    'integer',
                    Rule::exists((new Item())->getTable(), 'id')->where(function ($query) {
                        $query->where('purchase', true)->where('stock_control', true);
                    }),
                ],
                'item_variant_id' => ['nullable', 'integer'],
                'uom_id' => ['nullable', 'integer'],
                'quantity' => ['required', 'numeric', 'gt:0'],
                'unit_cost' => ['required', 'numeric', 'min:0'],
                'notes' => ['nullable', 'string', 'max:255'],
            ], [
                'item_id.exists' => 'The selected item is invalid, not available for purchase, or has stock control disabled.',
            ])->validate();

            $validatedLines[] = $item;
        }

        // Validate that current day exchange rate is set for any foreign item currency
        $orderCurrencyCode = $header['currency_code'];
        $orderDate = $header['order_date'] ?? now()->toDateString();
        $dateRates = app(\App\Repositories\RateIndexRepository::class)->getRatesForDate($orderDate);

        $itemIds = collect($validatedLines)->pluck('item_id')->map(fn ($id) => (int) $id)->unique()->all();
        $itemsWithCurrency = Item::query()->with('currency')->whereIn('id', $itemIds)->get();

        $unconfiguredCurrencies = [];
        foreach ($itemsWithCurrency as $itemModel) {
            $itemCode = strtoupper(trim((string) ($itemModel->currency?->code ?: 'USD')));
            if ($itemCode !== $orderCurrencyCode) {
                if (! in_array($itemCode, $dateRates['configured_codes'] ?? [], true)) {
                    $unconfiguredCurrencies[$itemCode] = $itemModel->currency?->name ?: $itemCode;
                }
            }
        }

        if (! empty($unconfiguredCurrencies)) {
            $codesList = implode(', ', array_keys($unconfiguredCurrencies));
            throw \Illuminate\Validation\ValidationException::withMessages([
                'currency_id' => "The exchange rate for {$codesList} is not configured for {$orderDate}. Exchange rates must be configured for the current day. Please set up the exchange rate in Exchange Rates before creating an order with this item.",
            ]);
        }

        return [
            'header' => $header,
            'items' => $validatedLines,
        ];
    }

    public function searchItems(Request $request): JsonResponse
    {
        $this->requiredTenant($request);

        $q = trim((string) $request->input('q', ''));
        $limit = (int) $request->input('limit', 25);
        $limit = max(1, min($limit, 50));

        $items = Item::query()
            ->with(['uomGroup.units.unit', 'variants', 'category', 'currency'])
            ->purchase()
            ->where('stock_control', true)
            ->where('status', 'Active')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('foreign_name', 'like', "%{$q}%")
                        ->orWhereHas('category', function ($catQuery) use ($q) {
                            $catQuery->where('name', 'like', "%{$q}%");
                        })
                        ->orWhereHas('variants', function ($varQuery) use ($q) {
                            $varQuery->where('sku', 'like', "%{$q}%")
                                ->orWhere('barcode', 'like', "%{$q}%");
                        });
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        $baseCurrency = tenant_base_currency();
        $data = $items->map(function ($it) use ($baseCurrency) {
            $currency = $it->currency ?: $baseCurrency;
            return [
                'id' => $it->id,
                'name' => $it->name,
                'sku' => $it->sku,
                'barcode' => $it->variants?->firstWhere('barcode', '!=', null)?->barcode ?? '',
                'category_name' => $it->category?->name ?? '',
                'cost_price' => (float) ($it->cost_price ?: $it->price ?: 0),
                'currency_id' => $currency?->id,
                'currency_code' => strtoupper((string) ($currency?->code ?: 'USD')),
                'currency_symbol' => (string) ($currency?->symbol ?: ($currency?->code ?: '$')),
                'currency_decimals' => currency_decimal_places($currency),
                'stock_control' => (bool) $it->stock_control,
                'current_stock' => (int) $it->stock,
                'has_variants' => $it->variants && $it->variants->isNotEmpty(),
                'variants' => $it->variants ? $it->variants->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'sku' => $v->sku,
                        'name' => $v->sku ?: 'Variant #' . $v->id,
                        'stock' => (int) $v->stock,
                        'price' => (float) ($v->price ?: 0),
                    ];
                })->values()->all() : [],
                'has_uom' => $it->uomGroup && $it->uomGroup->units && $it->uomGroup->units->isNotEmpty(),
                'uom_units' => ($it->uomGroup && $it->uomGroup->units) ? $it->uomGroup->units->map(function ($u) {
                    return [
                        'id' => $u->unit_of_measure_id ?: $u->id,
                        'name' => $u->unit?->name ?: $u->unit?->code ?: 'Unit',
                        'factor' => (float) ($u->conversion_factor_to_base ?: 1),
                    ];
                })->values()->all() : [],
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'items' => $data,
        ]);
    }

    public function getExchangeRates(Request $request): JsonResponse
    {
        $this->requiredTenant($request);

        $date = (string) $request->input('date', now()->toDateString());
        $rates = app(\App\Repositories\RateIndexRepository::class)->getRatesForDate($date);

        return response()->json([
            'success' => true,
            'rates' => $rates,
        ]);
    }

    protected function requiredTenant(?Request $request = null): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        return $tenant;
    }
}
