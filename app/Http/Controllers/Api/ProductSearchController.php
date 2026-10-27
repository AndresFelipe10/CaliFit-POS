<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    /**
     * Search products by name or SKU.
     * Used by the POS sale form for autocomplete.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $rawSearch = trim((string) $request->input('q', ''));

        $query = Product::where('activo', true);

        if ($rawSearch !== '') {
            $escaped = addcslashes($rawSearch, '%_\\');

            $query->where(function ($q) use ($escaped, $rawSearch) {
                $q->where('sku', $rawSearch)
                  ->orWhere('nombre', 'like', "%{$escaped}%")
                  ->orWhere('sku', 'like', "%{$escaped}%");
            });
        }

        $products = $query->select(['id', 'nombre', 'sku', 'precio_venta', 'stock', 'tipo_producto', 'unidad_base', 'activo'])
            ->orderBy('nombre')
            ->limit(15)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'name' => $p->nombre,
                'sku' => $p->sku,
                'precio_venta' => (float) $p->precio_venta,
                'sale_price' => (float) $p->precio_venta,
                'stock' => (float) $p->stock,
                'tipo_producto' => $p->tipo_producto,
                'unidad_base' => $p->unidad_base,
                'activo' => (bool) $p->activo,
                'is_active' => (bool) $p->activo,
            ]);

        return response()->json($products)
            ->header('Cache-Control', 'private, max-age=5');
    }
}
