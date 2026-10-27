<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportService
{
    /**
     * Hub overview data for Managerial Reports.
     */
    public function getHubSummary(): array
    {
        $now = Carbon::now('America/Bogota');
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $monthRevenue = (float) Sale::where('status', 'completed')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('total');

        $inventoryCost = (float) Product::where('activo', true)
            ->whereIn('tipo_producto', ['INSUMO', 'SUPLEMENTO'])
            ->selectRaw('SUM(stock * precio_compra) as total_cost')
            ->value('total_cost') ?? 0.0;

        return [
            'salesSummary' => [
                'total_revenue' => $monthRevenue,
            ],
            'inventorySummary' => [
                'total_cost_valuation' => $inventoryCost,
            ],
        ];
    }

    /**
     * Generate sales performance report.
     */
    public function getSalesReport(array $filters = [], bool $paginate = true): array
    {
        $dateFromStr = $filters['start_date'] ?? $filters['date_from'] ?? null;
        $dateToStr = $filters['end_date'] ?? $filters['date_to'] ?? null;

        $dateFrom = !empty($dateFromStr)
            ? Carbon::parse($dateFromStr, 'America/Bogota')->startOfDay()
            : Carbon::now('America/Bogota')->startOfMonth();

        $dateTo = !empty($dateToStr)
            ? Carbon::parse($dateToStr, 'America/Bogota')->endOfDay()
            : Carbon::now('America/Bogota')->endOfDay();

        $query = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->with(['customer', 'user', 'payments']);

        if (!empty($filters['payment_method'])) {
            $methodFilter = $filters['payment_method'];
            if ($methodFilter === 'mixed') {
                $query->where('payment_method', 'mixed');
            } else {
                $query->where(function ($q) use ($methodFilter) {
                    $q->where('payment_method', $methodFilter)
                      ->orWhereHas('payments', function ($sub) use ($methodFilter) {
                          $sub->where('method', $methodFilter);
                      });
                });
            }
        }

        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Métricas superiores consolidadas sobre el universo filtrado
        $totalRevenue = (float) (clone $query)->sum('total');
        $salesCount = (int) (clone $query)->count();
        $averageTicket = $salesCount > 0 ? round($totalRevenue / $salesCount, 2) : 0.0;
        $totalDiscounts = (float) (clone $query)->sum('discount');

        $sales = $paginate
            ? (clone $query)->orderByDesc('created_at')->paginate(15)->withQueryString()
            : (clone $query)->orderByDesc('created_at')->get();

        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        return [
            'sales' => $sales,
            'customers' => $customers,
            'total_revenue' => $totalRevenue,
            'sales_count' => $salesCount,
            'average_ticket' => $averageTicket,
            'total_discounts' => $totalDiscounts,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'start_date' => $dateFrom->format('Y-m-d'),
            'end_date' => $dateTo->format('Y-m-d'),
            'selected_payment_method' => $filters['payment_method'] ?? '',
            'selected_customer_id' => $filters['customer_id'] ?? '',
        ];
    }

    /**
     * Export sales report as StreamedResponse with UTF-8 BOM.
     */
    public function exportSalesCsv(array $filters = []): StreamedResponse
    {
        $data = $this->getSalesReport($filters, paginate: false);
        $filename = 'califit_reporte_ventas_' . Carbon::now('America/Bogota')->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // Escribir BOM UTF-8 para apertura directa en Excel en español
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Consecutivo',
                'Fecha / Hora',
                'Cliente',
                'Vendedor / Cajero',
                'Método de Pago',
                'Subtotal',
                'Descuento',
                'Total Facturado',
            ]);

            foreach ($data['sales'] as $sale) {
                $paymentMethod = $sale->payment_method;
                if ($sale->payments->isNotEmpty()) {
                    $methodsList = $sale->payments->map(fn ($p) => $p->method_label ?? (string) $p->method)->implode(', ');
                    $paymentMethod = $methodsList ?: $paymentMethod;
                }

                $row = [
                    $sale->invoice_number,
                    $sale->created_at->format('Y-m-d H:i:s'),
                    $sale->customer_name ?: ($sale->customer ? $sale->customer->name : 'CONSUMIDOR FINAL'),
                    $sale->user ? $sale->user->name : ($sale->waiter_name ?: 'N/A'),
                    $paymentMethod,
                    number_format((float) $sale->subtotal, 2, '.', ''),
                    number_format((float) $sale->discount, 2, '.', ''),
                    number_format((float) $sale->total, 2, '.', ''),
                ];

                fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Generate inventory valuation report (Costo vs Comercial y Margen Proyectado).
     */
    public function getInventoryValuationReport(array $filters = []): array
    {
        // Considera productos activos de tipo INSUMO y SUPLEMENTO
        $query = Product::where('activo', true)
            ->whereIn('tipo_producto', ['INSUMO', 'SUPLEMENTO'])
            ->with('category');

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('nombre')->get();

        $totalUnits = 0.0;
        $totalCostValuation = 0.0;
        $totalRetailValuation = 0.0;

        $enrichedProducts = $products->map(function (Product $product) use (
            &$totalUnits,
            &$totalCostValuation,
            &$totalRetailValuation
        ) {
            $stock = max(0, (float) $product->stock);
            $costPrice = (float) $product->precio_compra;
            $salePrice = (float) $product->precio_venta;

            $costVal = round($stock * $costPrice, 2);
            $retailVal = round($stock * $salePrice, 2);
            $potentialProfit = round($retailVal - $costVal, 2);
            $marginPercent = $retailVal > 0 ? round(($potentialProfit / $retailVal) * 100, 1) : 0.0;

            $totalUnits += $stock;
            $totalCostValuation += $costVal;
            $totalRetailValuation += $retailVal;

            // Atributos dinámicos para presentación
            $product->cost_valuation = $costVal;
            $product->retail_valuation = $retailVal;
            $product->potential_profit = $potentialProfit;
            $product->margin_percent = $marginPercent;
            $product->name = $product->nombre;
            $product->cost_price = $costPrice;
            $product->sale_price = $salePrice;
            $product->min_stock = $product->stock_minimo;

            return $product;
        });

        $totalPotentialProfit = round($totalRetailValuation - $totalCostValuation, 2);
        $potentialMarginPercent = $totalRetailValuation > 0
            ? round(($totalPotentialProfit / $totalRetailValuation) * 100, 1)
            : 0.0;

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return [
            'products' => $enrichedProducts,
            'categories' => $categories,
            'total_products' => $products->count(),
            'total_units' => $totalUnits,
            'total_cost_valuation' => $totalCostValuation,
            'total_retail_valuation' => $totalRetailValuation,
            'total_potential_profit' => $totalPotentialProfit,
            'potential_margin_percent' => $potentialMarginPercent,
            'selected_category_id' => $filters['category_id'] ?? '',
            'search' => $filters['search'] ?? '',
        ];
    }

    /**
     * Export inventory valuation report as StreamedResponse with UTF-8 BOM.
     */
    public function exportInventoryCsv(array $filters = []): StreamedResponse
    {
        $data = $this->getInventoryValuationReport($filters);
        $filename = 'califit_reporte_inventario_' . Carbon::now('America/Bogota')->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'SKU',
                'Producto',
                'Categoría',
                'Tipo de Producto',
                'Stock Actual',
                'Unidad Base',
                'Stock Mínimo',
                'Costo Unitario',
                'Precio Venta',
                'Valor Costo',
                'Valor Venta',
                'Ganancia Estimada',
                'Margen %',
            ]);

            foreach ($data['products'] as $prod) {
                $row = [
                    $prod->sku,
                    $prod->nombre,
                    $prod->category ? $prod->category->name : 'Sin categoría',
                    $prod->tipo_producto,
                    $prod->stock,
                    $prod->unidad_base,
                    $prod->stock_minimo,
                    number_format((float) $prod->precio_compra, 2, '.', ''),
                    number_format((float) $prod->precio_venta, 2, '.', ''),
                    number_format((float) $prod->cost_valuation, 2, '.', ''),
                    number_format((float) $prod->retail_valuation, 2, '.', ''),
                    number_format((float) $prod->potential_profit, 2, '.', ''),
                    $prod->margin_percent . '%',
                ];

                fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Generate top selling products and profitability report (Rotación, COGS y Utilidad Bruta).
     */
    public function getTopProductsReport(array $filters = []): array
    {
        $dateFromStr = $filters['start_date'] ?? $filters['date_from'] ?? null;
        $dateToStr = $filters['end_date'] ?? $filters['date_to'] ?? null;

        $dateFrom = !empty($dateFromStr)
            ? Carbon::parse($dateFromStr, 'America/Bogota')->startOfDay()
            : Carbon::now('America/Bogota')->startOfMonth();

        $dateTo = !empty($dateToStr)
            ? Carbon::parse($dateToStr, 'America/Bogota')->endOfDay()
            : Carbon::now('America/Bogota')->endOfDay();

        $results = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$dateFrom, $dateTo])
            ->select(
                'products.id',
                'products.nombre as name',
                'products.sku',
                'products.precio_compra as cost_price',
                'products.precio_venta as sale_price',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue')
            )
            ->groupBy(
                'products.id',
                'products.nombre',
                'products.sku',
                'products.precio_compra',
                'products.precio_venta'
            )
            ->orderByDesc('total_revenue')
            ->get();

        $totalItemsSold = 0.0;
        $totalRevenue = 0.0;
        $totalCogs = 0.0;

        $products = $results->map(function ($item) use (&$totalItemsSold, &$totalRevenue, &$totalCogs) {
            $qty = (float) $item->total_quantity;
            $revenue = (float) $item->total_revenue;
            $costPrice = (float) $item->cost_price;

            $cogs = round($qty * $costPrice, 2);
            $grossProfit = round($revenue - $cogs, 2);
            $marginPercent = $revenue > 0 ? round(($grossProfit / $revenue) * 100, 1) : 0.0;

            $totalItemsSold += $qty;
            $totalRevenue += $revenue;
            $totalCogs += $cogs;

            $item->cogs = $cogs;
            $item->gross_profit = $grossProfit;
            $item->margin_percent = $marginPercent;

            return $item;
        });

        $totalGrossProfit = round($totalRevenue - $totalCogs, 2);
        $overallMarginPercent = $totalRevenue > 0 ? round(($totalGrossProfit / $totalRevenue) * 100, 1) : 0.0;

        return [
            'products' => $products,
            'total_items_sold' => $totalItemsSold,
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_gross_profit' => $totalGrossProfit,
            'overall_margin_percent' => $overallMarginPercent,
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
            'start_date' => $dateFrom->format('Y-m-d'),
            'end_date' => $dateTo->format('Y-m-d'),
        ];
    }

    /**
     * Export top products and profitability report as StreamedResponse with UTF-8 BOM.
     */
    public function exportTopProductsCsv(array $filters = []): StreamedResponse
    {
        $data = $this->getTopProductsReport($filters);
        $filename = 'califit_reporte_top_productos_' . Carbon::now('America/Bogota')->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($data) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'Posición',
                'SKU',
                'Producto',
                'Unidades Vendidas',
                'Costo Unitario',
                'Precio Venta',
                'Ingresos Totales',
                'Costo Mercancía (COGS)',
                'Utilidad Bruta',
                'Margen %',
            ]);

            foreach ($data['products'] as $idx => $prod) {
                $row = [
                    $idx + 1,
                    $prod->sku,
                    $prod->name,
                    $prod->total_quantity,
                    number_format((float) $prod->cost_price, 2, '.', ''),
                    number_format((float) $prod->sale_price, 2, '.', ''),
                    number_format((float) $prod->total_revenue, 2, '.', ''),
                    number_format((float) $prod->cogs, 2, '.', ''),
                    number_format((float) $prod->gross_profit, 2, '.', ''),
                    $prod->margin_percent . '%',
                ];

                fputcsv($handle, array_map([$this, 'sanitizeCsvCell'], $row));
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Sanitize cell value to prevent CSV formula injection (CWE-1236).
     */
    private function sanitizeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }

        return $value;
    }
}
