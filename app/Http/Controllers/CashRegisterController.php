<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    /**
     * Muestra la pantalla interactiva de Cuadre de Caja Diario y Arqueo.
     */
    public function index(Request $request): View
    {
        $metrics = $this->calculateDailyMetrics($request->input('date'));

        return view('cash-register.index', $metrics);
    }

    /**
     * Renderiza la tirilla térmica de 80 mm para el cierre de caja diario.
     */
    public function printReceipt(Request $request): View
    {
        $metrics = $this->calculateDailyMetrics($request->input('date'));

        return view('prints.cash-register', $metrics);
    }

    /**
     * Calcula todas las métricas financieras, por canales y arqueo de caja para una fecha dada.
     */
    protected function calculateDailyMetrics(?string $dateInput): array
    {
        $date = $dateInput ?: now()->timezone('America/Bogota')->format('Y-m-d');

        $startOfDay = Carbon::createFromFormat('Y-m-d', $date, 'America/Bogota')->startOfDay();
        $endOfDay = Carbon::createFromFormat('Y-m-d', $date, 'America/Bogota')->endOfDay();

        $sales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->with(['payments', 'details.product', 'user'])
            ->get();

        $cancelledSales = Sale::where('status', 'cancelled')
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->get();
        $cancelledSalesCount = $cancelledSales->count();
        $cancelledSalesTotal = (float) $cancelledSales->sum('total');

        // 1. Ventas a Crédito del Día vs Ventas Directas
        $creditSales = $sales->where('is_credit', true);
        $creditSalesCount = $creditSales->count();
        $creditSalesTotal = (float) $creditSales->sum('total');

        $nonCreditSales = $sales->where('is_credit', false);

        // 2. Recaudos de Cartera / Cobros de Crédito recibidos hoy
        $creditCollections = \App\Models\CustomerCreditPayment::whereBetween('paid_at', [$startOfDay, $endOfDay])
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed'))
            ->with(['sale', 'customer', 'user'])
            ->get();
        $creditCollectionsCount = $creditCollections->count();
        $creditCollectionsTotal = (float) $creditCollections->sum('amount');

        // 3. Gastos y Facturas de la Fecha (Trazabilidad Contable Integral)
        $paidExpenses = Expense::where('status', 'paid')
            ->where(function ($q) use ($date) {
                $q->whereDate('paid_at', $date)
                  ->orWhere(function ($q2) use ($date) {
                      $q2->whereNull('paid_at')->whereDate('issue_date', $date);
                  });
            })
            ->with(['supplier', 'creator'])
            ->orderByDesc('id')
            ->get();

        $pendingExpenses = Expense::where('status', 'pending')
            ->whereDate('issue_date', $date)
            ->with(['supplier', 'creator'])
            ->orderByDesc('id')
            ->get();

        $matchesExpenseMethod = function ($expense, array $methods): bool {
            $val = $expense->payment_method instanceof \BackedEnum
                ? $expense->payment_method->value
                : (string) $expense->payment_method;
            return in_array(strtolower($val), array_map('strtolower', $methods), true);
        };

        $expensesCashTotal = (float) $paidExpenses->filter(fn ($e) => $matchesExpenseMethod($e, ['cash']))->sum('amount');
        $expensesTransferTotal = (float) $paidExpenses->filter(fn ($e) => $matchesExpenseMethod($e, ['transfer', 'nequi', 'daviplata']))->sum('amount');
        $expensesCardOtherTotal = (float) $paidExpenses->filter(fn ($e) => $matchesExpenseMethod($e, ['card', 'other']))->sum('amount');
        $totalExpensesPaidToday = (float) $paidExpenses->sum('amount');
        $totalExpensesPendingToday = (float) $pendingExpenses->sum('amount');
        $expensesToday = $paidExpenses->concat($pendingExpenses);

        // 4. Métricas Consolidadas de Facturación
        $grossSales = (float) $sales->sum('subtotal');
        $discounts = (float) $sales->sum('discount');
        $tipsTotal = (float) $sales->sum('tip');
        $serviceTips = (float) $sales->sum(fn ($s) => (float) $s->tip + (float) $s->service_fee);
        $taxInc = (float) $sales->sum('tax_inc');
        $deliveryFees = (float) $sales->sum('delivery_fee');
        $netTotalBilled = (float) $sales->sum('total');
        $salesCount = $sales->count();

        // 5. Canales de Venta
        $tableSales = $sales->where('order_type', 'table');
        $deliverySales = $sales->where('order_type', 'delivery');
        $takeoutSales = $sales->where('order_type', 'takeout');
        $retailSales = $sales->filter(fn ($s) => empty($s->order_type) || $s->order_type === 'retail');

        $tableTotal = (float) $tableSales->sum('total');
        $deliveryTotal = (float) $deliverySales->sum('total');
        $deliveryFeesTotal = (float) $deliverySales->sum('delivery_fee');
        $takeoutTotal = (float) $takeoutSales->sum('total');
        $retailTotal = (float) $retailSales->sum('total');

        // 6. Arqueo y Medios de Pago (Ventas Directas + Recaudos de Cartera)
        $directPayments = $nonCreditSales->flatMap->payments;

        $matchesMethod = function ($payment, string $method): bool {
            $val = $payment->method instanceof \BackedEnum ? $payment->method->value : (string) $payment->method;
            return strtolower($val) === strtolower($method);
        };

        $directCash = $directPayments->filter(fn ($p) => $matchesMethod($p, 'cash'));
        $creditCash = $creditCollections->filter(fn ($p) => $matchesMethod($p, 'cash'));

        $cashAmount = (float) $directCash->sum('amount') + (float) $creditCash->sum('amount');
        $cashReceived = (float) $directCash->sum(fn ($p) => (float) ($p->cash_received ?? $p->amount))
            + (float) $creditCash->sum(fn ($p) => (float) ($p->cash_received ?? $p->amount));
        $changeGiven = (float) $directCash->sum(fn ($p) => (float) ($p->change_given ?? 0))
            + (float) $creditCash->sum(fn ($p) => (float) ($p->change_given ?? 0));

        // Impacto Real en Gaveta y Balance Neto
        $grossCashInDrawer = $cashAmount;
        $netCashInDrawer = $grossCashInDrawer - $expensesCashTotal;
        $cashInDrawer = $netCashInDrawer; // Efectivo neto esperado en gaveta

        $transferAmount = (float) $directPayments->filter(fn ($p) => $matchesMethod($p, 'transfer'))->sum('amount')
            + (float) $creditCollections->filter(fn ($p) => $matchesMethod($p, 'transfer'))->sum('amount');

        $cardAmount = (float) $directPayments->filter(fn ($p) => $matchesMethod($p, 'card'))->sum('amount')
            + (float) $creditCollections->filter(fn ($p) => $matchesMethod($p, 'card'))->sum('amount');

        $otherAmount = (float) $directPayments->filter(fn ($p) => $matchesMethod($p, 'other'))->sum('amount')
            + (float) $creditCollections->filter(fn ($p) => $matchesMethod($p, 'other'))->sum('amount');

        $digitalTotal = $transferAmount + $cardAmount + $otherAmount;
        $totalCollected = $cashAmount + $digitalTotal;
        $netCashBalance = $totalCollected - $totalExpensesPaidToday;

        return compact(
            'date',
            'sales',
            'salesCount',
            'cancelledSalesCount',
            'cancelledSalesTotal',
            'creditSales',
            'creditSalesCount',
            'creditSalesTotal',
            'creditCollections',
            'creditCollectionsCount',
            'creditCollectionsTotal',
            'paidExpenses',
            'pendingExpenses',
            'expensesToday',
            'expensesCashTotal',
            'expensesTransferTotal',
            'expensesCardOtherTotal',
            'totalExpensesPaidToday',
            'totalExpensesPendingToday',
            'grossCashInDrawer',
            'netCashInDrawer',
            'netCashBalance',
            'grossSales',
            'discounts',
            'tipsTotal',
            'serviceTips',
            'taxInc',
            'deliveryFees',
            'netTotalBilled',
            'tableSales',
            'tableTotal',
            'deliverySales',
            'deliveryTotal',
            'deliveryFeesTotal',
            'takeoutSales',
            'takeoutTotal',
            'retailSales',
            'retailTotal',
            'cashAmount',
            'cashReceived',
            'changeGiven',
            'cashInDrawer',
            'transferAmount',
            'cardAmount',
            'otherAmount',
            'digitalTotal',
            'totalCollected'
        );
    }
}
