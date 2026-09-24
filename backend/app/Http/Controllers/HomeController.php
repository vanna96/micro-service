<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Tenant;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(): View
    {
        $tenantOptions = admin_accessible_tenants();
        $selectedTenant = admin_current_tenant();
        $metricsTenant = $selectedTenant ?: ($tenantOptions->count() === 1 ? $tenantOptions->first() : null);

        return view('home', [
            'tenantOptions' => $tenantOptions,
            'selectedTenant' => $selectedTenant,
            'dashboard' => $this->dashboardData($metricsTenant),
        ]);
    }

    protected function dashboardData(?Tenant $tenant): array
    {
        $today = Carbon::today();
        $days = collect(range(6, 0))->map(fn (int $offset) => $today->copy()->subDays($offset));
        $dashboard = [
            'currency_code' => strtoupper((string) data_get($tenant?->general_settings, 'currency', 'USD')) ?: 'USD',
            'currency_symbol' => '$',
            'currency_decimals' => 2,
            'revenue_today' => 0.0,
            'revenue_yesterday' => 0.0,
            'orders_today' => 0,
            'orders_yesterday' => 0,
            'customers' => 0,
            'active_promotions' => 0,
            'active_items' => 0,
            'low_stock_items' => 0,
            'out_of_stock_items' => 0,
            'held_sales' => 0,
            'seven_day_revenue' => 0.0,
            'average_order_value' => 0.0,
            'chart_labels' => $days->map(fn (Carbon $day) => $day->format('D'))->all(),
            'revenue_series' => array_fill(0, 7, 0.0),
            'orders_series' => array_fill(0, 7, 0),
            'recent_sales' => collect(),
            'top_items' => collect(),
        ];

        if (! $tenant) {
            return $this->withDashboardTrends($dashboard);
        }

        try {
            if (! tenant() || (string) tenant('id') !== (string) $tenant->id) {
                tenancy()->initialize($tenant);
            }

            $connection = DB::connection($tenant->database_connection_name ?: 'tenant');
            $schema = $connection->getSchemaBuilder();
            $this->hydrateCurrency($connection, $schema->hasTable('currencies'), $dashboard);

            if ($schema->hasTable('items')) {
                $dashboard['active_items'] = (int) $connection->table('items')->where('status', 'Active')->count();
                $dashboard['low_stock_items'] = (int) $connection->table('items')
                    ->where('status', 'Active')
                    ->whereBetween('stock', [1, 10])
                    ->count();
                $dashboard['out_of_stock_items'] = (int) $connection->table('items')
                    ->where('status', 'Active')
                    ->where('stock', '<=', 0)
                    ->count();
            }

            if ($schema->hasTable('customers')) {
                $dashboard['customers'] = (int) $connection->table('customers')->where('status', 'Active')->count();
            }

            if ($schema->hasTable('promotions')) {
                $dashboard['active_promotions'] = (int) $connection->table('promotions')
                    ->where('status', 'Active')
                    ->where('start_at', '<=', now())
                    ->where('end_at', '>=', now())
                    ->count();
            }

            if ($schema->hasTable('pos_sales') && $schema->hasColumn('pos_sales', 'total_base')) {
                $this->hydrateSales($connection, $days, $dashboard);
            }

            if ($schema->hasTable('pos_sale_items') && $schema->hasTable('pos_sales')) {
                $dashboard['top_items'] = $connection->table('pos_sale_items as lines')
                    ->join('pos_sales as sales', 'sales.id', '=', 'lines.pos_sale_id')
                    ->where('sales.status', 'completed')
                    ->selectRaw('lines.name, lines.sku, SUM(lines.quantity) as units, SUM(lines.line_total_base) as revenue')
                    ->groupBy('lines.name', 'lines.sku')
                    ->orderByDesc('units')
                    ->limit(5)
                    ->get();
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to load dashboard metrics.', [
                'tenant_id' => $tenant->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return $this->withDashboardTrends($dashboard);
    }

    protected function hydrateCurrency(ConnectionInterface $connection, bool $hasCurrencies, array &$dashboard): void
    {
        if (! $hasCurrencies) {
            return;
        }

        $currency = $connection->table('currencies')
            ->whereRaw('UPPER(code) = ?', [$dashboard['currency_code']])
            ->first();

        if (! $currency) {
            return;
        }

        $dashboard['currency_symbol'] = (string) ($currency->symbol ?: $currency->code);
        $dashboard['currency_decimals'] = max(0, (int) ($currency->decimal_places ?? 2));
    }

    protected function hydrateSales(ConnectionInterface $connection, $days, array &$dashboard): void
    {
        $completedSales = fn () => $connection->table('pos_sales')->where('status', 'completed');
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $dashboard['revenue_today'] = (float) $completedSales()
            ->whereBetween('completed_at', [$today, $today->copy()->endOfDay()])
            ->sum('total_base');
        $dashboard['orders_today'] = (int) $completedSales()
            ->whereBetween('completed_at', [$today, $today->copy()->endOfDay()])
            ->count();
        $dashboard['revenue_yesterday'] = (float) $completedSales()
            ->whereBetween('completed_at', [$yesterday, $yesterday->copy()->endOfDay()])
            ->sum('total_base');
        $dashboard['orders_yesterday'] = (int) $completedSales()
            ->whereBetween('completed_at', [$yesterday, $yesterday->copy()->endOfDay()])
            ->count();
        $dashboard['held_sales'] = (int) $connection->table('pos_sales')->where('status', 'held')->count();

        $revenueSeries = [];
        $ordersSeries = [];

        foreach ($days as $day) {
            $start = $day->copy()->startOfDay();
            $end = $day->copy()->endOfDay();
            $revenueSeries[] = round((float) $completedSales()->whereBetween('completed_at', [$start, $end])->sum('total_base'), 2);
            $ordersSeries[] = (int) $completedSales()->whereBetween('completed_at', [$start, $end])->count();
        }

        $dashboard['revenue_series'] = $revenueSeries;
        $dashboard['orders_series'] = $ordersSeries;
        $dashboard['seven_day_revenue'] = array_sum($revenueSeries);
        $sevenDayOrders = array_sum($ordersSeries);
        $dashboard['average_order_value'] = $sevenDayOrders > 0
            ? $dashboard['seven_day_revenue'] / $sevenDayOrders
            : 0;
        $dashboard['recent_sales'] = $completedSales()
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get([
                'invoice_number',
                'customer_name',
                'item_count',
                'payment_method',
                'total_base',
                'completed_at',
            ]);
    }

    protected function withDashboardTrends(array $dashboard): array
    {
        $dashboard['revenue_trend'] = $this->percentageChange(
            $dashboard['revenue_today'],
            $dashboard['revenue_yesterday']
        );
        $dashboard['orders_trend'] = $this->percentageChange(
            $dashboard['orders_today'],
            $dashboard['orders_yesterday']
        );
        $dashboard['stock_health'] = $dashboard['active_items'] > 0
            ? round((($dashboard['active_items'] - $dashboard['out_of_stock_items']) / $dashboard['active_items']) * 100)
            : 0;

        return $dashboard;
    }

    protected function percentageChange(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / abs($previous)) * 100, 1);
    }
}
