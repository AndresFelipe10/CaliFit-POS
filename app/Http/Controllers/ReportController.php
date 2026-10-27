<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Reports Center / Hub overview.
     */
    public function index(Request $request): View
    {
        $hubData = $this->reportService->getHubSummary();

        return view('reports.index', [
            'salesSummary' => $hubData['salesSummary'],
            'inventorySummary' => $hubData['inventorySummary'],
        ]);
    }

    /**
     * Sales performance report with KPIs and filters.
     */
    public function sales(Request $request): View|StreamedResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'date_from', 'date_to', 'payment_method', 'customer_id']);

        if ($request->query('export') === 'csv') {
            return $this->exportSalesCsv($request);
        }

        $data = $this->reportService->getSalesReport($filters);

        return view('reports.sales', $data);
    }

    /**
     * Export sales report to CSV.
     */
    public function exportSalesCsv(Request $request): StreamedResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'date_from', 'date_to', 'payment_method', 'customer_id']);

        return $this->reportService->exportSalesCsv($filters);
    }

    /**
     * Inventory valuation and margin report.
     */
    public function inventoryValuation(Request $request): View|StreamedResponse
    {
        $filters = $request->only(['category_id', 'search']);

        if ($request->query('export') === 'csv') {
            return $this->exportInventoryCsv($request);
        }

        $data = $this->reportService->getInventoryValuationReport($filters);

        return view('reports.inventory', $data);
    }

    /**
     * Alias for inventoryValuation.
     */
    public function inventory(Request $request): View|StreamedResponse
    {
        return $this->inventoryValuation($request);
    }

    /**
     * Export inventory valuation report to CSV.
     */
    public function exportInventoryCsv(Request $request): StreamedResponse
    {
        $filters = $request->only(['category_id', 'search']);

        return $this->reportService->exportInventoryCsv($filters);
    }

    /**
     * Top selling products and profitability report.
     */
    public function topProducts(Request $request): View|StreamedResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'date_from', 'date_to']);

        if ($request->query('export') === 'csv') {
            return $this->exportTopProductsCsv($request);
        }

        $data = $this->reportService->getTopProductsReport($filters);

        return view('reports.top-products', $data);
    }

    /**
     * Export top products report to CSV.
     */
    public function exportTopProductsCsv(Request $request): StreamedResponse
    {
        $filters = $request->only(['start_date', 'end_date', 'date_from', 'date_to']);

        return $this->reportService->exportTopProductsCsv($filters);
    }
}
