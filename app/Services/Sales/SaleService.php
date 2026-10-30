<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Addition;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleDetailAddition;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Kitchen\RecipeService;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaleService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected RecipeService $recipeService
    ) {}

    /**
     * Procesa una venta completa dentro de una transacción atómica bajo bloqueo pesimista.
     *
     * @param  array  $saleData Datos de la venta (items, customer_id, payments, delivery_fee, etc.)
     * @param  User   $seller   Usuario cajero o vendedor
     * @return Sale
     *
     * @throws InsufficientInventoryException
     * @throws InvalidArgumentException
     */
    public function processSale(array $saleData, User $seller): Sale
    {
        $sale = DB::transaction(function () use ($saleData, $seller) {
            $items = $saleData['items'] ?? [];

            if (empty($items)) {
                throw new InvalidArgumentException('La venta debe contener al menos un producto o plato.');
            }

            // 1. Consolidar requerimientos totales de inventario (receta base + adiciones + suplementos)
            $totalConsumptions = $this->recipeService->calculateOrderTotalConsumptions($items);

            // 2. Bloqueo pesimista y verificación de Cero Stock Negativo
            $this->inventoryService->checkAvailability($totalConsumptions);

            // 3. Resolución autoritativa de precios y cálculo monetario en Backend
            $subtotal = 0.0;
            $resolvedDetails = [];

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = round((float) $item['quantity'], 3);

                if ($quantity <= 0) {
                    throw new InvalidArgumentException("La cantidad del producto ID #{$productId} debe ser mayor a 0.");
                }

                /** @var Product $product */
                $product = Product::lockForUpdate()->findOrFail($productId);
                $unitPrice = round((float) $product->precio_venta, 2);
                $itemSubtotal = round($unitPrice * $quantity, 2);
                $subtotal += $itemSubtotal;

                $resolvedAdditions = [];
                $rawAdditions = $item['additions'] ?? [];

                // Normalizar y resolver precio de adiciones
                foreach ($this->normalizeAdditionsArray($rawAdditions) as $adicionId => $adicionQty) {
                    /** @var Addition $addition */
                    $addition = Addition::findOrFail($adicionId);
                    $addPrice = round((float) $addition->precio, 2);
                    $addSubtotal = round($addPrice * (float) $adicionQty, 2);
                    $subtotal += $addSubtotal;

                    $resolvedAdditions[] = [
                        'adicion_id' => $addition->id,
                        'quantity' => $adicionQty,
                        'unit_price' => $addPrice,
                        'subtotal' => $addSubtotal,
                    ];
                }

                $resolvedDetails[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                    'additions' => $resolvedAdditions,
                ];
            }

            // Descuentos autoritativos
            $discount = round((float) ($saleData['discount'] ?? 0.0), 2);
            $discountPct = round((float) ($saleData['discount_percentage'] ?? 0.0), 2);

            if ($discountPct > 0) {
                if ($discountPct > 100) {
                    throw new InvalidArgumentException('El porcentaje de descuento no puede ser mayor al 100%.');
                }
                $discount = round($subtotal * ($discountPct / 100), 2);
            }

            $orderType = $saleData['order_type'] ?? 'retail';
            $deliveryFee = $orderType === 'takeout' ? 0.00 : round((float) ($saleData['delivery_fee'] ?? 0.0), 2);
            $serviceFee = round((float) ($saleData['service_fee'] ?? 0.0), 2);
            $taxInc = round((float) ($saleData['tax_inc'] ?? 0.0), 2);
            $tip = max(0.00, round((float) ($saleData['tip'] ?? 0.0), 2));

            $total = round($subtotal - $discount + $deliveryFee + $serviceFee + $taxInc + $tip, 2);

            if (isset($saleData['override_total'])) {
                $total = round((float) $saleData['override_total'], 2);
                $subtotal = round((float) ($saleData['override_subtotal'] ?? $total), 2);
                $discount = 0.00;
                $discountPct = 0.00;
                $deliveryFee = 0.00;
                $serviceFee = 0.00;
                $taxInc = 0.00;

                // Ajustar subtotal de detalles proporcionalmente para que sumen exactamente $subtotal
                $rawDetailsSum = array_sum(array_column($resolvedDetails, 'subtotal'));
                if ($rawDetailsSum > 0 && count($resolvedDetails) > 0) {
                    $ratio = $subtotal / $rawDetailsSum;
                    $allocatedSubtotal = 0.0;
                    $count = count($resolvedDetails);
                    foreach ($resolvedDetails as $idx => &$d) {
                        if ($idx === $count - 1) {
                            $d['subtotal'] = round($subtotal - $allocatedSubtotal, 2);
                        } else {
                            $d['subtotal'] = round($d['subtotal'] * $ratio, 2);
                            $allocatedSubtotal += $d['subtotal'];
                        }
                    }
                    unset($d);
                }
            }

            if ($total < 0) {
                throw new InvalidArgumentException('El total de la venta no puede ser negativo.');
            }

            // 4. Consecutivo seguro bajo bloqueo
            $invoiceNumber = $this->generateConsecutiveInvoiceNumber();

            // 5. Snapshot inmutable del comprador y validación de Crédito
            $isCredit = (bool) ($saleData['is_credit'] ?? false);
            $customerId = $saleData['customer_id'] ?? null;
            $customerName = 'Consumidor Final';
            $customerDoc = '222222222222';

            if ($isCredit) {
                if (empty($customerId)) {
                    throw new InvalidArgumentException('Para realizar una venta a crédito debe seleccionar un cliente registrado.');
                }

                /** @var Customer|null $creditCustomer */
                $creditCustomer = Customer::find($customerId);
                if (! $creditCustomer || $creditCustomer->document === '222222222222' || str_contains(strtoupper($creditCustomer->name), 'CONSUMIDOR FINAL')) {
                    throw new InvalidArgumentException('Está terminantemente prohibido otorgar crédito al Consumidor Final genérico (documento 222222222222).');
                }

                if (empty(trim((string) $creditCustomer->phone))) {
                    throw new InvalidArgumentException('El cliente debe contar con un número de teléfono de contacto verificable para acceder a ventas a crédito.');
                }

                $customerName = $creditCustomer->name;
                $customerDoc = $creditCustomer->document;
                $primaryPaymentMethod = 'credit';
                $processedPayments = [];
            } else {
                if ($customerId) {
                    $customer = Customer::find($customerId);
                    if ($customer) {
                        $customerName = $customer->name;
                        $customerDoc = $customer->document;
                    }
                } elseif (! empty($saleData['customer_name'])) {
                    $customerName = $saleData['customer_name'];
                    $customerDoc = $saleData['customer_document'] ?? '222222222222';
                }

                // 6. Validar pagos mixtos y autoritativo de vueltos
                $payments = $saleData['payments'] ?? [];
                $processedPayments = $this->validateAndCalculatePayments($payments, $total);
                $primaryPaymentMethod = $this->determinePaymentMethodSummary($processedPayments);
            }

            // 7. Crear encabezado de venta
            $waiterName = $saleData['waiter_name'] ?? null;
            if (! $waiterName && ! empty($saleData['restaurant_order_id'])) {
                $waiterName = RestaurantOrder::where('id', $saleData['restaurant_order_id'])->value('waiter_name');
            }

            $sale = Sale::create([
                'user_id' => $seller->id,
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'customer_document' => $customerDoc,
                'waiter_name' => $waiterName,
                'restaurant_order_id' => $saleData['restaurant_order_id'] ?? null,
                'order_type' => $orderType,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discount_percentage' => $discountPct,
                'delivery_fee' => $deliveryFee,
                'service_fee' => $serviceFee,
                'tax_inc' => $taxInc,
                'tip' => $tip,
                'total' => $total,
                'payment_method' => $primaryPaymentMethod,
                'status' => 'completed',
                'notes' => $saleData['notes'] ?? null,
                'is_credit' => $isCredit,
                'credit_status' => $isCredit ? 'pending' : 'none',
                'credit_due_date' => $isCredit ? ($saleData['credit_due_date'] ?? null) : null,
            ]);

            // 8. Crear líneas de detalle y adiciones
            foreach ($resolvedDetails as $detailData) {
                $saleDetail = SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $detailData['product_id'],
                    'quantity' => $detailData['quantity'],
                    'unit_price' => $detailData['unit_price'],
                    'subtotal' => $detailData['subtotal'],
                ]);

                foreach ($detailData['additions'] as $add) {
                    SaleDetailAddition::create([
                        'sale_detail_id' => $saleDetail->id,
                        'adicion_id' => $add['adicion_id'],
                        'quantity' => $add['quantity'],
                        'unit_price' => $add['unit_price'],
                        'subtotal' => $add['subtotal'],
                    ]);
                }
            }

            // 9. Deducir inventario y registrar movimientos (atómico bajo lockForUpdate en orden ascendente)
            ksort($totalConsumptions);
            foreach ($totalConsumptions as $ingredienteId => $totalGramos) {
                $this->inventoryService->registerMovement(
                    product: $ingredienteId,
                    quantity: $totalGramos,
                    type: InventoryMovement::TYPE_SALIDA_VENTA,
                    reason: "Venta {$invoiceNumber}" . ($isCredit ? ' (Crédito)' : ''),
                    reference: $sale,
                    user: $seller
                );
            }

            // 10. Persistir pagos en sale_payments (si no es crédito)
            foreach ($processedPayments as $payment) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                    'cash_received' => $payment['cash_received'] ?? null,
                    'change_given' => $payment['change_given'] ?? null,
                ]);
            }

            // 11. Cierre de comanda de restaurante y liberación de mesa
            $closeRestaurantOrder = (bool) ($saleData['close_restaurant_order'] ?? true);
            if (! empty($saleData['restaurant_order_id'])) {
                $restaurantOrder = RestaurantOrder::find($saleData['restaurant_order_id']);
                if ($restaurantOrder) {
                    if ($tip > 0) {
                        $restaurantOrder->tip = round((float) $restaurantOrder->tip + $tip, 2);
                    }

                    if ($closeRestaurantOrder) {
                        $restaurantOrder->status = 'closed';
                        $restaurantOrder->closed_at = now();

                        if ($restaurantOrder->table) {
                            $restaurantOrder->table->update([
                                'status' => 'available',
                            ]);
                        }
                    }
                    $restaurantOrder->save();
                }
            }

            return $sale->load(['details.additions.adicion', 'details.product', 'payments']);
        });

        // Encolado automático de comprobante / factura de venta para impresión física en PC
        app(\App\Services\Printing\PrintQueueService::class)->enqueueSaleReceipt($sale);

        return $sale;
    }

    /**
     * Anula simétricamente una venta completada, devolviendo todos los insumos y suplementos.
     *
     * @param  Sale    $sale
     * @param  User    $authorizer
     * @param  string  $reason
     * @return Sale
     *
     * @throws DomainException
     */
    public function cancelSale(Sale $sale, User $authorizer, string $reason): Sale
    {
        return DB::transaction(function () use ($sale, $authorizer, $reason) {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if ($lockedSale->status !== 'completed') {
                throw new DomainException("Solo se pueden anular ventas en estado completado. Estado actual: {$lockedSale->status}");
            }

            // Cargar detalles y adiciones
            $lockedSale->loadMissing([
                'details.product.receta.items',
                'details.additions.adicion.items',
            ]);

            // Revertir consumos de cada línea de venta
            foreach ($lockedSale->details as $detail) {
                $product = $detail->product;

                if ($product->isDish() || $product->isPreparation()) {
                    // 1. Revertir receta base
                    $recipe = $product->receta;
                    if ($recipe) {
                        foreach ($recipe->items as $item) {
                            $revertGramos = round((float) $item->cantidad_por_porcion * (float) $detail->quantity, 3);
                            $this->inventoryService->registerMovement(
                                product: $item->ingrediente_id,
                                quantity: $revertGramos,
                                type: InventoryMovement::TYPE_DEVOLUCION_ANULACION,
                                reason: "Anulación venta {$lockedSale->invoice_number}: {$reason}",
                                reference: $lockedSale,
                                user: $authorizer
                            );
                        }
                    }

                    // 2. Revertir adiciones de esta línea
                    foreach ($detail->additions as $detailAddition) {
                        $adicion = $detailAddition->adicion;
                        if ($adicion) {
                            foreach ($adicion->items as $addItem) {
                                $revertAddGramos = round((float) $addItem->cantidad * (float) $detailAddition->quantity, 3);
                                $this->inventoryService->registerMovement(
                                    product: $addItem->ingrediente_id,
                                    quantity: $revertAddGramos,
                                    type: InventoryMovement::TYPE_DEVOLUCION_ANULACION,
                                    reason: "Anulación venta {$lockedSale->invoice_number} (Adición {$adicion->nombre}): {$reason}",
                                    reference: $lockedSale,
                                    user: $authorizer
                                );
                            }
                        }
                    }
                } else {
                    // Reversión directa de suplementos / insumos
                    $this->inventoryService->registerMovement(
                        product: $detail->product_id,
                        quantity: (float) $detail->quantity,
                        type: InventoryMovement::TYPE_DEVOLUCION_ANULACION,
                        reason: "Anulación venta {$lockedSale->invoice_number}: {$reason}",
                        reference: $lockedSale,
                        user: $authorizer
                    );
                }
            }

            // Marcar venta como anulada y actualizar crédito si corresponde
            $auditNote = "Anulada el " . now()->format('Y-m-d H:i') . " por {$authorizer->name}. Motivo: {$reason}";
            $updateData = [
                'status' => 'cancelled',
                'notes' => trim(($lockedSale->notes ? $lockedSale->notes . ' | ' : '') . $auditNote),
            ];

            if ($lockedSale->isCredit() || (bool) $lockedSale->is_credit) {
                $updateData['credit_status'] = 'cancelled';
            }

            $lockedSale->update($updateData);

            // Si tenía orden de restaurante, actualizar a cancelled con trazabilidad completa de auditoría
            if ($lockedSale->restaurant_order_id) {
                $order = RestaurantOrder::find($lockedSale->restaurant_order_id);
                if ($order && in_array($order->status, ['closed', 'billed', 'open'], true)) {
                    $order->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => "Venta {$lockedSale->invoice_number} anulada: {$reason}",
                        'cancelled_by' => $authorizer->id,
                        'cancelled_at' => now(),
                        'closed_at' => now(),
                    ]);

                    $order->items()->where('status', '!=', 'cancelled')->update(['status' => 'cancelled']);

                    if ($order->table) {
                        $order->table->update(['status' => 'available']);
                    }
                }
            }

            return $lockedSale->fresh(['details.additions.adicion', 'details.product', 'payments']);
        });
    }

    /**
     * Genera un número de factura consecutivo (FAC-000001) bloqueando el último registro.
     */
    protected function generateConsecutiveInvoiceNumber(): string
    {
        $lastSale = Sale::lockForUpdate()->latest('id')->first();
        $nextNumber = $lastSale ? ($lastSale->id + 1) : 1;

        return 'FAC-' . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Valida líneas de pago mixtas y calcula autoritativamente los vueltos.
     *
     * @param  array  $payments
     * @param  float  $total
     * @return array
     */
    protected function validateAndCalculatePayments(array $payments, float $total): array
    {
        // Si no se proporcionaron pagos, asumir efectivo por el valor total
        if (empty($payments)) {
            return [
                [
                    'method' => 'cash',
                    'amount' => $total,
                    'reference' => null,
                    'cash_received' => $total,
                    'change_given' => 0.00,
                ],
            ];
        }

        $cashCount = 0;
        $totalAppliedCents = 0;
        $totalExpectedCents = (int) round($total * 100);
        $processed = [];

        foreach ($payments as $payment) {
            $method = strtolower(trim((string) ($payment['method'] ?? 'cash')));
            $amount = round((float) ($payment['amount'] ?? 0.0), 2);

            if ($amount <= 0) {
                throw new InvalidArgumentException("El monto de cada forma de pago debe ser mayor a 0.");
            }

            if ($method === 'cash') {
                $cashCount++;
                if ($cashCount > 1) {
                    throw new InvalidArgumentException('Solo se permite una línea de pago en efectivo por venta.');
                }

                $cashReceived = isset($payment['cash_received']) && (float) $payment['cash_received'] > 0
                    ? round((float) $payment['cash_received'], 2)
                    : $amount;

                if ($cashReceived < $amount) {
                    throw new InvalidArgumentException("El efectivo recibido (\${$cashReceived}) no puede ser menor al monto a pagar (\${$amount}).");
                }

                $changeGiven = round($cashReceived - $amount, 2);

                $processed[] = [
                    'method' => 'cash',
                    'amount' => $amount,
                    'reference' => $payment['reference'] ?? null,
                    'cash_received' => $cashReceived,
                    'change_given' => $changeGiven,
                ];
            } else {
                $processed[] = [
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $payment['reference'] ?? null,
                    'cash_received' => null,
                    'change_given' => null,
                ];
            }

            $totalAppliedCents += (int) round($amount * 100);
        }

        if ($totalAppliedCents !== $totalExpectedCents) {
            $appliedFormatted = number_format($totalAppliedCents / 100, 2);
            $expectedFormatted = number_format($totalExpectedCents / 100, 2);
            throw new InvalidArgumentException("La suma de los pagos (\${$appliedFormatted}) debe coincidir exactamente con el total de la venta (\${$expectedFormatted}).");
        }

        return $processed;
    }

    /**
     * Determina el método de pago resumen ('cash', 'card', 'transfer', 'mixed').
     */
    protected function determinePaymentMethodSummary(array $payments): string
    {
        if (count($payments) === 1) {
            return $payments[0]['method'];
        }

        return 'mixed';
    }

    /**
     * Normaliza el array de adiciones por ítem.
     */
    protected function normalizeAdditionsArray(array $input): array
    {
        $normalized = [];

        foreach ($input as $key => $val) {
            if (is_array($val)) {
                $id = (int) ($val['adicion_id'] ?? $val['addition_id'] ?? $val['id'] ?? 0);
                $qty = (float) ($val['quantity'] ?? $val['cantidad'] ?? 1);
                if ($id > 0 && $qty > 0) {
                    $normalized[$id] = round(($normalized[$id] ?? 0.0) + $qty, 3);
                }
            } elseif (is_numeric($key) && is_numeric($val) && $key > 0 && ! is_int($key)) {
                $id = (int) $key;
                $qty = (float) $val;
                if ($id > 0 && $qty > 0) {
                    $normalized[$id] = round(($normalized[$id] ?? 0.0) + $qty, 3);
                }
            } elseif (is_int($key) && is_numeric($val)) {
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
