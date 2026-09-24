<?php

namespace App\Http\Controllers\API\V1\Pos;

use App\Events\PosStockUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Jobs\SendTelegramOrderNotificationJob;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PosSale;
use App\Models\RateIndexValue;
use App\Services\PromotionPricingService;
use App\Services\SaleStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosSaleController extends Controller
{
    public function __construct(
        protected PromotionPricingService $promotionPricing,
        protected SaleStockService $saleStock
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_token' => ['required', 'uuid'],
        ]);

        $sales = PosSale::query()
            ->where('client_token', $validated['client_token'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'current' => optional($sales->firstWhere('status', 'current'), fn (PosSale $sale) => $this->salePayload($sale)),
                'held' => $sales
                    ->where('status', 'held')
                    ->map(fn (PosSale $sale) => $this->salePayload($sale))
                    ->values(),
                'history' => $sales
                    ->where('status', 'completed')
                    ->take(100)
                    ->map(fn (PosSale $sale) => $this->salePayload($sale))
                    ->values(),
            ],
        ]);
    }

    public function saveCurrent(Request $request): JsonResponse
    {
        $validated = $this->validateSnapshot($request);

        $sale = DB::transaction(function () use ($validated) {
            $existing = PosSale::query()
                ->where('client_token', $validated['client_token'])
                ->where('sale_key', $validated['sale_id'])
                ->first();

            if (in_array($existing?->status, ['held', 'completed'], true)) {
                return $existing;
            }

            PosSale::query()
                ->where('client_token', $validated['client_token'])
                ->where('status', 'current')
                ->where('sale_key', '!=', $validated['sale_id'])
                ->delete();

            return PosSale::query()->updateOrCreate([
                'client_token' => $validated['client_token'],
                'sale_key' => $validated['sale_id'],
            ], [
                'status' => 'current',
                'reference' => null,
                'notes' => null,
                'snapshot' => $validated['snapshot'],
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => $this->salePayload($sale),
        ]);
    }

    public function completeCash(Request $request): JsonResponse
    {
        $request->merge(['payment_method' => 'Cash']);

        return $this->completePayment($request);
    }

    public function completePayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_token' => ['required', 'uuid'],
            'sale_id' => ['required', 'uuid'],
            'reference' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', 'in:Cash,Card,Bank'],
            'snapshot' => ['required', 'array'],
            'snapshot.cart' => ['required', 'array', 'min:1'],
            'snapshot.cart.*.product' => ['required', 'array'],
            'snapshot.cart.*.product.id' => [
                'required',
                'integer',
                Rule::exists((new Item)->getTable(), 'id')->where(function ($query) {
                    $query->where('status', 'Active')->where('sale', true);
                }),
            ],
            'snapshot.cart.*.product.sku' => ['nullable', 'string', 'max:64'],
            'snapshot.cart.*.product.name' => ['required', 'string', 'max:255'],
            'snapshot.cart.*.product.currency.code' => ['nullable', 'string', 'size:3'],
            'snapshot.cart.*.quantity' => ['required', 'integer', 'min:1'],
            'snapshot.cart.*.unitPrice' => ['nullable', 'numeric', 'min:0'],
            'snapshot.cart.*.selectedVariant.id' => ['nullable', 'integer', 'min:1'],
            'snapshot.cart.*.selectedUOM.id' => ['nullable', 'integer', 'min:1'],
            'snapshot.orderType' => ['required', 'string', 'in:Takeaway,Dine-in,Delivery'],
            'snapshot.discountType' => ['required', 'string', 'in:percentage,fixed'],
            'snapshot.discountValue' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'snapshot.taxPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'snapshot.serviceFee' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'totals' => ['required', 'array'],
            'totals.sub_total' => ['required', 'numeric', 'min:0'],
            'totals.discount_amount' => ['required', 'numeric', 'min:0'],
            'totals.tax_amount' => ['required', 'numeric', 'min:0'],
            'totals.service_fee' => ['required', 'numeric', 'min:0'],
            'totals.total_payable' => ['required', 'numeric', 'gt:0'],
            'tenders' => ['required', 'array', 'min:1'],
            'tenders.*.currency_code' => ['required', 'string', 'size:3', 'distinct'],
            'tenders.*.amount' => ['required', 'numeric', 'gt:0'],
            'tenders.*.provider' => ['nullable', 'string', 'max:64'],
            'tenders.*.reference' => ['nullable', 'string', 'max:128'],
            'tenders.*.metadata' => ['nullable', 'array'],
        ]);
        // Preserve the complete POS snapshot. Laravel's validated payload only
        // retains explicitly-addressed nested fields once snapshot.cart is used.
        $validated['snapshot'] = $request->input('snapshot');
        $validated['snapshot']['invoiceNumber'] = $validated['reference'];
        $paymentMethod = $validated['payment_method'];

        if ($paymentMethod !== 'Cash' && (
            count($validated['tenders']) !== 1
            || blank($validated['tenders'][0]['provider'] ?? null)
            || blank($validated['tenders'][0]['reference'] ?? null)
        )) {
            throw ValidationException::withMessages([
                'tenders' => "{$paymentMethod} payment requires a provider and transaction reference.",
            ]);
        }

        $baseCurrency = tenant_base_currency();
        if (! $baseCurrency) {
            throw ValidationException::withMessages([
                'base_currency' => 'Set an active base currency before accepting payment.',
            ]);
        }

        $tenderCurrencyCodes = collect($validated['tenders'])
            ->pluck('currency_code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->unique()
            ->values();
        $currencyCodes = $tenderCurrencyCodes
            ->push(strtoupper((string) $baseCurrency->code))
            ->unique()
            ->values();
        $currencies = Currency::query()
            ->where('status', 'Active')
            ->whereIn('code', $currencyCodes)
            ->get()
            ->keyBy(fn (Currency $currency) => strtoupper((string) $currency->code));

        if ($tenderCurrencyCodes->contains(fn (string $code) => ! $currencies->has($code))) {
            throw ValidationException::withMessages([
                'tenders' => 'One or more payment currencies are unavailable.',
            ]);
        }
        $tenantSettings = is_array(tenant()?->general_settings) ? tenant()->general_settings : [];
        $tenantTimezone = (string) ($tenantSettings['timezone'] ?? config('app.timezone', 'UTC'));
        $today = Carbon::now($tenantTimezone);
        $foreignCurrencyIds = $currencies
            ->reject(fn (Currency $currency) => strtoupper((string) $currency->code) === strtoupper((string) $baseCurrency->code))
            ->pluck('id');
        $rates = RateIndexValue::query()
            ->where('dataset_type', 'exchange_rate')
            ->where('year', $today->year)
            ->where('month', $today->month)
            ->where('day', $today->day)
            ->whereIn('currency_id', $foreignCurrencyIds)
            ->get()
            ->keyBy('currency_id');

        $tenders = collect($validated['tenders'])->map(function (array $tender) use ($baseCurrency, $currencies, $rates) {
            $currencyCode = strtoupper(trim((string) $tender['currency_code']));
            /** @var Currency $currency */
            $currency = $currencies->get($currencyCode);
            $decimalPlaces = currency_decimal_places($currency);

            if (currency_decimal_count($tender['amount']) > $decimalPlaces) {
                throw ValidationException::withMessages([
                    "tenders.{$currencyCode}.amount" => "{$currencyCode} accepts {$decimalPlaces} decimal places.",
                ]);
            }

            $amount = round_currency_amount($tender['amount'], $currency);
            $isBaseCurrency = $currencyCode === strtoupper((string) $baseCurrency->code);
            $rate = $isBaseCurrency ? 1.0 : (float) optional($rates->get($currency->id))->value;

            if (! ($rate > 0)) {
                throw ValidationException::withMessages([
                    "tenders.{$currencyCode}.rate" => "Today's exchange rate is not configured for {$currencyCode}.",
                ]);
            }

            return [
                'currency_code' => $currencyCode,
                'currency_symbol' => (string) ($currency->symbol ?: $currencyCode),
                'decimal_places' => $decimalPlaces,
                'amount' => $amount,
                'exchange_rate' => $rate,
                'base_amount' => round($amount / $rate, 8),
                'provider' => filled($tender['provider'] ?? null) ? trim((string) $tender['provider']) : null,
                'reference' => filled($tender['reference'] ?? null) ? trim((string) $tender['reference']) : null,
                'metadata' => $tender['metadata'] ?? null,
            ];
        })->values();

        $totalReceived = round((float) $tenders->sum('base_amount'), 8);
        $paymentTolerance = 0.5 / (10 ** currency_decimal_places($baseCurrency));
        $completedAt = Carbon::now($tenantTimezone);
        $paymentRows = $tenders->map(fn (array $tender) => [
            'method' => $paymentMethod,
            'currency_code' => $tender['currency_code'],
            'currency_symbol' => $tender['currency_symbol'],
            'decimal_places' => $tender['decimal_places'],
            'amount' => $tender['amount'],
            'exchange_rate' => $tender['exchange_rate'],
            'amount_base' => $tender['base_amount'],
            'provider' => $tender['provider'],
            'reference' => $tender['reference'],
            'metadata' => $tender['metadata'],
        ])->all();
        $pricingPayload = $this->pricingPayload($validated['snapshot']['cart']);
        $requestedCustomerId = data_get($validated, 'snapshot.customer.id');
        $customer = is_numeric($requestedCustomerId)
            ? Customer::query()
                ->with(['priceList' => fn ($query) => $query->where('status', 'Active')])
                ->where('status', 'Active')
                ->find((int) $requestedCustomerId)
            : null;
        [$discountType, $discountValue] = $this->resolvedManualDiscount(
            $validated['snapshot'],
            $customer
        );
        $authenticatedUserId = $request->user()?->id;

        [$sale, $stockItemIds, $completedNow] = DB::connection($this->tenantConnectionName())->transaction(function () use (
            $validated,
            $pricingPayload,
            $baseCurrency,
            $paymentMethod,
            $paymentRows,
            $tenders,
            $totalReceived,
            $paymentTolerance,
            $completedAt,
            $today,
            $customer,
            $discountType,
            $discountValue,
            $authenticatedUserId
        ) {
            $existing = PosSale::query()
                ->where('client_token', $validated['client_token'])
                ->where('sale_key', $validated['sale_id'])
                ->lockForUpdate()
                ->first();

            if ($existing?->status === 'completed') {
                return [$existing, [], false];
            }

            $pricing = $this->promotionPricing->price($pricingPayload, true, true);
            $totals = $this->authoritativeTotals(
                $validated,
                $pricing,
                $baseCurrency,
                $discountType,
                $discountValue,
                $paymentTolerance
            );

            if ($totalReceived + $paymentTolerance < $totals['total_payable']) {
                throw ValidationException::withMessages([
                    'tenders' => 'The combined payment received is less than the current amount payable.',
                ]);
            }

            $stockItemIds = $this->saleStock->reserve($pricing['items'], 'snapshot.cart');
            $itemRows = $this->buildAuthoritativeItemRows(
                $pricing['items'],
                $baseCurrency,
                $totals['subtotal'],
                $totals['discount_amount'],
                $totals['tax_amount']
            );
            $change = round(max(0, $totalReceived - $totals['total_payable']), 8);
            $payment = [
                'method' => $paymentMethod,
                'base_currency' => [
                    'code' => strtoupper((string) $baseCurrency->code),
                    'symbol' => (string) ($baseCurrency->symbol ?: $baseCurrency->code),
                    'decimal_places' => currency_decimal_places($baseCurrency),
                ],
                'rate_date' => $today->toDateString(),
                'tenders' => $tenders->all(),
                'total_received_base' => $totalReceived,
                'change_base' => $change,
                'completed_at' => $completedAt->toIso8601String(),
            ];
            $snapshot = array_merge($validated['snapshot'], [
                'discountType' => $discountType,
                'discountValue' => $discountValue,
                'subTotal' => $totals['subtotal'],
                'discountAmount' => $totals['discount_amount'],
                'taxAmount' => $totals['tax_amount'],
                'serviceFee' => $totals['service_fee'],
                'totalPayable' => $totals['total_payable'],
                'appliedPromotion' => $pricing['applied_promotion'],
                'promotionDiscountAmount' => $totals['promotion_discount'],
                'serverPricing' => $pricing,
                'paymentMethod' => $paymentMethod,
                'cashTender' => $payment,
            ]);
            if ($customer) {
                $snapshot['customer'] = array_merge((array) ($snapshot['customer'] ?? []), [
                    'id' => (int) $customer->id,
                    'code' => (string) $customer->code,
                    'name' => (string) $customer->name,
                ]);
            }

            $saleAttributes = [
                'status' => 'completed',
                'reference' => $validated['reference'],
                'invoice_number' => $validated['reference'],
                'customer_id' => $customer?->id,
                'customer_code' => $customer?->code,
                'customer_name' => $customer?->name,
                'user_id' => $authenticatedUserId,
                'sale_from' => 'pos',
                'order_type' => (string) $snapshot['orderType'],
                'base_currency_code' => strtoupper((string) $baseCurrency->code),
                'item_count' => (int) collect($itemRows)->sum('quantity'),
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'promotion_id' => data_get($pricing, 'applied_promotion.id'),
                'promotion_name' => data_get($pricing, 'applied_promotion.name'),
                'promotion_discount_base' => $totals['promotion_discount'],
                'tax_percent' => (float) $snapshot['taxPercent'],
                'subtotal_base' => $totals['subtotal'],
                'discount_base' => $totals['discount_amount'],
                'tax_base' => $totals['tax_amount'],
                'service_fee_base' => $totals['service_fee'],
                'total_base' => $totals['total_payable'],
                'payment_method' => $paymentMethod,
                'cash_received_base' => $totalReceived,
                'change_base' => $change,
                'completed_at' => $completedAt,
                'stock_deducted_at' => $completedAt,
                'notes' => null,
                'snapshot' => $snapshot,
            ];

            $sale = $existing ?? new PosSale([
                'client_token' => $validated['client_token'],
                'sale_key' => $validated['sale_id'],
            ]);
            $sale->fill($saleAttributes);
            $sale->save();
            $sale->items()->delete();
            $sale->payments()->delete();
            $sale->items()->createMany($itemRows);
            $sale->payments()->createMany($paymentRows);

            return [$sale, $stockItemIds, true];
        }, 3);

        if ($completedNow) {
            SendTelegramOrderNotificationJob::dispatch('pos_sale', $sale->id);
            try {
                broadcast(new PosStockUpdatedEvent(
                    $stockItemIds,
                    $sale->client_token,
                    tenant()?->id
                ));
            } catch (\Throwable) {
                // The sale and stock reservation are committed. Realtime
                // notification failure must not report checkout as failed.
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->salePayload($sale),
        ]);
    }

    public function hold(Request $request): JsonResponse
    {
        $validated = $this->validateSnapshot($request, true);

        $sale = DB::transaction(function () use ($validated) {
            $sale = PosSale::query()->updateOrCreate([
                'client_token' => $validated['client_token'],
                'sale_key' => $validated['sale_id'],
            ], [
                'status' => 'held',
                'reference' => $validated['reference'],
                'notes' => $validated['notes'] ?? null,
                'snapshot' => $validated['snapshot'],
            ]);

            PosSale::query()
                ->where('client_token', $validated['client_token'])
                ->where('status', 'current')
                ->where('id', '!=', $sale->getKey())
                ->delete();

            return $sale;
        });

        return response()->json([
            'success' => true,
            'data' => $this->salePayload($sale),
        ], 201);
    }

    public function restore(Request $request, string $sale): JsonResponse
    {
        $validated = $request->validate([
            'client_token' => ['required', 'uuid'],
        ]);

        $saleModel = PosSale::query()
            ->where('client_token', $validated['client_token'])
            ->where('status', 'held')
            ->findOrFail((int) $sale);

        DB::transaction(function () use ($saleModel, $validated) {
            PosSale::query()
                ->where('client_token', $validated['client_token'])
                ->where('status', 'current')
                ->delete();

            $saleModel->update(['status' => 'current']);
        });

        return response()->json([
            'success' => true,
            'data' => $this->salePayload($saleModel->fresh()),
        ]);
    }

    public function destroy(Request $request, string $sale): JsonResponse
    {
        $validated = $request->validate([
            'client_token' => ['required', 'uuid'],
        ]);

        $saleModel = PosSale::query()
            ->where('client_token', $validated['client_token'])
            ->where('status', 'held')
            ->findOrFail((int) $sale);

        $saleModel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Held sale deleted.',
        ]);
    }

    private function validateSnapshot(Request $request, bool $holding = false): array
    {
        $rules = [
            'client_token' => ['required', 'uuid'],
            'sale_id' => ['required', 'uuid'],
            'snapshot' => ['required', 'array'],
            'snapshot.cart' => ['present', 'array'],
        ];

        if ($holding) {
            $rules['reference'] = ['required', 'string', 'max:255'];
            $rules['notes'] = ['nullable', 'string', 'max:1000'];
        }

        $validated = $request->validate($rules);
        $validated['snapshot'] = $request->input('snapshot');

        return $validated;
    }

    private function salePayload(PosSale $sale): array
    {
        return [
            'id' => (int) $sale->id,
            'sale_id' => (string) $sale->sale_key,
            'status' => (string) $sale->status,
            'reference' => (string) ($sale->reference ?? ''),
            'notes' => (string) ($sale->notes ?? ''),
            'snapshot' => $sale->snapshot,
            'updated_at' => optional($sale->updated_at)->toIso8601String(),
            'invoice_number' => (string) ($sale->invoice_number ?? $sale->reference ?? ''),
            'customer_name' => (string) ($sale->customer_name ?? ''),
            'order_type' => (string) ($sale->order_type ?? ''),
            'payment_method' => (string) ($sale->payment_method ?? ''),
            'base_currency_code' => (string) ($sale->base_currency_code ?? ''),
            'item_count' => (int) ($sale->item_count ?? 0),
            'subtotal_base' => (float) ($sale->subtotal_base ?? 0),
            'discount_base' => (float) ($sale->discount_base ?? 0),
            'promotion_id' => $sale->promotion_id,
            'promotion_name' => (string) ($sale->promotion_name ?? ''),
            'promotion_discount_base' => (float) ($sale->promotion_discount_base ?? 0),
            'tax_base' => (float) ($sale->tax_base ?? 0),
            'service_fee_base' => (float) ($sale->service_fee_base ?? 0),
            'total_base' => (float) ($sale->total_base ?? 0),
            'completed_at' => optional($sale->completed_at)->toIso8601String(),
            'stock_deducted_at' => optional($sale->stock_deducted_at)->toIso8601String(),
        ];
    }

    private function pricingPayload(array $cart): array
    {
        return collect($cart)->map(function (array $line): array {
            $optionValueIds = collect((array) ($line['selectedVariants'] ?? []))
                ->pluck('id')
                ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            return [
                'item_id' => (int) data_get($line, 'product.id'),
                'variant_id' => is_numeric(data_get($line, 'selectedVariant.id'))
                    ? (int) data_get($line, 'selectedVariant.id')
                    : null,
                'uom_id' => is_numeric(data_get($line, 'selectedUOM.id'))
                    ? (int) data_get($line, 'selectedUOM.id')
                    : null,
                'option_value_ids' => $optionValueIds,
                'quantity' => (int) $line['quantity'],
            ];
        })->all();
    }

    private function resolvedManualDiscount(array $snapshot, ?Customer $customer): array
    {
        $priceList = $customer?->priceList;
        if ($priceList?->header_pricing_method === 'fixed') {
            return ['fixed', max(0, (float) ($priceList->header_fixed_price ?? 0))];
        }
        if ($priceList?->header_pricing_method === 'discount') {
            return ['percentage', max(0, min(100, (float) ($priceList->header_discount_percent ?? 0)))];
        }

        $type = (string) $snapshot['discountType'];
        $value = max(0, (float) $snapshot['discountValue']);

        return [$type, $type === 'percentage' ? min(100, $value) : $value];
    }

    private function authoritativeTotals(
        array $validated,
        array $pricing,
        Currency $baseCurrency,
        string $discountType,
        float $discountValue,
        float $tolerance
    ): array {
        $subtotal = round_currency_amount($pricing['subtotal'], $baseCurrency);
        $promotionDiscount = round_currency_amount($pricing['discount_total'], $baseCurrency);
        $manualDiscount = $discountType === 'percentage'
            ? round_currency_amount($subtotal * min(100, $discountValue) / 100, $baseCurrency)
            : round_currency_amount($discountValue, $baseCurrency);
        $discountAmount = round_currency_amount(
            min($subtotal, $manualDiscount + $promotionDiscount),
            $baseCurrency
        );
        $taxPercent = max(0, min(100, (float) data_get($validated, 'snapshot.taxPercent', 0)));
        $taxAmount = round_currency_amount(
            max(0, $subtotal - $discountAmount) * $taxPercent / 100,
            $baseCurrency
        );
        $serviceFee = round_currency_amount(
            max(0, (float) data_get($validated, 'snapshot.serviceFee', 0)),
            $baseCurrency
        );
        $totalPayable = round_currency_amount(
            max(0, $subtotal - $discountAmount + $taxAmount + $serviceFee),
            $baseCurrency
        );

        $expected = $validated['totals'];
        $checks = [
            'sub_total' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'service_fee' => $serviceFee,
            'total_payable' => $totalPayable,
        ];
        $errors = [];
        foreach ($checks as $key => $actual) {
            if (abs((float) $expected[$key] - $actual) > $tolerance) {
                $errors["totals.{$key}"] = 'Checkout pricing changed. Refresh the cart before accepting payment.';
            }
        }

        $expectedPromotion = data_get($validated, 'snapshot.appliedPromotion.id');
        $expectedPromotion = is_numeric($expectedPromotion) ? (int) $expectedPromotion : null;
        $actualPromotion = data_get($pricing, 'applied_promotion.id');
        $actualPromotion = is_numeric($actualPromotion) ? (int) $actualPromotion : null;
        if ($expectedPromotion !== $actualPromotion) {
            $errors['snapshot.appliedPromotion'] = 'The active promotion changed. Refresh the cart before accepting payment.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'subtotal' => $subtotal,
            'manual_discount' => $manualDiscount,
            'promotion_discount' => $promotionDiscount,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'service_fee' => $serviceFee,
            'total_payable' => $totalPayable,
        ];
    }

    private function buildAuthoritativeItemRows(
        iterable $pricedLines,
        Currency $baseCurrency,
        float $subtotal,
        float $discountAmount,
        float $taxAmount
    ): array {
        $rows = collect($pricedLines)->map(fn (array $line): array => [
            'item_id' => (int) $line['item_id'],
            'item_variant_id' => $line['item_variant_id'] ? (int) $line['item_variant_id'] : null,
            'sku' => (string) ($line['sku'] ?? ''),
            'name' => (string) $line['name'],
            'uom_code' => $line['uom_code'] ?? null,
            'uom_name' => $line['uom_name'] ?? null,
            'selected_options' => $line['selected_options'] ?? [],
            'quantity' => (float) $line['quantity'],
            'currency_code' => strtoupper((string) $baseCurrency->code),
            'unit_price' => (float) $line['unit_price'],
            'exchange_rate' => 1.0,
            'unit_price_base' => (float) $line['unit_price'],
            'line_subtotal_base' => (float) $line['line_subtotal'],
        ])->values();

        $remainingDiscount = $discountAmount;
        $remainingTax = $taxAmount;

        return $rows->map(function (array $row, int $index) use (
            $rows,
            $subtotal,
            $discountAmount,
            $taxAmount,
            &$remainingDiscount,
            &$remainingTax
        ): array {
            $isLast = $index === $rows->count() - 1;
            $ratio = $subtotal > 0 ? $row['line_subtotal_base'] / $subtotal : 0;
            $lineDiscount = $isLast ? $remainingDiscount : round($discountAmount * $ratio, 8);
            $lineTax = $isLast ? $remainingTax : round($taxAmount * $ratio, 8);
            $remainingDiscount = round($remainingDiscount - $lineDiscount, 8);
            $remainingTax = round($remainingTax - $lineTax, 8);

            return array_merge($row, [
                'discount_base' => max(0, $lineDiscount),
                'tax_base' => max(0, $lineTax),
                'line_total_base' => max(0, round($row['line_subtotal_base'] - $lineDiscount + $lineTax, 8)),
            ]);
        })->all();
    }

    private function tenantConnectionName(): string
    {
        return tenant()?->database_connection_name ?: 'tenant';
    }
}
