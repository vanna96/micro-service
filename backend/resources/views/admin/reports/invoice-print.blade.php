<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ($format ?? 'a4') === 'thermal' ? 'Receipt' : 'Invoice' }}-{{ $sale->invoice_number ?: ('#' . $sale->id) }}</title>
    <link rel="shortcut icon" href="{{ global_asset('branding/v-pos-mark.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ global_asset('minible/assets/css/bootstrap.min.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ global_asset('minible/assets/css/icons.min.css') }}" rel="stylesheet" type="text/css" />

    @php
        $currencySymbol = $currency['symbol'] ?? '$';
        $currencyDecimals = (int) ($currency['decimals'] ?? 2);
        $currencyCode = strtoupper((string) ($currency['code'] ?? 'USD'));
        $currencyCatalog = collect($currencies ?? [])->mapWithKeys(function ($entry, $key) {
            $code = strtoupper((string) ($entry['code'] ?? $key));

            return [$code => $entry];
        })->all();
        $resolveLineCurrency = function (?string $code, array $embedded = []) use ($currencyCatalog, $currencyCode, $currencySymbol, $currencyDecimals) {
            $resolvedCode = strtoupper(trim((string) ($code ?: ($embedded['code'] ?? $currencyCode))));
            $configured = $currencyCatalog[$resolvedCode] ?? [];

            return [
                'code' => $resolvedCode,
                'symbol' => (string) ($embedded['symbol'] ?? $configured['symbol'] ?? ($resolvedCode === $currencyCode ? $currencySymbol : $resolvedCode)),
                'decimals' => max(0, (int) ($embedded['decimalPlaces'] ?? $embedded['decimals'] ?? $configured['decimals'] ?? ($resolvedCode === $currencyCode ? $currencyDecimals : 2))),
            ];
        };
        $lineMoney = function ($val, array $lineCurrency) {
            $symbol = (string) ($lineCurrency['symbol'] ?? $lineCurrency['code'] ?? '');
            $separator = \Illuminate\Support\Str::length($symbol) > 1 ? ' ' : '';

            return $symbol . $separator . number_format((float) ($val ?? 0), (int) ($lineCurrency['decimals'] ?? 2), '.', '');
        };
        $money = fn ($val) => $lineMoney($val, [
            'code' => $currencyCode,
            'symbol' => $currencySymbol,
            'decimals' => $currencyDecimals,
        ]);
        $quantity = fn ($val) => rtrim(rtrim(number_format((float) ($val ?? 0), 8, '.', ''), '0'), '.');
        $currentFormat = request('format', $format ?? 'a4');
        $customerName = $sale->customer_name ?: ($sale->snapshot['customer']['name'] ?? __('Walk-in customer'));
        $customerCode = $sale->customer_code ?: ($sale->snapshot['customer']['code'] ?? '');
        $customerPhone = $sale->snapshot['customer']['phone'] ?? '';
        $orderType = ucfirst(str_replace('_', ' ', $sale->order_type ?: ($sale->snapshot['orderType'] ?? 'Takeaway')));
        $completedAt = $sale->completed_at ? \Carbon\Carbon::parse($sale->completed_at) : ($sale->created_at ? \Carbon\Carbon::parse($sale->created_at) : now());
        $pmString = $sale->payment_method ? ucfirst(str_replace('_', ' ', $sale->payment_method)) : ($sale->payments->pluck('method')->filter()->unique()->map(fn ($m) => ucfirst(str_replace('_', ' ', $m)))->join(', ') ?: ($sale->snapshot['paymentMethod'] ?? __('Not yet')));
        $items = $sale->items;
        $cartItems = $sale->snapshot['cart'] ?? [];
        $cartSubtotal = collect($cartItems)->sum(fn ($cIt) => (float) ($cIt['unitPrice'] ?? 0) * (float) ($cIt['quantity'] ?? 1));
        $cartItemCount = (int) collect($cartItems)->sum(fn ($cIt) => (float) ($cIt['quantity'] ?? 1));

        $subtotalBase = (float) $sale->subtotal_base;
        $discountBase = (float) $sale->discount_base;
        $promoDiscountBase = (float) $sale->promotion_discount_base;
        $taxBase = (float) $sale->tax_base;
        $serviceFeeBase = (float) $sale->service_fee_base;
        $totalBase = (float) $sale->total_base;
        $itemCountBase = (int) $sale->item_count;

        $appliedPromotion = $promotion ?? ($sale->promotion_name ? [
            'id' => $sale->promotion_id,
            'name' => $sale->promotion_name,
            'savings' => $promoDiscountBase,
        ] : ($sale->snapshot['appliedPromotion'] ?? ($sale->snapshot['applied_promotion'] ?? null)));
        $promoName = $appliedPromotion['name'] ?? null;
        $promoSavings = (float) ($promotion_discount ?? ($promoDiscountBase > 0 ? $promoDiscountBase : ($sale->snapshot['promotionDiscountAmount'] ?? ($appliedPromotion['savings'] ?? 0))));

        $totalDiscount = $discountBase > 0 ? $discountBase : (float) ($sale->snapshot['discountAmount'] ?? $promoSavings);
        $resolvedPromotionDiscount = $appliedPromotion ? min($totalDiscount > 0 ? $totalDiscount : $promoSavings, max(0, $promoSavings)) : 0;
        $manualDiscount = $manual_discount ?? max(0, $totalDiscount - $resolvedPromotionDiscount);
        $discountType = $sale->discount_type ?: ($sale->snapshot['discountType'] ?? 'percentage');
        $discountValue = (float) ($sale->discount_value ?: ($sale->snapshot['discountValue'] ?? 0));

        $subtotal = $subtotalBase > 0 ? $subtotalBase : (float) ($sale->snapshot['subTotal'] ?? ($cartSubtotal > 0 ? $cartSubtotal : $totalBase));
        $tax = $taxBase > 0 ? $taxBase : (float) ($sale->snapshot['taxAmount'] ?? 0);
        $taxPercent = (float) ($sale->tax_percent ?: ($sale->snapshot['taxPercent'] ?? 0));
        $serviceFee = $serviceFeeBase > 0 ? $serviceFeeBase : (float) ($sale->snapshot['serviceFee'] ?? 0);
        $grandTotal = $totalBase > 0 ? $totalBase : (float) ($sale->snapshot['totalPayable'] ?? max(0, $subtotal - $totalDiscount + $tax + $serviceFee));
        $totalItems = $itemCountBase > 0 ? $itemCountBase : ($items->sum('quantity') ?: $cartItemCount);
        $exchangeRateRemark = $items
            ->filter(fn ($item) => filled($item->currency_code)
                && strtoupper((string) $item->currency_code) !== $currencyCode
                && (float) $item->exchange_rate > 0)
            ->mapWithKeys(fn ($item) => [strtoupper((string) $item->currency_code) => (float) $item->exchange_rate])
            ->map(fn ($rate, $code) => '1 ' . $currencyCode . ' = '
                . rtrim(rtrim(number_format($rate, 8, '.', ','), '0'), '.') . ' ' . $code)
            ->values()
            ->join(' · ');
    @endphp

    <style>
        :root {
            --pos-primary: #4f46e5;
            --pos-primary-light: #eef2ff;
            --pos-border: #e2e8f0;
            --pos-text: #0f172a;
            --pos-text-muted: #64748b;
            --pos-text-secondary: #334155;
            --pos-danger: #dc2626;
        }
        body {
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, sans-serif;
            color: var(--pos-text);
            margin: 0;
            padding: 24px;
        }
        .print-toolbar {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #ffffff;
            padding: 12px 18px;
            border-radius: 12px;
            border: 1px solid var(--pos-border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .format-switch {
            display: inline-flex;
            background: #f1f5f9;
            padding: 3px;
            border-radius: 8px;
            gap: 4px;
        }
        .format-switch a {
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .15s ease;
        }
        .format-switch a.active {
            background: #ffffff;
            color: var(--pos-primary);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        /* A4 Sheet Styles */
        .invoice-preview-sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--pos-border);
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .invoice-preview-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(300px, 0.9fr);
            grid-template-areas: "company title";
            align-items: flex-start;
            column-gap: 48px;
            padding: 24px;
            border-top: 5px solid var(--pos-primary);
            border-bottom: 1px solid var(--pos-border);
        }
        .invoice-preview-company {
            grid-area: company;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            min-width: 0;
        }
        .invoice-preview-company-mark {
            display: grid;
            place-items: center;
            flex: 0 0 44px;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--pos-primary-light);
            color: var(--pos-primary);
            font-size: 22px;
        }
        .invoice-preview-company h4 {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: 700;
            white-space: nowrap;
        }
        .invoice-preview-address {
            max-width: 360px;
            color: var(--pos-text-muted);
            font-size: 12px;
            white-space: pre-line;
            line-height: 1.4;
        }
        .invoice-preview-title {
            grid-area: title;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            text-align: right;
        }
        .invoice-preview-title > div:first-child {
            color: var(--pos-text);
            font-size: 26px;
            font-weight: 800;
            letter-spacing: 0.08em;
            line-height: 1;
        }
        .invoice-preview-header-meta {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 6px;
            width: auto;
            margin-top: 14px;
            margin-left: auto;
        }
        .invoice-preview-header-meta-item {
            display: flex;
            align-items: baseline;
            gap: 8px;
            min-width: 0;
            color: var(--pos-text);
            font-size: 11px;
        }
        .invoice-preview-header-meta-item > span {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 6px;
            flex: 0 0 68px;
            width: 68px;
            color: var(--pos-text-muted);
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .invoice-preview-header-meta-item > span > span:last-child {
            margin-left: auto;
            text-align: right;
        }
        .invoice-preview-header-meta-item strong {
            color: var(--pos-text);
            font-weight: 600;
            text-align: left;
            line-height: 1.35;
        }
        .invoice-preview-number-row strong {
            color: var(--pos-primary);
            font-family: monospace;
            font-size: 13px;
            font-weight: 700;
        }
        .invoice-preview-time-row strong {
            color: var(--pos-text-muted);
            font-weight: 500;
        }
        .invoice-preview-table-wrap {
            padding: 16px 24px 4px;
        }
        .invoice-preview-table {
            width: 100%;
            margin-bottom: 0;
            font-size: 12px;
        }
        .invoice-preview-table thead th {
            padding: 10px 9px;
            border-top: 1px solid #dbe3f3;
            border-bottom: 2px solid #c7d2fe;
            background: #f5f7ff;
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .invoice-preview-table thead th:first-child {
            border-top-left-radius: 7px;
        }
        .invoice-preview-table thead th:last-child {
            border-top-right-radius: 7px;
        }
        .invoice-preview-table tbody td {
            padding: 11px 9px;
            border-bottom: 1px solid var(--pos-border);
            font-size: 13px;
        }
        .invoice-preview-summary {
            width: min(330px, calc(100% - 48px));
            margin: 12px 24px 20px auto;
        }
        .invoice-preview-summary > div {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 5px 0;
            font-size: 12px;
        }
        .invoice-preview-discount,
        .invoice-preview-discount span,
        .invoice-preview-discount strong,
        .thermal-discount,
        .thermal-discount span,
        .thermal-discount strong {
            color: var(--pos-danger) !important;
        }
        .invoice-preview-total {
            margin-top: 6px;
            padding-top: 12px !important;
            border-top: 2px solid var(--pos-text);
            color: var(--pos-primary);
            font-size: 15px !important;
        }
        .invoice-preview-footer {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 58px;
            padding: 14px 24px;
            border-top: 1px dashed var(--pos-border);
            background: #f8fafc;
            color: var(--pos-text-muted);
            font-size: 12px;
            text-align: center;
            white-space: pre-line;
        }
        .invoice-preview-footer-message,
        .invoice-preview-rate-remark {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .invoice-preview-rate-remark {
            color: var(--pos-text-secondary);
            font-size: 10px;
            font-weight: 600;
        }
        .invoice-preview-footer i {
            color: var(--pos-primary);
        }

        /* Thermal 80mm Receipt Styles */
        .thermal-receipt {
            width: min(100%, 302px);
            margin: 0 auto;
            padding: 20px 14px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: #ffffff;
            color: #111827;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .thermal-receipt,
        .thermal-receipt * {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Khmer Sangam MN", monospace !important;
        }
        .thermal-receipt-header {
            text-align: center;
            color: #111827;
            font-size: 9px;
            line-height: 1.4;
        }
        .thermal-receipt-header h3 {
            margin: 0 0 3px;
            font-size: 16px;
            font-weight: 800;
        }
        .thermal-receipt-header h4 {
            margin: 12px 0 7px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.12em;
        }
        .thermal-receipt-meta {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            margin-top: 7px;
            text-align: center;
        }
        .thermal-receipt-meta > div {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 5px;
            width: 100%;
            text-align: center;
        }
        .thermal-receipt-meta span {
            flex: 0 0 auto;
            color: #4b5563;
        }
        .thermal-receipt-meta strong {
            min-width: 0;
            font-weight: 700;
            text-align: center;
            overflow-wrap: anywhere;
        }
        .thermal-receipt-rule {
            margin: 10px 0;
            border-top: 1px dashed #6b7280;
        }
        .thermal-receipt-table {
            width: 100%;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 8.5px;
            line-height: 1.35;
        }
        .thermal-receipt-table th, .thermal-receipt-table td {
            padding: 6px 2px;
            vertical-align: top;
        }
        .thermal-receipt-table th:first-child, .thermal-receipt-table td:first-child { width: 60%; padding-left: 0; }
        .thermal-receipt-table th:nth-child(2), .thermal-receipt-table td:nth-child(2) { width: 12%; text-align: center; }
        .thermal-receipt-table th:last-child, .thermal-receipt-table td:last-child { width: 28%; text-align: right; padding-right: 0; }
        .thermal-receipt-table thead th {
            border-bottom: 1px dashed #6b7280;
            color: #111827;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .thermal-receipt-table tbody td {
            border-bottom: 1px dashed #d1d5db;
        }
        .thermal-receipt-table td strong,
        .thermal-receipt-table td small {
            display: block;
        }
        .thermal-receipt-table td strong {
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .thermal-receipt-table td small {
            margin-top: 2px;
            color: #6b7280;
            font-size: 7.5px;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }
        .thermal-receipt-summary {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #6b7280;
            font-size: 9px;
        }
        .thermal-receipt-summary > div {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 8px;
        }
        .thermal-receipt-summary strong {
            text-align: right;
            white-space: nowrap;
        }
        .thermal-receipt-total {
            margin-top: 4px;
            padding-top: 7px;
            border-top: 2px solid #111827;
            font-size: 12px;
            font-weight: 800;
        }
        .thermal-receipt-summary > small {
            display: block;
            margin-top: 2px;
            color: #6b7280;
            font-size: 7.5px;
            text-align: center;
        }
        .thermal-receipt-footer {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px dashed #6b7280;
            font-size: 8.5px;
            text-align: center;
            white-space: pre-line;
            line-height: 1.45;
        }
        .thermal-receipt-footer div {
            margin-bottom: 7px;
            color: #4b5563;
            font-size: 7.5px;
        }
        .thermal-receipt-footer strong {
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .invoice-preview-header {
                grid-template-columns: 1fr;
                grid-template-areas:
                    "company"
                    "title";
                gap: 20px;
            }
            .invoice-preview-title {
                width: 100%;
                text-align: left !important;
            }
            .invoice-preview-header-meta {
                width: 100%;
                margin-left: 0;
            }
        }

        /* Print Media Styles */
        @media print {
            @page {
                @if ($currentFormat === 'thermal')
                    size: 80mm auto;
                    margin: 4mm;
                @else
                    size: A4 portrait;
                    margin: 12mm;
                @endif
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .invoice-preview-sheet {
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }
            .thermal-receipt {
                border: none !important;
                box-shadow: none !important;
                width: 100% !important;
                padding: 4px 0 !important;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <div class="format-switch">
            <a href="{{ route('admin.reports.sales.print', ['sale' => $sale->id, 'format' => 'a4']) }}" class="{{ $currentFormat === 'a4' ? 'active' : '' }}">
                <i class="uil uil-file-alt"></i> A4 / PDF
            </a>
            <a href="{{ route('admin.reports.sales.print', ['sale' => $sale->id, 'format' => 'thermal']) }}" class="{{ $currentFormat === 'thermal' ? 'active' : '' }}">
                <i class="uil uil-receipt"></i> Thermal 80mm
            </a>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm px-3" onclick="window.print()">
                <i class="uil uil-print me-1"></i> {{ __('Print') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3" onclick="window.close()">
                {{ __('Close') }}
            </button>
        </div>
    </div>

    @if ($currentFormat === 'a4')
        <!-- A4 Invoice Sheet -->
        <article class="invoice-preview-sheet">
            <header class="invoice-preview-header">
                <div class="invoice-preview-company">
                    <div class="invoice-preview-company-mark">
                        <i class="uil uil-store"></i>
                    </div>
                    <div>
                        <h4>{{ $company['name'] ?: 'V-POS Store' }}</h4>
                        @if (!empty($company['address']))
                            <div class="invoice-preview-address">{{ $company['address'] }}</div>
                        @endif
                        @if (!empty($company['phone']))
                            <div class="text-muted font-size-12 mt-1">
                                {{ $company['phone'] }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="invoice-preview-title text-end">
                    <div class="text-uppercase fw-bolder">INVOICE</div>
                    <div class="invoice-preview-header-meta">
                        <div class="invoice-preview-header-meta-item invoice-preview-number-row">
                            <span><span>#</span><span>:</span></span>
                            <strong>{{ $sale->invoice_number ?: ('#' . $sale->id) }}</strong>
                        </div>
                        <div class="invoice-preview-header-meta-item invoice-preview-time-row">
                            <span><span>Time</span><span>:</span></span>
                            <strong>{{ $completedAt->format('M d, Y · h:i A') }}</strong>
                        </div>
                        <div class="invoice-preview-header-meta-item">
                            <span><span>BILL TO</span><span>:</span></span>
                            <strong>{{ $customerName }}</strong>
                        </div>
                        <div class="invoice-preview-header-meta-item">
                            <span><span>ORDER</span><span>:</span></span>
                            <strong>{{ $orderType }}</strong>
                        </div>
                        <div class="invoice-preview-header-meta-item">
                            <span><span>PAYMENT</span><span>:</span></span>
                            <strong>{{ $pmString }}</strong>
                        </div>
                    </div>
                </div>
            </header>

            <div class="invoice-preview-table-wrap">
                <table class="invoice-preview-table">
                    <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('UOM / Option') }}</th>
                            <th class="text-center">{{ __('Qty') }}</th>
                            <th class="text-end">{{ __('Price') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            @php
                                $optString = $item->uom_name ?: ($item->uom_code ?: (is_array($item->selected_options) ? collect($item->selected_options)->pluck('label')->filter()->join(', ') : 'Standard'));
                                $itemCurrency = $resolveLineCurrency($item->currency_code);
                            @endphp
                            <tr>
                                <td>
                                    <strong class="d-block text-dark">{{ $item->name }}</strong>
                                    @if ($item->sku)
                                        <small class="text-muted font-monospace">{{ $item->sku }}</small>
                                    @endif
                                </td>
                                <td class="text-muted font-size-12">
                                    {{ $optString ?: 'Standard' }}
                                </td>
                                <td class="text-center font-monospace">
                                    {{ $quantity($item->quantity) }}
                                </td>
                                <td class="text-end font-size-13 text-muted">
                                    {{ $lineMoney($item->unit_price, $itemCurrency) }}
                                </td>
                                <td class="text-end font-size-13 fw-bold text-dark">
                                    {{ $lineMoney((float) $item->unit_price * (float) $item->quantity, $itemCurrency) }}
                                </td>
                            </tr>
                        @empty
                            @if (!empty($sale->snapshot['cart']))
                                @foreach ($sale->snapshot['cart'] as $cItem)
                                    @php
                                        $embeddedCurrency = is_array($cItem['product']['currency'] ?? null) ? $cItem['product']['currency'] : [];
                                        $itemCurrency = $resolveLineCurrency($embeddedCurrency['code'] ?? null, $embeddedCurrency);
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong class="d-block text-dark">{{ $cItem['product']['name'] ?? 'Item' }}</strong>
                                            @if (!empty($cItem['product']['sku']))
                                                <small class="text-muted font-monospace">{{ $cItem['product']['sku'] }}</small>
                                            @endif
                                        </td>
                                        <td class="text-muted font-size-12">
                                            {{ $cItem['selectedUOM']['name'] ?? (collect($cItem['selectedVariants'] ?? [])->pluck('label')->filter()->join(', ') ?: 'Standard') }}
                                        </td>
                                        <td class="text-center font-monospace">
                                            {{ $cItem['quantity'] ?? 1 }}
                                        </td>
                                        <td class="text-end font-size-13 text-muted">
                                            {{ $lineMoney($cItem['unitPrice'] ?? 0, $itemCurrency) }}
                                        </td>
                                        <td class="text-end font-size-13 fw-bold text-dark">
                                            {{ $lineMoney(($cItem['unitPrice'] ?? 0) * ($cItem['quantity'] ?? 1), $itemCurrency) }}
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">{{ __('No line items found.') }}</td>
                                </tr>
                            @endif
                        @endforelse
                    </tbody>
                </table>
            </div>

            <section class="invoice-preview-summary">
                <div>
                    <span>{{ __('Subtotal') }}</span>
                    <strong>{{ $money($subtotal) }}</strong>
                </div>
                @if ($manualDiscount > 0 || ! $appliedPromotion)
                    <div class="{{ $manualDiscount > 0 ? 'invoice-preview-discount' : '' }}">
                        <span>
                            {{ __('Discount') }}
                            @if ($discountType === 'percentage' && $discountValue > 0)
                                ({{ number_format($discountValue, 0) }}%)
                            @elseif ($discountType === 'fixed')
                                ({{ __('Fixed') }})
                            @endif
                        </span>
                        <strong>{{ $manualDiscount > 0 ? '-' : '' }}{{ $money($manualDiscount) }}</strong>
                    </div>
                @endif
                @if ($appliedPromotion && $promoName)
                    <div class="invoice-preview-discount">
                        <span>Promotion · {{ $promoName }}</span>
                        <strong>-{{ $money($resolvedPromotionDiscount) }}</strong>
                    </div>
                @endif
                <div>
                    <span>{{ __('GST Tax') }} ({{ number_format($taxPercent, 0) }}%)</span>
                    <strong>{{ $money($tax) }}</strong>
                </div>
                <div>
                    <span>{{ __('Service Fee') }}</span>
                    <strong>{{ $money($serviceFee) }}</strong>
                </div>
                <div class="invoice-preview-total">
                    <span class="d-flex flex-column align-items-start">
                        <span>{{ __('Grand Total') }} <small>({{ $currency['code'] ?? 'USD' }})</small></span>
                        <small style="color: var(--pos-text-muted); font-size: 11px;">{{ $totalItems }} {{ $totalItems == 1 ? __('item') : __('items') }}</small>
                    </span>
                    <strong>{{ $money($grandTotal) }}</strong>
                </div>
            </section>

            <footer class="invoice-preview-footer">
                @if ($exchangeRateRemark)
                    <div class="invoice-preview-rate-remark">
                        <i class="uil uil-info-circle"></i>
                        <span>{{ __('Exchange rate used:') }} {{ $exchangeRateRemark }}</span>
                    </div>
                @endif
                <div class="invoice-preview-footer-message">
                    <i class="uil uil-heart"></i>
                    <span>{{ $company['receipt_footer'] ?: __('Thank you for your business.') }}</span>
                </div>
            </footer>
        </article>
    @else
        <!-- Thermal 80mm Receipt -->
        <article class="thermal-receipt">
            <header class="thermal-receipt-header">
                <h3>{{ $company['name'] ?: 'V-POS Store' }}</h3>
                @if (!empty($company['address']))
                    <div>{{ $company['address'] }}</div>
                @endif
                @if (!empty($company['phone']))
                    <div>Tel: {{ $company['phone'] }}</div>
                @endif
                <h4>INVOICE</h4>
                <div class="thermal-receipt-meta">
                    <div><span>Invoice #:</span><strong>{{ $sale->invoice_number ?: ('#' . $sale->id) }}</strong></div>
                    <div><span>Date:</span><strong>{{ $completedAt->format('Y-m-d') }}</strong></div>
                    <div><span>Time:</span><strong>{{ $completedAt->format('H:i:s') }}</strong></div>
                    <div><span>Customer:</span><strong>{{ $customerName }}</strong></div>
                    @if ($customerCode)
                        <div><span>Customer ID:</span><strong>{{ $customerCode }}</strong></div>
                    @endif
                    @if ($customerPhone)
                        <div><span>Phone:</span><strong>{{ $customerPhone }}</strong></div>
                    @endif
                    <div><span>Order:</span><strong>{{ $orderType }}</strong></div>
                    <div><span>Payment:</span><strong>{{ $pmString }}</strong></div>
                </div>
            </header>

            <div class="thermal-receipt-rule"></div>

            <table class="thermal-receipt-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php
                            $itemCurrency = $resolveLineCurrency($item->currency_code);
                            $itemDescription = collect([
                                $item->sku,
                                $item->uom_name ?: ($item->uom_code ?: (is_array($item->selected_options) ? collect($item->selected_options)->pluck('label')->filter()->join(', ') : 'Standard')),
                            ])->filter()->join(' · ');
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $item->name }}</strong>
                                <small>{{ $itemDescription }}</small>
                            </td>
                            <td>{{ $quantity($item->quantity) }}</td>
                            <td>{{ $lineMoney((float) $item->unit_price * (float) $item->quantity, $itemCurrency) }}</td>
                        </tr>
                    @empty
                        @if (!empty($sale->snapshot['cart']))
                            @foreach ($sale->snapshot['cart'] as $cItem)
                                @php
                                    $fallbackOption = $cItem['selectedUOM']['name']
                                        ?? (collect($cItem['selectedVariants'] ?? [])->pluck('label')->filter()->join(', ') ?: 'Standard');
                                    $itemDescription = collect([$cItem['product']['sku'] ?? null, $fallbackOption])->filter()->join(' · ');
                                    $embeddedCurrency = is_array($cItem['product']['currency'] ?? null) ? $cItem['product']['currency'] : [];
                                    $itemCurrency = $resolveLineCurrency($embeddedCurrency['code'] ?? null, $embeddedCurrency);
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $cItem['product']['name'] ?? 'Item' }}</strong>
                                        <small>{{ $itemDescription }}</small>
                                    </td>
                                    <td>{{ $cItem['quantity'] ?? 1 }}</td>
                                    <td>{{ $lineMoney(($cItem['unitPrice'] ?? 0) * ($cItem['quantity'] ?? 1), $itemCurrency) }}</td>
                                </tr>
                            @endforeach
                        @endif
                    @endforelse
                </tbody>
            </table>

            <section class="thermal-receipt-summary">
                <div><span>Subtotal</span><strong>{{ $money($subtotal) }}</strong></div>
                @if ($manualDiscount > 0 || ! $appliedPromotion)
                    <div class="{{ $manualDiscount > 0 ? 'thermal-discount' : '' }}">
                        <span>Discount{{ $discountType === 'percentage' && $discountValue > 0 ? ' (' . number_format($discountValue, 0) . '%)' : '' }}</span>
                        <strong>{{ $manualDiscount > 0 ? '-' : '' }}{{ $money($manualDiscount) }}</strong>
                    </div>
                @endif
                @if ($appliedPromotion && $promoName)
                    <div class="thermal-discount">
                        <span>Promotion · {{ $promoName }}</span>
                        <strong>-{{ $money($resolvedPromotionDiscount) }}</strong>
                    </div>
                @endif
                <div><span>GST Tax ({{ number_format($taxPercent, 0) }}%)</span><strong>{{ $money($tax) }}</strong></div>
                <div><span>Service Fee</span><strong>{{ $money($serviceFee) }}</strong></div>
                <div class="thermal-receipt-total">
                    <span>TOTAL</span>
                    <strong>{{ $money($grandTotal) }}</strong>
                </div>
                <small>{{ $totalItems }} {{ $totalItems == 1 ? __('item') : __('items') }} {{ __('included') }}</small>
            </section>

            <footer class="thermal-receipt-footer">
                @if ($exchangeRateRemark)
                    <div>{{ __('Exchange rate used:') }} {{ $exchangeRateRemark }}</div>
                @endif
                <strong>{{ $company['receipt_footer'] ?: __('Thank you for your business.') }}</strong>
            </footer>
        </article>
    @endif

    @if (!empty($autoPrint))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 350);
            });
        </script>
    @endif
</body>
</html>
