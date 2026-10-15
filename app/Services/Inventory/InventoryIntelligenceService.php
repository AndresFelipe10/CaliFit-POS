<?php

namespace App\Services\Inventory;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

class InventoryIntelligenceService
{
    public const STATUS_OUT_OF_STOCK = 'out_of_stock';
    public const STATUS_LOW_STOCK = 'low_stock';
    public const STATUS_NORMAL = 'normal';

    /**
     * Deterministically classify inventory status based on stock and min_stock.
     *
     * @param float|int $stock
     * @param float|int $minStock
     * @return string
     */
    public function classify(float|int $stock, float|int $minStock): string
    {
        if ($stock <= 0) {
            return self::STATUS_OUT_OF_STOCK;
        }

        if ($stock <= $minStock) {
            return self::STATUS_LOW_STOCK;
        }

        return self::STATUS_NORMAL;
    }

    /**
     * Calculate replenishment recommendations and estimated financial cost.
     *
     * @param Product $product
     * @return array
     */
    public function calculateReplenishment(Product $product): array
    {
        $stock = (float) $product->stock;
        $minStock = (float) $product->min_stock;
        $costPrice = (float) $product->cost_price;

        $status = $this->classify($stock, $minStock);

        if ($status === self::STATUS_NORMAL) {
            return [
                'status' => $status,
                'deficit' => 0,
                'suggested_qty' => 0,
                'estimated_cost' => 0.0,
            ];
        }

        $deficit = round(max(0, $minStock - $stock), 3);
        $suggestedQty = round(max($minStock, ($minStock * 2) - $stock), 3);

        if (fmod($deficit, 1.0) === 0.0) {
            $deficit = (int) $deficit;
        }

        if (fmod($suggestedQty, 1.0) === 0.0) {
            $suggestedQty = (int) $suggestedQty;
        }

        $estimatedCost = round($suggestedQty * $costPrice, 2);

        return [
            'status' => $status,
            'deficit' => $deficit,
            'suggested_qty' => $suggestedQty,
            'estimated_cost' => $estimatedCost,
        ];
    }

    /**
     * Analyze tenant inventory and return products with intelligence metrics and summary KPIs.
     *
     * @param int $businessId
     * @param array $filters
     * @return array
     */
    public function getInventoryAnalysis(int $businessId, array $filters = []): array
    {
        $query = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->with('category');

        if (!empty($filters['search'])) {
            $escaped = addcslashes(trim((string) $filters['search']), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('sku', 'like', "%{$escaped}%");
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        $allProducts = $query->get();

        $outOfStockCount = 0;
        $lowStockCount = 0;
        $normalCount = 0;
        $totalReplenishmentCost = 0.0;

        $enrichedProducts = $allProducts->map(function (Product $product) use (
            &$outOfStockCount,
            &$lowStockCount,
            &$normalCount,
            &$totalReplenishmentCost
        ) {
            $replenishment = $this->calculateReplenishment($product);

            if ($replenishment['status'] === self::STATUS_OUT_OF_STOCK) {
                $outOfStockCount++;
                $totalReplenishmentCost += $replenishment['estimated_cost'];
            } elseif ($replenishment['status'] === self::STATUS_LOW_STOCK) {
                $lowStockCount++;
                $totalReplenishmentCost += $replenishment['estimated_cost'];
            } else {
                $normalCount++;
            }

            $product->inventory_status = $replenishment['status'];
            $product->deficit = $replenishment['deficit'];
            $product->suggested_qty = $replenishment['suggested_qty'];
            $product->estimated_replenishment_cost = $replenishment['estimated_cost'];

            return $product;
        });

        // Filtrar por status si fue solicitado
        $statusFilter = $filters['status'] ?? 'all';
        if ($statusFilter === 'out_of_stock') {
            $enrichedProducts = $enrichedProducts->where('inventory_status', self::STATUS_OUT_OF_STOCK);
        } elseif ($statusFilter === 'low_stock') {
            $enrichedProducts = $enrichedProducts->where('inventory_status', self::STATUS_LOW_STOCK);
        } elseif ($statusFilter === 'normal') {
            $enrichedProducts = $enrichedProducts->where('inventory_status', self::STATUS_NORMAL);
        } elseif ($statusFilter === 'alert') {
            $enrichedProducts = $enrichedProducts->whereIn('inventory_status', [
                self::STATUS_OUT_OF_STOCK,
                self::STATUS_LOW_STOCK,
            ]);
        }

        // Ordenar por prioridad: Agotados primero, luego Bajo Stock, luego Normal; y por menor stock relativo
        $statusWeight = [
            self::STATUS_OUT_OF_STOCK => 1,
            self::STATUS_LOW_STOCK => 2,
            self::STATUS_NORMAL => 3,
        ];

        $sortedProducts = $enrichedProducts->sortBy(function ($product) use ($statusWeight) {
            return sprintf(
                '%d-%08d-%s',
                $statusWeight[$product->inventory_status] ?? 9,
                (int) round(max(0, (float) $product->stock) * 1000),
                $product->name
            );
        })->values();

        $categories = Category::where('business_id', $businessId)->orderBy('name')->get();

        return [
            'products' => $sortedProducts,
            'categories' => $categories,
            'total_count' => $allProducts->count(),
            'out_of_stock_count' => $outOfStockCount,
            'low_stock_count' => $lowStockCount,
            'normal_count' => $normalCount,
            'total_replenishment_cost' => $totalReplenishmentCost,
            'filters' => [
                'status' => $statusFilter,
                'search' => $filters['search'] ?? '',
                'category_id' => $filters['category_id'] ?? '',
            ],
        ];
    }
}
