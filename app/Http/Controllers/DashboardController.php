<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show application dashboard tailored by user role.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = (bool) $user->isAdmin();

        $nowBogota = Carbon::now('America/Bogota');
        $startOfDay = $nowBogota->copy()->startOfDay();
        $endOfDay = $nowBogota->copy()->endOfDay();
        $startOfMonth = $nowBogota->copy()->startOfMonth();
        $endOfMonth = $nowBogota->copy()->endOfMonth();

        // 1. Métricas operativas comunes (sin montos para empleados)
        $todayCompletedSalesCount = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->count();

        $occupiedTablesCount = RestaurantTable::where('status', 'occupied')->count();

        // Despacho activo sincronizado exactamente con el tablero Kanban (/restaurant/dispatch):
        // Solo pedidos delivery/takeout en preparación (in_kitchen), en camino (dispatched) o entregados (delivered).
        $activeOrdersCount = RestaurantOrder::whereIn('order_type', ['delivery', 'takeout'])
            ->whereIn('status', ['in_kitchen', 'dispatched', 'delivered'])
            ->count();

        $criticalStockProducts = Product::where('activo', true)
            ->where(function ($q) {
                $q->where('stock', '<=', 0)
                  ->orWhereRaw('stock <= stock_minimo');
            })
            ->orderBy('stock')
            ->take(8)
            ->get();

        if (! $isAdmin) {
            // Dashboard Operativo para Empleados / Cajeros (Cero dinero visible)
            return view('dashboard', [
                'user' => $user,
                'isAdmin' => false,
                'todaySalesCount' => $todayCompletedSalesCount,
                'occupiedTablesCount' => $occupiedTablesCount,
                'activeOrdersCount' => $activeOrdersCount,
                'criticalStockProducts' => $criticalStockProducts,
            ]);
        }

        // 2. Parámetros de Período (Día vs Mes)
        $period = $request->query('period', 'month');
        if (! in_array($period, ['day', 'month'], true)) {
            $period = 'month';
        }

        $selectedDate = $request->query('date', $nowBogota->toDateString());
        $selectedMonth = $request->query('month', $nowBogota->format('Y-m'));

        // Métricas fijas del mes actual (compatibilidad y pruebas existentes)
        $monthSales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();
        $monthSalesTotal = (float) $monthSales->sum('total');
        $monthSalesCount = $monthSales->count();
        $monthExpensesTotal = (float) Expense::whereDate('issue_date', '>=', $startOfMonth->toDateString())
            ->whereDate('issue_date', '<=', $endOfMonth->toDateString())
            ->sum('amount');

        // Métricas fijas de hoy
        $todaySalesTotal = (float) Sale::where('status', 'completed')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->sum('total');

        // Métricas del Período Seleccionado
        if ($period === 'day') {
            try {
                $parsedDate = Carbon::parse($selectedDate, 'America/Bogota');
            } catch (\Exception $e) {
                $parsedDate = $nowBogota->copy();
                $selectedDate = $parsedDate->toDateString();
            }

            $periodStart = $parsedDate->copy()->startOfDay();
            $periodEnd = $parsedDate->copy()->endOfDay();
            $periodLabel = $parsedDate->isToday()
                ? 'Hoy (' . $parsedDate->format('d/m/Y') . ')'
                : $parsedDate->format('d/m/Y');

            $periodSales = Sale::where('status', 'completed')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->get();
            $periodSalesTotal = (float) $periodSales->sum('total');
            $periodSalesCount = $periodSales->count();
            $periodAverageTicket = $periodSalesCount > 0 ? round($periodSalesTotal / $periodSalesCount, 2) : 0.0;

            $periodExpensesTotal = (float) Expense::where('issue_date', $parsedDate->toDateString())
                ->sum('amount');
            $periodNetProfit = round($periodSalesTotal - $periodExpensesTotal, 2);
        } else {
            try {
                $parsedMonth = Carbon::parse($selectedMonth . '-01', 'America/Bogota');
            } catch (\Exception $e) {
                $parsedMonth = $nowBogota->copy()->startOfMonth();
                $selectedMonth = $parsedMonth->format('Y-m');
            }

            $periodStart = $parsedMonth->copy()->startOfMonth();
            $periodEnd = $parsedMonth->copy()->endOfMonth();
            $periodLabel = 'Mes de ' . $parsedMonth->translatedFormat('F Y');

            $periodSales = Sale::where('status', 'completed')
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->get();
            $periodSalesTotal = (float) $periodSales->sum('total');
            $periodSalesCount = $periodSales->count();
            $periodAverageTicket = $periodSalesCount > 0 ? round($periodSalesTotal / $periodSalesCount, 2) : 0.0;

            $periodExpensesTotal = (float) Expense::whereDate('issue_date', '>=', $periodStart->toDateString())
                ->whereDate('issue_date', '<=', $periodEnd->toDateString())
                ->sum('amount');
            $periodNetProfit = round($periodSalesTotal - $periodExpensesTotal, 2);
        }

        $periodTipsTotal = (float) $periodSales->sum(fn ($s) => (float) $s->tip + (float) $s->service_fee);
        $periodTipsCount = $periodSales->filter(fn ($s) => (float) $s->tip > 0 || (float) $s->service_fee > 0)->count();

        $pendingExpensesTotal = (float) Expense::where('status', 'pending')->sum('amount');

        // Top 5 Productos más vendidos en el período
        $topProducts = SaleDetail::select(
                'product_id',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(subtotal) as total_amount')
            )
            ->whereHas('sale', function ($q) use ($periodStart, $periodEnd) {
                $q->where('status', 'completed')
                  ->whereBetween('created_at', [$periodStart, $periodEnd]);
            })
            ->with('product')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->take(5)
            ->get();

        return view('dashboard', [
            'user' => $user,
            'isAdmin' => true,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'selectedDate' => $selectedDate,
            'selectedMonth' => $selectedMonth,
            'todaySalesCount' => $todayCompletedSalesCount,
            'todaySalesTotal' => $todaySalesTotal,
            'monthSalesCount' => $monthSalesCount,
            'monthSalesTotal' => $monthSalesTotal,
            'monthExpensesTotal' => $monthExpensesTotal,
            'periodSalesTotal' => $periodSalesTotal,
            'periodSalesCount' => $periodSalesCount,
            'periodExpensesTotal' => $periodExpensesTotal,
            'pendingExpensesTotal' => $pendingExpensesTotal,
            'periodTipsTotal' => $periodTipsTotal,
            'periodTipsCount' => $periodTipsCount,
            'netProfit' => $periodNetProfit,
            'averageTicket' => $periodAverageTicket,
            'occupiedTablesCount' => $occupiedTablesCount,
            'activeOrdersCount' => $activeOrdersCount,
            'criticalStockProducts' => $criticalStockProducts,
            'topProducts' => $topProducts,
        ]);
    }
}
