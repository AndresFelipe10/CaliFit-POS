<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Product;

class InventoryAiTools
{
    /**
     * Get kitchen raw materials (INSUMO) with low stock (read-only).
     */
    public function lowStockIngredients(): AiQueryResult
    {
        $ingredients = Product::where('tipo_producto', 'INSUMO')
            ->where('activo', true)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->where('stock', '>', 0)
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();

        if ($ingredients->isEmpty()) {
            return new AiQueryResult(
                intent: 'low_stock_ingredients',
                summary: '¡Excelente noticia! No hay insumos de cocina con bajo stock en este momento en SaborCaliFit.',
                data: []
            );
        }

        $data = $ingredients->map(fn($p) => [
            'Insumo' => $p->nombre,
            'Código SKU' => $p->sku,
            'Existencias' => (float) $p->stock . ' ' . strtolower($p->unidad_base),
            'Stock Mínimo' => (float) $p->stock_minimo . ' ' . strtolower($p->unidad_base),
            'Déficit' => max(0, (float)$p->stock_minimo - (float)$p->stock) . ' ' . strtolower($p->unidad_base),
        ])->toArray();

        return new AiQueryResult(
            intent: 'low_stock_ingredients',
            summary: "Tienes {$ingredients->count()} insumos de cocina con existencias iguales o inferiores a su stock mínimo de reposición.",
            data: $data
        );
    }

    /**
     * Get kitchen raw materials (INSUMO) completely out of stock (read-only).
     */
    public function outOfStockIngredients(): AiQueryResult
    {
        $ingredients = Product::where('tipo_producto', 'INSUMO')
            ->where('activo', true)
            ->where('stock', '<=', 0)
            ->orderBy('nombre', 'asc')
            ->limit(10)
            ->get();

        if ($ingredients->isEmpty()) {
            return new AiQueryResult(
                intent: 'out_of_stock_ingredients',
                summary: 'Todos los insumos de cocina tienen existencias disponibles.',
                data: []
            );
        }

        $data = $ingredients->map(fn($p) => [
            'Insumo' => $p->nombre,
            'Código SKU' => $p->sku,
            'Unidad Base' => $p->unidad_base,
            'Estado' => 'Agotado',
        ])->toArray();

        return new AiQueryResult(
            intent: 'out_of_stock_ingredients',
            summary: "Alerta crítica: Hay {$ingredients->count()} insumos de cocina totalmente agotados (stock 0).",
            data: $data
        );
    }

    /**
     * Get sports supplements with low stock (units) (read-only).
     */
    public function lowStockSupplements(): AiQueryResult
    {
        $supplements = Product::where('tipo_producto', 'SUPLEMENTO')
            ->where('activo', true)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->where('stock', '>', 0)
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();

        if ($supplements->isEmpty()) {
            return new AiQueryResult(
                intent: 'low_stock_supplements',
                summary: 'No hay suplementos deportivos con bajo stock actualmente.',
                data: []
            );
        }

        $data = $supplements->map(fn($p) => [
            'Suplemento' => $p->nombre,
            'Código SKU' => $p->sku,
            'Existencias' => (int) $p->stock . ' unidades',
            'Stock Mínimo' => (int) $p->stock_minimo . ' unidades',
        ])->toArray();

        return new AiQueryResult(
            intent: 'low_stock_supplements',
            summary: "Tienes {$supplements->count()} suplementos deportivos cerca de su umbral mínimo de seguridad.",
            data: $data
        );
    }

    /**
     * Get sports supplements completely out of stock (units) (read-only).
     */
    public function outOfStockSupplements(): AiQueryResult
    {
        $supplements = Product::where('tipo_producto', 'SUPLEMENTO')
            ->where('activo', true)
            ->where('stock', '<=', 0)
            ->orderBy('nombre', 'asc')
            ->limit(10)
            ->get();

        if ($supplements->isEmpty()) {
            return new AiQueryResult(
                intent: 'out_of_stock_supplements',
                summary: 'No hay suplementos deportivos agotados en el mostrador.',
                data: []
            );
        }

        $data = $supplements->map(fn($p) => [
            'Suplemento' => $p->nombre,
            'Código SKU' => $p->sku,
            'Estado' => 'Agotado',
        ])->toArray();

        return new AiQueryResult(
            intent: 'out_of_stock_supplements',
            summary: "Hay {$supplements->count()} suplementos deportivos agotados en mostrador.",
            data: $data
        );
    }

    /**
     * Generic low stock query (read-only).
     */
    public function lowStock(): AiQueryResult
    {
        $products = Product::where('activo', true)
            ->whereIn('tipo_producto', ['INSUMO', 'SUPLEMENTO'])
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->where('stock', '>', 0)
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            return new AiQueryResult(
                intent: 'low_stock_products',
                summary: 'No hay productos con bajo stock en este momento.',
                data: []
            );
        }

        $data = $products->map(fn($p) => [
            'Producto' => $p->nombre,
            'Código SKU' => $p->sku,
            'Tipo' => $p->tipo_producto,
            'Existencias' => $p->formatted_stock . ' ' . strtolower($p->unidad_base),
            'Stock Mínimo' => (float)$p->stock_minimo . ' ' . strtolower($p->unidad_base),
        ])->toArray();

        return new AiQueryResult(
            intent: 'low_stock_products',
            summary: "Tienes {$products->count()} productos (insumos y suplementos) con existencias iguales o inferiores a su stock mínimo.",
            data: $data
        );
    }

    /**
     * Generic out of stock query (read-only).
     */
    public function outOfStock(): AiQueryResult
    {
        $products = Product::where('activo', true)
            ->whereIn('tipo_producto', ['INSUMO', 'SUPLEMENTO'])
            ->where('stock', '<=', 0)
            ->orderBy('nombre', 'asc')
            ->limit(10)
            ->get();

        if ($products->isEmpty()) {
            return new AiQueryResult(
                intent: 'out_of_stock_products',
                summary: 'No hay productos agotados en inventario.',
                data: []
            );
        }

        $data = $products->map(fn($p) => [
            'Producto' => $p->nombre,
            'Código SKU' => $p->sku,
            'Tipo' => $p->tipo_producto,
            'Estado' => 'Agotado',
        ])->toArray();

        return new AiQueryResult(
            intent: 'out_of_stock_products',
            summary: "Hay {$products->count()} productos agotados en inventario.",
            data: $data
        );
    }

    /**
     * Summary and valuation of inventory (read-only).
     */
    public function summary(): AiQueryResult
    {
        $insumosCount = Product::where('tipo_producto', 'INSUMO')->where('activo', true)->count();
        $suplementosCount = Product::where('tipo_producto', 'SUPLEMENTO')->where('activo', true)->count();
        $platosCount = Product::where('tipo_producto', 'PLATO')->where('activo', true)->count();

        // Valoración estimada al costo de suplementos e insumos
        $totalCostValuation = Product::where('activo', true)
            ->whereIn('tipo_producto', ['SUPLEMENTO', 'INSUMO'])
            ->selectRaw('SUM(stock * precio_compra) as total_val')
            ->value('total_val') ?? 0;

        $totalSaleValuation = Product::where('activo', true)
            ->where('tipo_producto', 'SUPLEMENTO')
            ->selectRaw('SUM(stock * precio_venta) as total_pvp')
            ->value('total_pvp') ?? 0;

        return new AiQueryResult(
            intent: 'inventory_summary',
            summary: "SaborCaliFit cuenta con {$insumosCount} insumos de cocina, {$suplementosCount} suplementos deportivos y {$platosCount} platos en carta. La valoración estimada del inventario al costo es de $" . number_format($totalCostValuation, 0, ',', '.') . " COP.",
            data: [
                'Insumos de Cocina' => $insumosCount,
                'Suplementos Deportivos' => $suplementosCount,
                'Platos en Carta' => $platosCount,
                'Valoración al Costo' => '$' . number_format($totalCostValuation, 0, ',', '.') . ' COP',
                'Valoración PVP Suplementos' => '$' . number_format($totalSaleValuation, 0, ',', '.') . ' COP',
            ]
        );
    }
}
