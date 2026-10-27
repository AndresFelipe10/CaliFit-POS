<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessSaleRequest;
use App\Models\Addition;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Muestra la interfaz de Punto de Venta (POS) con catálogo clasificado.
     */
    public function index(): View
    {
        $products = Product::where('activo', true)
            ->with(['adiciones' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('nombre')
            ->get();

        $platos = $products->filter(fn ($p) => $p->isDish() || $p->tipo_producto === 'PLATO')->values();
        $suplementos = $products->filter(fn ($p) => $p->isSupplement() || $p->tipo_producto === 'SUPLEMENTO')->values();
        $adiciones = Addition::where('activo', true)->orderBy('nombre')->get();
        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        $defaultCustomer = [
            'name' => 'CONSUMIDOR FINAL',
            'document' => '222222222222',
        ];

        return view('pos.index', compact('products', 'platos', 'suplementos', 'adiciones', 'customers', 'defaultCustomer'));
    }

    /**
     * Procesa una venta directa o mixta desde el POS.
     */
    public function store(ProcessSaleRequest $request): JsonResponse
    {
        $sale = $this->saleService->processSale($request->validated(), auth()->user());

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'message' => 'Venta registrada con éxito',
        ], 201);
    }
}
