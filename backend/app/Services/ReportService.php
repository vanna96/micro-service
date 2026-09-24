<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Item;
use App\Models\PosSale;
use App\Models\PosSaleItem;
use App\Models\PosSalePayment;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    public function resolveDateRange(?string $preset = 'last_30_days', ?string $customStart = null, ?string $customEnd = null): array
    {
        $today = Carbon::today();

        switch ($preset) {
            case 'today':
                $start = $today->copy()->startOfDay();
                $end = $today->copy()->endOfDay();
                break;
            case 'yesterday':
                $start = $today->copy()->subDay()->startOfDay();
                $end = $today->copy()->subDay()->endOfDay();
                break;
            case 'last_7_days':
                $start = $today->copy()->subDays(6)->startOfDay();
                $end = $today->copy()->endOfDay();
                break;
            case 'this_month':
                $start = $today->copy()->startOfMonth()->startOfDay();
                $end = $today->copy()->endOfDay();
                break;
            case 'last_month':
                $start = $today->copy()->subMonth()->startOfMonth()->startOfDay();
                $end = $today->copy()->subMonth()->endOfMonth()->endOfDay();
                break;
            case 'this_year':
                $start = $today->copy()->startOfYear()->startOfDay();
                $end = $today->copy()->endOfDay();
                break;
            case 'custom':
                $start = $customStart ? Carbon::parse($customStart)->startOfDay() : $today->copy()->subDays(29)->startOfDay();
                $end = $customEnd ? Carbon::parse($customEnd)->endOfDay() : $today->copy()->endOfDay();
                break;
            case 'last_30_days':
            default:
                $preset = 'last_30_days';
                $start = $today->copy()->subDays(29)->startOfDay();
                $end = $today->copy()->endOfDay();
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'preset' => $preset,
            'formatted_start' => $start->toDateString(),
            'formatted_end' => $end->toDateString(),
            'label' => $start->format('M d, Y') . ' - ' . $end->format('M d, Y'),
        ];
    }

    public function resolveTenant(?Tenant $tenant = null): ?Tenant
    {
        if (! $tenant && function_exists('tenant')) {
            try {
                $tenant = tenant();
            } catch (\Throwable $e) {
                $tenant = null;
            }
        }

        if (! $tenant && function_exists('admin_current_tenant') && function_exists('app') && app()->bound('auth')) {
            try {
                $tenant = admin_current_tenant();
            } catch (\Throwable $e) {
                $tenant = null;
            }
        }

        if ($tenant && function_exists('tenancy') && function_exists('tenant')) {
            try {
                if (! tenant() || (string) tenant('id') !== (string) $tenant->id) {
                    tenancy()->initialize($tenant);
                }
            } catch (\Throwable $e) {
                // Ignore if in unit test environment
            }
        }

        return $tenant;
    }

    public function getCurrencyInfo(?Tenant $tenant = null): array
    {
        $code = 'USD';
        $symbol = '$';
        $decimals = 2;

        $tenant = $this->resolveTenant($tenant);
        if ($tenant) {
            $code = strtoupper((string) data_get($tenant->general_settings, 'currency', 'USD')) ?: 'USD';
            try {
                $currency = Currency::query()->whereRaw('UPPER(code) = ?', [$code])->first();
                if ($currency) {
                    $symbol = (string) ($currency->symbol ?: $currency->code);
                    $decimals = max(0, (int) ($currency->decimal_places ?? 2));
                }
            } catch (\Throwable $e) {
                // Fallback to defaults
            }
        }

        return [
            'code' => $code,
            'symbol' => $symbol,
            'decimals' => $decimals,
        ];
    }

    public function getCurrencyCatalog(): array
    {
        try {
            return Currency::query()
                ->get(['code', 'symbol', 'decimal_places'])
                ->mapWithKeys(function (Currency $currency) {
                    $code = strtoupper(trim((string) $currency->code));

                    return [$code => [
                        'code' => $code,
                        'symbol' => (string) ($currency->symbol ?: $code),
                        'decimals' => max(0, (int) ($currency->decimal_places ?? 2)),
                    ]];
                })
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getCompanyInfo(?Tenant $tenant = null): array
    {
        $tenant = $this->resolveTenant($tenant);
        $settings = is_array($tenant?->general_settings) ? $tenant->general_settings : [];

        $storeName = $settings['store_name'] ?? (function_exists('admin_tenant_display_name') ? admin_tenant_display_name($tenant) : null);

        return [
            'name' => $storeName ?: 'V-POS Store',
            'address' => $settings['address'] ?? '',
            'phone' => $settings['contact_phone'] ?? '',
            'email' => $settings['contact_email'] ?? '',
            'receipt_footer' => $settings['receipt_footer'] ?? 'Thank you for your business.',
        ];
    }

    public function getSaleDetail(int|string $saleId, ?Tenant $tenant = null): ?array
    {
        $tenant = $this->resolveTenant($tenant);

        $sale = PosSale::query()
            ->with(['items', 'payments', 'customer'])
            ->find($saleId);

        if (! $sale) {
            return null;
        }

        $currency = $this->getCurrencyInfo($tenant);
        $company = $this->getCompanyInfo($tenant);

        $snapshot = is_array($sale->snapshot) ? $sale->snapshot : [];
        $appliedPromo = $sale->promotion_name ? [
            'id' => $sale->promotion_id,
            'name' => $sale->promotion_name,
            'savings' => (float) $sale->promotion_discount_base,
        ] : (data_get($snapshot, 'appliedPromotion') ?: data_get($snapshot, 'applied_promotion'));

        $promoDiscount = (float) ($sale->promotion_discount_base > 0
            ? $sale->promotion_discount_base
            : (data_get($snapshot, 'promotionDiscountAmount') ?: data_get($appliedPromo, 'savings', 0)));

        $totalDiscount = (float) ($sale->discount_base > 0
            ? $sale->discount_base
            : (data_get($snapshot, 'discountAmount') ?: $promoDiscount));
        $resolvedPromoDiscount = ! empty($appliedPromo) ? min($totalDiscount > 0 ? $totalDiscount : $promoDiscount, max(0, $promoDiscount)) : 0;
        $manualDiscount = max(0, $totalDiscount - $resolvedPromoDiscount);

        return [
            'sale' => $sale,
            'currency' => $currency,
            'currencies' => $this->getCurrencyCatalog(),
            'company' => $company,
            'promotion' => $appliedPromo ? [
                'id' => data_get($appliedPromo, 'id'),
                'name' => data_get($appliedPromo, 'name'),
                'savings' => $resolvedPromoDiscount,
            ] : null,
            'manual_discount' => $manualDiscount,
            'promotion_discount' => $resolvedPromoDiscount,
        ];
    }

    public function getSalesReport(array $filters, ?Tenant $tenant = null): array
    {
        $tenant = $this->resolveTenant($tenant);
        $range = $this->resolveDateRange(
            $filters['date_range'] ?? 'last_30_days',
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );
        $currency = $this->getCurrencyInfo($tenant);

        $query = PosSale::query()
            ->whereBetween('completed_at', [$range['start'], $range['end']]);

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $paymentMethod = trim((string) ($filters['payment_method'] ?? ''));
        if ($paymentMethod !== '' && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        $orderType = trim((string) ($filters['order_type'] ?? ''));
        if ($orderType !== '' && $orderType !== 'all') {
            $query->where('order_type', $orderType);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        // Summary calculations via single SQL aggregation
        $completedQuery = (clone $query)->where('status', 'completed');
        $summary = (clone $completedQuery)
            ->selectRaw('COUNT(*) as total_orders, COALESCE(SUM(total_base), 0) as gross_revenue, COALESCE(SUM(item_count), 0) as total_items, COALESCE(SUM(discount_base), 0) as total_discounts, COALESCE(SUM(tax_base), 0) as total_taxes')
            ->first();

        $grossRevenue = (float) ($summary->gross_revenue ?? 0);
        $completedOrders = (int) ($summary->total_orders ?? 0);
        $totalItems = (int) ($summary->total_items ?? 0);
        $totalDiscounts = (float) ($summary->total_discounts ?? 0);
        $totalTaxes = (float) ($summary->total_taxes ?? 0);
        $avgOrderValue = $completedOrders > 0 ? $grossRevenue / $completedOrders : 0.0;

        // Daily Trend Chart Data
        $chartData = $this->buildSalesChartData($completedQuery, $range['start'], $range['end']);

        // Payment Methods Distribution
        $paymentsDistribution = (clone $completedQuery)
            ->selectRaw('payment_method, COUNT(*) as orders_count, SUM(total_base) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($row) use ($grossRevenue) {
                $amount = (float) $row->total_amount;
                $row->total_amount = $amount;
                $row->percentage = $grossRevenue > 0 ? round(($amount / $grossRevenue) * 100, 1) : 0;
                return $row;
            });

        // Transactions list paginated
        $transactions = (clone $query)
            ->with(['items', 'payments'])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Options for filter selects
        $availablePaymentMethods = PosSale::query()
            ->whereNotNull('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method');

        $availableOrderTypes = PosSale::query()
            ->whereNotNull('order_type')
            ->distinct()
            ->orderBy('order_type')
            ->pluck('order_type');

        return [
            'range' => $range,
            'currency' => $currency,
            'currencies' => $this->getCurrencyCatalog(),
            'company' => $this->getCompanyInfo($tenant),
            'gross_revenue' => $grossRevenue,
            'completed_orders' => $completedOrders,
            'total_items' => $totalItems,
            'total_discounts' => $totalDiscounts,
            'total_taxes' => $totalTaxes,
            'avg_order_value' => $avgOrderValue,
            'chart_labels' => $chartData['labels'],
            'revenue_series' => $chartData['revenue'],
            'orders_series' => $chartData['orders'],
            'payments_distribution' => $paymentsDistribution,
            'transactions' => $transactions,
            'available_payment_methods' => $availablePaymentMethods,
            'available_order_types' => $availableOrderTypes,
            'filters' => $filters,
        ];
    }

    public function exportSalesCsv(array $filters, ?Tenant $tenant = null): StreamedResponse
    {
        $tenant = $this->resolveTenant($tenant);
        $range = $this->resolveDateRange(
            $filters['date_range'] ?? 'last_30_days',
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );

        $query = PosSale::query()
            ->whereBetween('completed_at', [$range['start'], $range['end']]);

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $paymentMethod = trim((string) ($filters['payment_method'] ?? ''));
        if ($paymentMethod !== '' && $paymentMethod !== 'all') {
            $query->where('payment_method', $paymentMethod);
        }

        $orderType = trim((string) ($filters['order_type'] ?? ''));
        if ($orderType !== '' && $orderType !== 'all') {
            $query->where('order_type', $orderType);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        $filename = 'sales-report-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Invoice #',
                'Completed At',
                'Customer Name',
                'Customer Code',
                'Order Type',
                'Item Count',
                'Subtotal (Base)',
                'Discount (Base)',
                'Tax (Base)',
                'Total (Base)',
                'Payment Method',
                'Status',
            ]);

            $query->orderByDesc('completed_at')->chunk(200, function ($sales) use ($handle) {
                foreach ($sales as $sale) {
                    fputcsv($handle, [
                        $sale->invoice_number,
                        optional($sale->completed_at)->toDateTimeString(),
                        $sale->customer_name ?: 'Walk-in',
                        $sale->customer_code ?: '-',
                        $sale->order_type ?: '-',
                        $sale->item_count,
                        number_format((float) $sale->subtotal_base, 2, '.', ''),
                        number_format((float) $sale->discount_base, 2, '.', ''),
                        number_format((float) $sale->tax_base, 2, '.', ''),
                        number_format((float) $sale->total_base, 2, '.', ''),
                        $sale->payment_method ?: '-',
                        ucfirst((string) $sale->status),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function getProductReport(array $filters, ?Tenant $tenant = null): array
    {
        $tenant = $this->resolveTenant($tenant);
        $range = $this->resolveDateRange(
            $filters['date_range'] ?? 'last_30_days',
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );
        $currency = $this->getCurrencyInfo($tenant);

        $query = PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->where('pos_sales.status', 'completed')
            ->whereBetween('pos_sales.completed_at', [$range['start'], $range['end']]);

        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        if ($categoryId) {
            $query->leftJoin('items', 'items.id', '=', 'pos_sale_items.item_id')
                ->where('items.category_id', $categoryId);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('pos_sale_items.name', 'like', "%{$search}%")
                    ->orWhere('pos_sale_items.sku', 'like', "%{$search}%");
            });
        }

        // Summary Aggregates
        $totalUnits = (float) (clone $query)->sum('pos_sale_items.quantity');
        $totalRevenue = (float) (clone $query)->sum('pos_sale_items.line_total_base');

        // Ranked Items
        $itemsReport = (clone $query)
            ->selectRaw('pos_sale_items.item_id, pos_sale_items.name, pos_sale_items.sku, SUM(pos_sale_items.quantity) as units_sold, SUM(pos_sale_items.line_total_base) as total_revenue, AVG(pos_sale_items.unit_price_base) as avg_price')
            ->groupBy('pos_sale_items.item_id', 'pos_sale_items.name', 'pos_sale_items.sku')
            ->orderByDesc('units_sold')
            ->paginate(15)
            ->withQueryString();

        // Top 10 for bar chart
        $topItems = (clone $query)
            ->selectRaw('pos_sale_items.name, SUM(pos_sale_items.quantity) as units_sold, SUM(pos_sale_items.line_total_base) as total_revenue')
            ->groupBy('pos_sale_items.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $categories = Category::query()->orderBy('name')->get();

        return [
            'range' => $range,
            'currency' => $currency,
            'total_units' => $totalUnits,
            'total_revenue' => $totalRevenue,
            'items_report' => $itemsReport,
            'top_chart_names' => $topItems->pluck('name')->all(),
            'top_chart_revenue' => $topItems->pluck('total_revenue')->map(fn ($v) => round((float) $v, 2))->all(),
            'top_chart_units' => $topItems->pluck('units_sold')->map(fn ($v) => round((float) $v, 0))->all(),
            'categories' => $categories,
            'filters' => $filters,
        ];
    }

    public function exportProductsCsv(array $filters, ?Tenant $tenant = null): StreamedResponse
    {
        $tenant = $this->resolveTenant($tenant);
        $range = $this->resolveDateRange(
            $filters['date_range'] ?? 'last_30_days',
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );

        $query = PosSaleItem::query()
            ->join('pos_sales', 'pos_sales.id', '=', 'pos_sale_items.pos_sale_id')
            ->where('pos_sales.status', 'completed')
            ->whereBetween('pos_sales.completed_at', [$range['start'], $range['end']]);

        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        if ($categoryId) {
            $query->leftJoin('items', 'items.id', '=', 'pos_sale_items.item_id')
                ->where('items.category_id', $categoryId);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('pos_sale_items.name', 'like', "%{$search}%")
                    ->orWhere('pos_sale_items.sku', 'like', "%{$search}%");
            });
        }

        $items = $query
            ->selectRaw('pos_sale_items.name, pos_sale_items.sku, SUM(pos_sale_items.quantity) as units_sold, SUM(pos_sale_items.line_total_base) as total_revenue, AVG(pos_sale_items.unit_price_base) as avg_price')
            ->groupBy('pos_sale_items.name', 'pos_sale_items.sku')
            ->orderByDesc('units_sold')
            ->get();

        $filename = 'products-report-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Rank',
                'Product Name',
                'SKU',
                'Units Sold',
                'Average Unit Price (Base)',
                'Gross Revenue (Base)',
            ]);

            foreach ($items as $index => $item) {
                fputcsv($handle, [
                    $index + 1,
                    $item->name,
                    $item->sku ?: '-',
                    number_format((float) $item->units_sold, 2, '.', ''),
                    number_format((float) $item->avg_price, 2, '.', ''),
                    number_format((float) $item->total_revenue, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function getInventoryReport(array $filters, ?Tenant $tenant = null): array
    {
        $tenant = $this->resolveTenant($tenant);
        $currency = $this->getCurrencyInfo($tenant);

        $query = Item::query()
            ->with(['category', 'branch', 'currency'])
            ->where('status', 'Active');

        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $stockStatus = trim((string) ($filters['stock_status'] ?? ''));
        if ($stockStatus === 'healthy') {
            $query->where('stock', '>', 10);
        } elseif ($stockStatus === 'low') {
            $query->whereBetween('stock', [1, 10]);
        } elseif ($stockStatus === 'out') {
            $query->where('stock', '<=', 0);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Summary KPIs via single SQL aggregation
        $kpis = Item::query()
            ->where('status', 'Active')
            ->selectRaw('COUNT(*) as total_active, SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as healthy, SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) as low_stock, SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock, COALESCE(SUM(stock * price), 0) as total_stock_value')
            ->first();

        $totalActiveItems = (int) ($kpis->total_active ?? 0);
        $healthyItems = (int) ($kpis->healthy ?? 0);
        $lowStockItems = (int) ($kpis->low_stock ?? 0);
        $outOfStockItems = (int) ($kpis->out_of_stock ?? 0);
        $totalStockValue = (float) ($kpis->total_stock_value ?? 0);

        $inventoryItems = (clone $query)
            ->orderBy('stock')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get();

        return [
            'currency' => $currency,
            'total_active_items' => $totalActiveItems,
            'healthy_items' => $healthyItems,
            'low_stock_items' => $lowStockItems,
            'out_of_stock_items' => $outOfStockItems,
            'total_stock_value' => $totalStockValue,
            'inventory_items' => $inventoryItems,
            'categories' => $categories,
            'filters' => $filters,
        ];
    }

    public function exportInventoryCsv(array $filters, ?Tenant $tenant = null): StreamedResponse
    {
        $tenant = $this->resolveTenant($tenant);
        $query = Item::query()
            ->with(['category'])
            ->where('status', 'Active');

        $categoryId = filled($filters['category_id'] ?? null) ? (int) $filters['category_id'] : null;
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $stockStatus = trim((string) ($filters['stock_status'] ?? ''));
        if ($stockStatus === 'healthy') {
            $query->where('stock', '>', 10);
        } elseif ($stockStatus === 'low') {
            $query->whereBetween('stock', [1, 10]);
        } elseif ($stockStatus === 'out') {
            $query->where('stock', '<=', 0);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $filename = 'inventory-report-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SKU',
                'Item Name',
                'Category',
                'Stock Units',
                'Stock Status',
                'Unit Price (Base)',
                'Stock Value (Base)',
            ]);

            $query->orderBy('stock')->chunk(200, function ($items) use ($handle) {
                foreach ($items as $item) {
                    $stock = (float) $item->stock;
                    $status = $stock > 10 ? 'Healthy' : ($stock > 0 ? 'Low Stock' : 'Out of Stock');
                    $value = $stock * (float) $item->price;

                    fputcsv($handle, [
                        $item->sku ?: '-',
                        $item->name,
                        optional($item->category)->name ?: 'Unassigned',
                        $stock,
                        $status,
                        number_format((float) $item->price, 2, '.', ''),
                        number_format($value, 2, '.', ''),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function getPaymentReport(array $filters, ?Tenant $tenant = null): array
    {
        $tenant = $this->resolveTenant($tenant);
        $range = $this->resolveDateRange(
            $filters['date_range'] ?? 'last_30_days',
            $filters['start_date'] ?? null,
            $filters['end_date'] ?? null
        );
        $currency = $this->getCurrencyInfo($tenant);

        $completedQuery = PosSale::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$range['start'], $range['end']]);

        $summary = (clone $completedQuery)
            ->selectRaw('COUNT(*) as total_tx, COALESCE(SUM(total_base), 0) as total_rev')
            ->first();

        $totalRevenue = (float) ($summary->total_rev ?? 0);
        $totalTransactions = (int) ($summary->total_tx ?? 0);

        $payments = (clone $completedQuery)
            ->selectRaw('payment_method, COUNT(*) as tx_count, SUM(total_base) as total_amount, AVG(total_base) as avg_amount')
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($p) use ($totalRevenue) {
                $amount = (float) $p->total_amount;
                $p->total_amount = $amount;
                $p->percentage = $totalRevenue > 0 ? round(($amount / $totalRevenue) * 100, 1) : 0;
                return $p;
            });

        return [
            'range' => $range,
            'currency' => $currency,
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'payments' => $payments,
            'chart_methods' => $payments->pluck('payment_method')->map(fn ($m) => $m ?: 'Unknown')->all(),
            'chart_amounts' => $payments->pluck('total_amount')->map(fn ($a) => round((float) $a, 2))->all(),
            'filters' => $filters,
        ];
    }

    private function buildSalesChartData(Builder $query, Carbon $start, Carbon $end): array
    {
        $daysDiff = $start->diffInDays($end);
        $labels = [];
        $revenueData = [];
        $ordersData = [];

        // If range is within 31 days, group by day via single SQL aggregation
        if ($daysDiff <= 31) {
            $grouped = (clone $query)
                ->selectRaw('DATE(completed_at) as sale_date, COUNT(*) as orders_count, COALESCE(SUM(total_base), 0) as total_revenue')
                ->groupBy(DB::raw('DATE(completed_at)'))
                ->get()
                ->keyBy('sale_date');

            $current = $start->copy()->startOfDay();
            $endDay = $end->copy()->startOfDay();

            while ($current->lte($endDay)) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format('M d');
                $row = $grouped->get($dateKey);
                $revenueData[] = $row ? round((float) $row->total_revenue, 2) : 0.0;
                $ordersData[] = $row ? (int) $row->orders_count : 0;
                $current->addDay();
            }
        } else {
            // Group by month via single SQL aggregation
            $grouped = (clone $query)
                ->selectRaw('SUBSTR(completed_at, 1, 7) as sale_month, COUNT(*) as orders_count, COALESCE(SUM(total_base), 0) as total_revenue')
                ->groupBy(DB::raw('SUBSTR(completed_at, 1, 7)'))
                ->get()
                ->keyBy('sale_month');

            $current = $start->copy()->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();

            while ($current->lte($endMonth)) {
                $monthKey = $current->format('Y-m');
                $labels[] = $current->format('M Y');
                $row = $grouped->get($monthKey);
                $revenueData[] = $row ? round((float) $row->total_revenue, 2) : 0.0;
                $ordersData[] = $row ? (int) $row->orders_count : 0;
                $current->addMonth();
            }
        }

        return [
            'labels' => $labels,
            'revenue' => $revenueData,
            'orders' => $ordersData,
        ];
    }
}
