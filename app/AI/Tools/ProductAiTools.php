<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Product;

class ProductAiTools
{
    /**
     * List products in catalog (read-only).
     */
    public function list(array $filters = []): AiQueryResult
    {
        $query = Product::where('activo', true)->with('category');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('tipo_producto')->orderBy('nombre')->limit(15)->get();

        if ($products->isEmpty()) {
            return new AiQueryResult(
                intent: 'list_products',
                summary: 'No se encontraron productos activos con los criterios solicitados.',
                data: []
            );
        }

        $data = $products->map(fn($p) => [
            'Nombre' => $p->nombre,
            'Código SKU' => $p->sku,
            'Tipo' => $p->tipo_producto,
            'Categoría' => $p->category ? $p->category->name : 'Sin categoría',
            'Existencias' => $p->formatted_stock . ' ' . strtolower($p->unidad_base),
            'Precio de Venta' => '$' . number_format($p->precio_venta, 0, ',', '.') . ' COP',
        ])->toArray();

        return new AiQueryResult(
            intent: 'list_products',
            summary: "Se encontraron {$products->count()} productos activos en el catálogo de SaborCaliFit.",
            data: $data
        );
    }
}
