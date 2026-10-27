<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientInventoryException;
use App\Http\Requests\StoreInventoryAdjustmentRequest;
use App\Http\Requests\StoreInventoryEntryRequest;
use App\Http\Requests\StoreInventoryWasteRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Muestra el panel integral de inventario con alertas determinísticas y catálogo clasificado.
     */
    public function index(Request $request): View
    {
        $products = Product::where('activo', true)
            ->with('category')
            ->orderBy('tipo_producto')
            ->orderBy('nombre')
            ->get();

        // Clasificación de estado y reposición sugerida
        $products->each(function ($p) {
            $stock = (float) $p->stock;
            $min = (float) $p->stock_minimo;

            if ($stock <= 0) {
                $p->status_stock = 'agotado';
                $p->status_label = 'Agotado';
                $p->status_class = 'bg-danger text-white';
            } elseif ($min > 0 && $stock <= $min) {
                $p->status_stock = 'bajo';
                $p->status_label = 'Bajo Stock';
                $p->status_class = 'bg-warning text-dark';
            } else {
                $p->status_stock = 'normal';
                $p->status_label = 'Normal';
                $p->status_class = 'bg-success text-white';
            }

            $p->sugerido_reposicion = max(0, round(($min * 1.5) - $stock, 3));
        });

        $insumos = $products->filter(fn ($p) => $p->tipo_producto === 'INSUMO')->values();
        $suplementos = $products->filter(fn ($p) => $p->tipo_producto === 'SUPLEMENTO')->values();
        $platos = $products->filter(fn ($p) => $p->tipo_producto === 'PLATO')->values();
        $preparaciones = $products->filter(fn ($p) => $p->tipo_producto === 'PREPARACION')->values();

        // Métricas de cabecera
        $lowStockCount = $products->where('status_stock', 'bajo')->count();
        $outOfStockCount = $products->where('status_stock', 'agotado')->count();
        $totalEstimatedValue = $products->sum(fn ($p) => (float) $p->stock * (float) $p->precio_compra);

        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('inventory.index', compact(
            'products',
            'insumos',
            'suplementos',
            'platos',
            'preparaciones',
            'lowStockCount',
            'outOfStockCount',
            'totalEstimatedValue',
            'suppliers'
        ));
    }

    /**
     * Muestra el historial cronológico y trazabilidad de movimientos de inventario.
     */
    public function movements(Request $request): View
    {
        $query = InventoryMovement::with(['product', 'user']);

        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $movements = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $products = Product::orderBy('nombre')->get();

        return view('inventory.movements', compact('movements', 'products', 'productId', 'type', 'dateFrom', 'dateTo'));
    }

    /**
     * Registra una entrada por compra con conversión automática oficial.
     */
    public function storeEntry(StoreInventoryEntryRequest $request): RedirectResponse
    {
        $product = Product::findOrFail($request->product_id);

        $convertedQuantity = $this->inventoryService->convertPurchaseUnit(
            (float) $request->purchase_quantity,
            $request->purchase_unit
        );

        $reason = $request->notes ?? "Entrada por compra ({$request->purchase_quantity} {$request->purchase_unit})";
        if ($request->invoice_number) {
            $reason .= " - Factura/Remisión: {$request->invoice_number}";
        }

        $this->inventoryService->registerMovement(
            product: $product,
            quantity: $convertedQuantity,
            type: InventoryMovement::TYPE_ENTRADA_COMPRA,
            reason: $reason,
            reference: null,
            user: auth()->user()
        );

        if ($request->cost_price !== null && (float) $request->cost_price > 0) {
            $product->update([
                'precio_compra' => round((float) $request->cost_price, 2),
            ]);
        }

        return redirect()->route('inventory.index')
            ->with('success', "Se registraron {$convertedQuantity} {$product->unidad_base} de {$product->nombre} en inventario exitosamente.");
    }

    /**
     * Registra una merma o pérdida de alimentos/insumos con trazabilidad estricta.
     */
    public function storeWaste(StoreInventoryWasteRequest $request): RedirectResponse
    {
        $product = Product::findOrFail($request->product_id);

        try {
            $this->inventoryService->registerMovement(
                product: $product,
                quantity: (float) $request->quantity,
                type: InventoryMovement::TYPE_MERMA,
                reason: $request->reason,
                reference: null,
                user: auth()->user()
            );

            return redirect()->route('inventory.index')
                ->with('success', "Merma de {$request->quantity} {$product->unidad_base} registrada para {$product->nombre}.");
        } catch (InsufficientInventoryException $e) {
            return back()->with('error', "No hay suficiente stock disponible para registrar esta merma: {$e->getMessage()}");
        }
    }

    /**
     * Registra un ajuste de inventario por conteo físico real.
     */
    public function storeAdjustment(StoreInventoryAdjustmentRequest $request): RedirectResponse
    {
        $product = Product::findOrFail($request->product_id);
        $diff = round((float) $request->real_stock - (float) $product->stock, 3);

        try {
            $this->inventoryService->registerMovement(
                product: $product,
                quantity: $diff,
                type: InventoryMovement::TYPE_AJUSTE,
                reason: $request->reason,
                reference: null,
                user: auth()->user()
            );

            return redirect()->route('inventory.index')
                ->with('success', "Ajuste físico aplicado a {$product->nombre}. Stock actualizado a {$request->real_stock} {$product->unidad_base}.");
        } catch (InsufficientInventoryException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
