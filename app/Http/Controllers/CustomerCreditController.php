<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCreditPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerCreditPayment;
use App\Models\Sale;
use App\Services\Credit\CustomerCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerCreditController extends Controller
{
    public function __construct(
        protected CustomerCreditService $creditService
    ) {}

    /**
     * Muestra el panel general de Cartera y Cuentas por Cobrar (SaborCaliFit).
     */
    public function index(Request $request): View
    {
        // 1. Métricas de Cartera Global vía agregaciones directas SQL (< 10 ms)
        $totalCreditGranted = (float) Sale::where('is_credit', true)->where('status', 'completed')->sum('total');
        $totalCollected = (float) CustomerCreditPayment::whereHas('sale', fn ($q) => $q->where('status', 'completed'))->sum('amount');
        $totalReceivable = max(0.0, $totalCreditGranted - $totalCollected);
        $totalPending = $totalReceivable;

        // 2. Facturas de crédito pendientes de cobro
        $query = Sale::where('is_credit', true)
            ->where('status', 'completed')
            ->where('credit_status', 'pending')
            ->with(['customer', 'creditPayments']);

        $selectedCustomerId = $request->input('customer_id');
        if ($selectedCustomerId) {
            $query->where('customer_id', $selectedCustomerId);
        }

        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_document', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('document', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        $pendingSales = $query->latest('created_at')->paginate(15)->withQueryString();
        $creditSales = $pendingSales;
        $pendingCount = $pendingSales->total();

        // 3. Clientes con deuda activa (para selector y vista agrupada)
        $customersWithCredit = Customer::whereHas('sales', function ($q) {
            $q->where('is_credit', true)
              ->where('status', 'completed')
              ->where('credit_status', 'pending');
        })->with(['sales' => function ($q) {
            $q->where('is_credit', true)
              ->where('status', 'completed')
              ->where('credit_status', 'pending')
              ->with('creditPayments')
              ->latest('created_at');
        }])->get();

        // Lista de clientes agrupados filtrada según criterios si aplica
        $groupedCustomers = $customersWithCredit;
        if ($selectedCustomerId) {
            $groupedCustomers = $groupedCustomers->where('id', (int) $selectedCustomerId);
        } elseif ($search) {
            $searchLower = mb_strtolower($search);
            $groupedCustomers = $groupedCustomers->filter(function ($customer) use ($searchLower) {
                return str_contains(mb_strtolower($customer->name), $searchLower)
                    || str_contains(mb_strtolower($customer->document ?? ''), $searchLower)
                    || str_contains(mb_strtolower($customer->phone ?? ''), $searchLower)
                    || $customer->sales->contains(fn ($s) => str_contains(mb_strtolower($s->invoice_number), $searchLower));
            });
        }

        $selectedCustomer = $selectedCustomerId ? Customer::find($selectedCustomerId) : null;
        $activeTab = $request->input('tab', 'grouped');

        return view('customers.credit.index', compact(
            'pendingSales',
            'creditSales',
            'totalReceivable',
            'totalPending',
            'totalCreditGranted',
            'totalCollected',
            'customersWithCredit',
            'groupedCustomers',
            'selectedCustomer',
            'pendingCount',
            'activeTab'
        ));
    }

    /**
     * Muestra la cartera detallada de un cliente específico.
     */
    public function customerCredit(Customer $customer): View
    {
        $sales = $this->creditService->getCustomerCreditSales($customer);
        $creditSales = $sales;
        $balance = $this->creditService->getCustomerBalance($customer);
        $totalPending = $balance;

        return view('customers.credit.show', compact('customer', 'sales', 'creditSales', 'balance', 'totalPending'));
    }

    /**
     * Registra un abono o cancelación total a una factura a crédito.
     */
    public function storePayment(StoreCreditPaymentRequest $request, ?Sale $sale = null): JsonResponse|RedirectResponse
    {
        $targetSale = ($sale && $sale->exists) 
            ? $sale 
            : Sale::findOrFail($request->validated('sale_id'));

        $creditPayment = $this->creditService->registerPayment(
            $targetSale,
            $request->validated(),
            auth()->user()
        );

        $freshSale = $targetSale->fresh();
        $amountFmt = number_format($creditPayment->amount, 0, ',', '.');
        $message = "Abono por \${$amountFmt} a la factura {$freshSale->invoice_number} registrado correctamente.";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'sale_id' => $freshSale->id,
                'invoice_number' => $freshSale->invoice_number,
                'payment_id' => $creditPayment->id,
                'amount' => (float) $creditPayment->amount,
                'pending_balance' => (float) $freshSale->credit_pending_amount,
                'credit_status' => $freshSale->credit_status,
                'print_url' => route('prints.sales.receipt', $freshSale),
            ]);
        }

        return back()->with('success', $message);
    }
}
