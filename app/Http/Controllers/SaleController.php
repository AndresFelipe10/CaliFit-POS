<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\Sales\InvalidSaleItemException;
use App\Exceptions\Sales\InvalidSalePaymentException;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Redirige al módulo unificado de Reportes en la sección de Ventas de CaliFit.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('reports.sales');
    }

    /**
     * Show the form for creating a new sale (POS).
     */
    public function create(): View
    {
        Gate::authorize('create', Sale::class);

        $selectedCustomer = null;
        if (old('customer_id')) {
            $selectedCustomer = Customer::find(old('customer_id'));
        }

        $products = Product::where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('sales.create', [
            'defaultCustomerName' => config('sales.default_customer_name', 'CONSUMIDOR FINAL'),
            'defaultCustomerDocument' => config('sales.default_customer_document', '222222222222'),
            'selectedCustomer' => $selectedCustomer,
            'paymentMethods' => PaymentMethod::cases(),
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created sale in storage.
     */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->saleService->processSale(
                $request->validated(),
                $request->user()
            );

            return redirect()->route('sales.show', $sale)
                ->with('status', "Venta {$sale->invoice_number} registrada exitosamente.");
        } catch (\App\Exceptions\InsufficientStockException|\App\Exceptions\InsufficientInventoryException|InvalidSaleItemException $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['items' => $e->getMessage()]);
        } catch (\InvalidArgumentException|InvalidSalePaymentException $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['payments' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified sale (invoice/receipt).
     */
    public function show(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        $sale->load(['details.product', 'customer', 'user', 'payments']);

        return view('sales.show', [
            'sale' => $sale,
        ]);
    }

    /**
     * Print invoice format (Letter/A4).
     */
    public function printInvoice(Sale $sale): View
    {
        Gate::authorize('view', $sale);
        return app(SalePrintController::class)->receipt($sale);
    }

    /**
     * Print receipt format (thermal 80mm).
     */
    public function printReceipt(Sale $sale): View
    {
        Gate::authorize('view', $sale);
        return app(SalePrintController::class)->receipt($sale);
    }

    /**
     * Cancel a sale and restore stock (admin only).
     */
    public function destroy(Sale $sale, Request $request): RedirectResponse
    {
        Gate::authorize('delete', $sale);

        if ($sale->status === 'cancelled') {
            return redirect()->route('reports.sales')
                ->with('info', "La venta {$sale->invoice_number} ya se encuentra anulada.");
        }

        $this->saleService->cancelSale($sale, $request->user(), 'Anulación manual solicitada');

        return redirect()->route('reports.sales')
            ->with('status', "Venta {$sale->invoice_number} anulada correctamente. Stock restaurado.");
    }
}
