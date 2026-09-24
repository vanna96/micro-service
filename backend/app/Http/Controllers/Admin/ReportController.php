<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService)
    {
        $this->middleware('admin.permission:reports.view');
        $this->middleware('admin.permission:reports.export')->only([
            'salesExport',
            'productsExport',
            'inventoryExport',
        ]);
    }

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.reports.sales');
    }

    public function sales(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $filters = $request->only([
            'date_range',
            'start_date',
            'end_date',
            'status',
            'payment_method',
            'order_type',
            'search',
        ]);

        $reportData = $this->reportService->getSalesReport($filters, $selectedTenant);

        return view('admin.reports.sales', array_merge($reportData, [
            'selectedTenant' => $selectedTenant,
        ]));
    }

    public function salesExport(Request $request): StreamedResponse
    {
        $selectedTenant = $this->requiredTenant();
        $filters = $request->only([
            'date_range',
            'start_date',
            'end_date',
            'status',
            'payment_method',
            'order_type',
            'search',
        ]);

        return $this->reportService->exportSalesCsv($filters, $selectedTenant);
    }

    public function saleDetail(Request $request, int $sale): View|JsonResponse
    {
        $selectedTenant = $this->requiredTenant();
        $detail = $this->reportService->getSaleDetail($sale, $selectedTenant);

        abort_if(! $detail, 404, 'Sale not found');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($detail);
        }

        return view('admin.reports.invoice-print', array_merge($detail, [
            'selectedTenant' => $selectedTenant,
            'format' => $request->query('format', 'a4'),
            'autoPrint' => false,
        ]));
    }

    public function salePrint(Request $request, int $sale): View
    {
        $selectedTenant = $this->requiredTenant();
        $detail = $this->reportService->getSaleDetail($sale, $selectedTenant);

        abort_if(! $detail, 404, 'Sale not found');

        return view('admin.reports.invoice-print', array_merge($detail, [
            'selectedTenant' => $selectedTenant,
            'format' => $request->query('format', 'a4'),
            'autoPrint' => $request->boolean('auto_print', true),
        ]));
    }

    public function products(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $filters = $request->only([
            'date_range',
            'start_date',
            'end_date',
            'category_id',
            'search',
        ]);

        $reportData = $this->reportService->getProductReport($filters, $selectedTenant);

        return view('admin.reports.products', array_merge($reportData, [
            'selectedTenant' => $selectedTenant,
        ]));
    }

    public function productsExport(Request $request): StreamedResponse
    {
        $selectedTenant = $this->requiredTenant();
        $filters = $request->only([
            'date_range',
            'start_date',
            'end_date',
            'category_id',
            'search',
        ]);

        return $this->reportService->exportProductsCsv($filters, $selectedTenant);
    }

    public function inventory(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $filters = $request->only([
            'category_id',
            'stock_status',
            'search',
        ]);

        $reportData = $this->reportService->getInventoryReport($filters, $selectedTenant);

        return view('admin.reports.inventory', array_merge($reportData, [
            'selectedTenant' => $selectedTenant,
        ]));
    }

    public function inventoryExport(Request $request): StreamedResponse
    {
        $selectedTenant = $this->requiredTenant();
        $filters = $request->only([
            'category_id',
            'stock_status',
            'search',
        ]);

        return $this->reportService->exportInventoryCsv($filters, $selectedTenant);
    }

    public function payments(Request $request): View
    {
        $selectedTenant = $this->requiredTenant();
        $filters = $request->only([
            'date_range',
            'start_date',
            'end_date',
        ]);

        $reportData = $this->reportService->getPaymentReport($filters, $selectedTenant);

        return view('admin.reports.payments', array_merge($reportData, [
            'selectedTenant' => $selectedTenant,
        ]));
    }

    protected function requiredTenant(): Tenant
    {
        $tenant = admin_current_tenant();

        abort_if(! $tenant, 404);

        if (! tenant() || (string) tenant('id') !== (string) $tenant->id) {
            tenancy()->initialize($tenant);
        }

        return $tenant;
    }
}
