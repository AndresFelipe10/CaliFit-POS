<?php

namespace App\Services\Kitchen;

use App\Models\Addition;
use App\Models\Product;
use RuntimeException;

class RecipeService
{
    /**
     * Calcula los insumos consumidos por un ítem de venta, considerando
     * su receta base y cualquier adición con precio seleccionada.
     *
     * @param  Product  $product
     * @param  float    $quantity
     * @param  array    $selectedAdditionIdsWithQuantities Mapa de [adicionId => cantidad] o lista de adiciones
     * @return array{consolidated_ingredients: array<int, float>, breakdown: array<int, array<string, mixed>>}
     */
    public function calculateItemConsumption(
        Product $product,
        float $quantity,
        array $selectedAdditionIdsWithQuantities = []
    ): array {
        $quantity = round((float) $quantity, 3);
        $consolidated = [];
        $breakdown = [];

        // 1. Suplementos o Insumos de venta directa
        if ($product->isSupplement() || $product->isRawMaterial()) {
            $consolidated[$product->id] = $quantity;
            $breakdown[] = [
                'product_id' => $product->id,
                'name' => $product->nombre,
                'quantity' => $quantity,
                'unit' => $product->unidad_base,
                'source' => 'DIRECT',
            ];

            return [
                'consolidated_ingredients' => $consolidated,
                'breakdown' => $breakdown,
            ];
        }

        // 2. Platos o Preparaciones compuestas con receta
        $recipe = $product->activeRecipe()->with('items.ingrediente')->first()
            ?? $product->receta()->with('items.ingrediente')->first();

        if ($recipe === null) {
            throw new RuntimeException("El plato o preparación '{$product->nombre}' (ID #{$product->id}) no tiene una receta activa configurada.");
        }

        // 2.1 Calcular consumo de la receta base
        foreach ($recipe->items as $item) {
            $consumoBase = round((float) $item->cantidad_por_porcion * $quantity, 3);
            $ingredienteId = (int) $item->ingrediente_id;

            $consolidated[$ingredienteId] = round(($consolidated[$ingredienteId] ?? 0.0) + $consumoBase, 3);
            $breakdown[] = [
                'product_id' => $ingredienteId,
                'name' => $item->ingrediente?->nombre ?? "Insumo #{$ingredienteId}",
                'quantity' => $consumoBase,
                'unit' => $item->unidad ?? 'GRAMO',
                'source' => 'RECIPE_BASE',
            ];
        }

        // 2.2 Normalizar y procesar adiciones seleccionadas
        $normalizedAdditions = $this->normalizeAdditionsInput($selectedAdditionIdsWithQuantities);

        if (! empty($normalizedAdditions)) {
            $additionIds = array_keys($normalizedAdditions);
            $additions = Addition::whereIn('id', $additionIds)
                ->with('items.ingrediente')
                ->get()
                ->keyBy('id');

            foreach ($normalizedAdditions as $additionId => $additionQty) {
                /** @var Addition|null $addition */
                $addition = $additions->get($additionId);

                if ($addition === null) {
                    throw new RuntimeException("La adición con ID #{$additionId} no existe en el catálogo.");
                }

                foreach ($addition->items as $additionItem) {
                    $consumoAdicion = round((float) $additionItem->cantidad * (float) $additionQty, 3);
                    $ingredienteId = (int) $additionItem->ingrediente_id;

                    $consolidated[$ingredienteId] = round(($consolidated[$ingredienteId] ?? 0.0) + $consumoAdicion, 3);
                    $breakdown[] = [
                        'product_id' => $ingredienteId,
                        'name' => $additionItem->ingrediente?->nombre ?? "Insumo #{$ingredienteId}",
                        'quantity' => $consumoAdicion,
                        'unit' => 'GRAMO',
                        'source' => 'ADDITION',
                        'addition_id' => $addition->id,
                        'addition_name' => $addition->nombre,
                    ];
                }
            }
        }

        return [
            'consolidated_ingredients' => $consolidated,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Consolida el requerimiento total de ingredientes de una lista completa de ítems de comanda/venta.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, float> Mapa acumulado de [ingredienteId => totalGramosOUnidades]
     */
    public function calculateOrderTotalConsumptions(array $items): array
    {
        $totalConsolidated = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = (float) $item['quantity'];
            $additions = $item['additions'] ?? [];

            $product = Product::findOrFail($productId);
            $consumption = $this->calculateItemConsumption($product, $quantity, $additions);

            foreach ($consumption['consolidated_ingredients'] as $ingredienteId => $neededQty) {
                $totalConsolidated[$ingredienteId] = round(
                    ($totalConsolidated[$ingredienteId] ?? 0.0) + (float) $neededQty,
                    3
                );
            }
        }

        return $totalConsolidated;
    }

    /**
     * Normaliza el formato de adiciones para soportar:
     * - [addition_id => quantity]
     * - [['addition_id' => ..., 'quantity' => ...]]
     * - [addition_id, addition_id, ...] (asume quantity = 1)
     *
     * @param  array  $input
     * @return array<int, float>
     */
    private function normalizeAdditionsInput(array $input): array
    {
        $normalized = [];

        foreach ($input as $key => $val) {
            if (is_array($val)) {
                $id = (int) ($val['addition_id'] ?? $val['adicion_id'] ?? $val['id'] ?? 0);
                $qty = (float) ($val['quantity'] ?? $val['cantidad'] ?? 1);
                if ($id > 0 && $qty > 0) {
                    $normalized[$id] = round(($normalized[$id] ?? 0.0) + $qty, 3);
                }
            } elseif (is_numeric($key) && is_numeric($val) && $key > 0 && ! is_int($key)) {
                // [id => qty]
                $id = (int) $key;
                $qty = (float) $val;
                if ($id > 0 && $qty > 0) {
                    $normalized[$id] = round(($normalized[$id] ?? 0.0) + $qty, 3);
                }
            } elseif (is_int($key) && is_numeric($val)) {
                // Podría ser lista secuencial de IDs [id1, id2] o [id => qty]
                // Si la key es secuencial 0, 1, 2... el valor es el ID de la adición
                if (isset($input[0]) && array_keys($input) === range(0, count($input) - 1)) {
                    $id = (int) $val;
                    if ($id > 0) {
                        $normalized[$id] = round(($normalized[$id] ?? 0.0) + 1.0, 3);
                    }
                } else {
                    $id = (int) $key;
                    $qty = (float) $val;
                    if ($id > 0 && $qty > 0) {
                        $normalized[$id] = round(($normalized[$id] ?? 0.0) + $qty, 3);
                    }
                }
            }
        }

        return $normalized;
    }
}
