<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientInventoryException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Verifica la disponibilidad de insumos aplicando bloqueo pesimista.
     *
     * @param  array<int, float>  $itemsToConsume Mapa de [productId => requiredQuantity]
     * @return bool
     *
     * @throws InsufficientInventoryException
     */
    public function checkAvailability(array $itemsToConsume): bool
    {
        if (empty($itemsToConsume)) {
            return true;
        }

        $productIds = array_values(array_unique(array_map('intval', array_keys($itemsToConsume))));
        sort($productIds);

        // Bloqueo pesimista ordenado de forma ascendente para prevenir deadlocks
        $products = Product::whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($itemsToConsume as $productId => $requiredQuantity) {
            $requiredQuantity = round((float) $requiredQuantity, 3);

            if ($requiredQuantity <= 0) {
                continue;
            }

            /** @var Product|null $product */
            $product = $products->get($productId);

            if ($product === null) {
                throw new InsufficientInventoryException(
                    productName: "ID #{$productId}",
                    availableStock: 0.0,
                    requiredQuantity: $requiredQuantity,
                    unit: 'UNIDAD',
                    message: "El producto con ID #{$productId} no existe en el catálogo."
                );
            }

            $currentStock = (float) $product->stock;

            if ($currentStock < $requiredQuantity) {
                throw new InsufficientInventoryException(
                    productName: $product->nombre,
                    availableStock: $currentStock,
                    requiredQuantity: $requiredQuantity,
                    unit: $product->unidad_base
                );
            }
        }

        return true;
    }

    /**
     * Registra un movimiento de inventario, actualiza el stock del producto
     * y genera la traza inmutable en inventory_movements.
     *
     * @param  Product|int  $product
     * @param  float  $quantity
     * @param  string  $type
     * @param  string|null  $reason
     * @param  mixed  $reference
     * @param  User|null  $user
     * @return InventoryMovement
     *
     * @throws InsufficientInventoryException
     */
    public function registerMovement(
        Product|int $product,
        float $quantity,
        string $type,
        ?string $reason = null,
        mixed $reference = null,
        ?User $user = null
    ): InventoryMovement {
        $execute = function () use ($product, $quantity, $type, $reason, $reference, $user) {
            $productId = $product instanceof Product ? $product->id : $product;

            /** @var Product $lockedProduct */
            $lockedProduct = Product::where('id', $productId)->lockForUpdate()->firstOrFail();
            $previousStock = (float) $lockedProduct->stock;
            $quantity = round((float) $quantity, 3);

            switch ($type) {
                case InventoryMovement::TYPE_SALIDA_VENTA:
                case InventoryMovement::TYPE_MERMA:
                    if ($previousStock < $quantity) {
                        throw new InsufficientInventoryException(
                            productName: $lockedProduct->nombre,
                            availableStock: $previousStock,
                            requiredQuantity: $quantity,
                            unit: $lockedProduct->unidad_base
                        );
                    }
                    $newStock = round($previousStock - $quantity, 3);
                    break;

                case InventoryMovement::TYPE_ENTRADA_COMPRA:
                case InventoryMovement::TYPE_DEVOLUCION_ANULACION:
                    $newStock = round($previousStock + $quantity, 3);
                    break;

                case InventoryMovement::TYPE_AJUSTE:
                    $newStock = round($previousStock + $quantity, 3);
                    if ($newStock < 0) {
                        throw new InsufficientInventoryException(
                            productName: $lockedProduct->nombre,
                            availableStock: $previousStock,
                            requiredQuantity: abs($quantity),
                            unit: $lockedProduct->unidad_base,
                            message: "El ajuste de inventario no puede resultar en un stock negativo ({$newStock})."
                        );
                    }
                    break;

                default:
                    throw new \InvalidArgumentException("Tipo de movimiento de inventario no válido: {$type}");
            }

            // Actualizar stock del producto
            $lockedProduct->update([
                'stock' => $newStock,
            ]);

            // Determinar referencia polimórfica
            $referenceType = null;
            $referenceId = null;

            if ($reference instanceof Model) {
                $referenceType = get_class($reference);
                $referenceId = $reference->getKey();
            } elseif (is_array($reference)) {
                $referenceType = $reference['type'] ?? null;
                $referenceId = $reference['id'] ?? null;
            }

            $userId = $user?->id ?? auth()->id() ?? User::first()?->id ?? 1;

            return InventoryMovement::create([
                'product_id' => $lockedProduct->id,
                'user_id' => $userId,
                'type' => $type,
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        };

        // Si ya estamos dentro de una transacción DB, ejecutar directamente
        if (DB::transactionLevel() > 0) {
            return $execute();
        }

        return DB::transaction($execute);
    }

    /**
     * Convierte unidades de compra a la unidad base interna (Gramos / Mililitros).
     *
     * Factor de conversión según manual de SaborCaliFit:
     * - 1 libra (lb) = 500 g
     * - 1 kilo (kg)  = 1000 g
     * - 1 litro (lt) = 1000 ml
     */
    public function convertPurchaseUnit(float $quantity, string $purchaseUnit): float
    {
        $unit = strtolower(trim($purchaseUnit));

        return match ($unit) {
            'libra', 'libras', 'lb', 'lbs' => round($quantity * 500.0, 3),
            'kilo', 'kilos', 'kilogramo', 'kilogramos', 'kg', 'kgs' => round($quantity * 1000.0, 3),
            'litro', 'litros', 'lt', 'lts', 'l' => round($quantity * 1000.0, 3),
            default => round($quantity, 3),
        };
    }
}
