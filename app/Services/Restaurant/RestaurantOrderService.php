<?php

namespace App\Services\Restaurant;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantOrderSplit;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RestaurantOrderService
{
    public function __construct(
        protected ?SaleService $saleService = null
    ) {
        $this->saleService = $saleService ?? app(SaleService::class);
    }

    /**
     * Reasigna la mesa de una comanda activa con bloqueo pesimista y auditoría.
     *
     * @throws ValidationException
     */
    public function changeTable(RestaurantOrder $order, RestaurantTable $newTable, User $user): RestaurantOrder
    {
        // 1. Validar que la orden sea de tipo mesa y su estado permita modificaciones
        if ($order->order_type !== 'table') {
            throw ValidationException::withMessages([
                'order' => 'Solo se pueden transferir comandas asignadas a mesas del salón.',
            ]);
        }

        if (! in_array($order->status, ['open', 'in_kitchen'], true)) {
            throw ValidationException::withMessages([
                'order' => 'Solo se pueden cambiar de mesa comandas activas (en estado abierta o en cocina).',
            ]);
        }

        // 2. Validar que la mesa de destino sea distinta a la actual
        if ((int) $newTable->id === (int) $order->table_id) {
            throw ValidationException::withMessages([
                'table_id' => 'La comanda ya se encuentra asignada a esta mesa.',
            ]);
        }

        // 3. Ejecutar bajo DB::transaction() con lockForUpdate() pesimista
        return DB::transaction(function () use ($order, $newTable, $user) {
            // Bloqueo pesimista sobre la orden
            $lockedOrder = RestaurantOrder::where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->order_type !== 'table' || ! in_array($lockedOrder->status, ['open', 'in_kitchen'], true)) {
                throw ValidationException::withMessages([
                    'order' => 'La comanda ya no se encuentra en un estado activo para ser transferida.',
                ]);
            }

            if ((int) $lockedOrder->table_id === (int) $newTable->id) {
                throw ValidationException::withMessages([
                    'table_id' => 'La comanda ya se encuentra asignada a esta mesa.',
                ]);
            }

            $oldTableId = $lockedOrder->table_id;
            $newTableId = $newTable->id;

            // Bloquear mesas en orden ascendente por ID para evitar posibles deadlocks
            $tableIds = array_values(array_filter(array_unique([$oldTableId, $newTableId])));
            sort($tableIds);

            $lockedTables = RestaurantTable::whereIn('id', $tableIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedNewTable = $lockedTables->get($newTableId);
            if (! $lockedNewTable || ! $lockedNewTable->is_active || $lockedNewTable->status !== 'available') {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada no está disponible o se encuentra inactiva.',
                ]);
            }

            $lockedOldTable = $oldTableId ? $lockedTables->get($oldTableId) : null;
            $oldTableName = $lockedOldTable ? $lockedOldTable->name : ('Mesa #' . ($oldTableId ?? 'N/A'));

            // Actualizar mesa previa a libre
            if ($lockedOldTable) {
                $lockedOldTable->update(['status' => 'available']);
            }

            // Actualizar nueva mesa a ocupada
            $lockedNewTable->update(['status' => 'occupied']);

            // Auditoría y trazabilidad
            $timestamp = now()->format('Y-m-d H:i');
            $auditNote = sprintf(
                '[Traslado de mesa: %s -> %s por %s el %s]',
                $oldTableName,
                $lockedNewTable->name,
                $user->name,
                $timestamp
            );

            $currentNotes = trim((string) $lockedOrder->notes);
            $newNotes = $currentNotes !== '' ? $currentNotes . ' ' . $auditNote : $auditNote;
            if (mb_strlen($newNotes) > 500) {
                $newNotes = mb_substr($newNotes, 0, 500);
            }

            // Actualizar comanda
            $lockedOrder->update([
                'table_id' => $lockedNewTable->id,
                'notes' => $newNotes,
            ]);

            return $lockedOrder->fresh(['table']);
        });
    }

    /**
     * Open a new order for a table (mono-negocio).
     */
    public function openTableOrder(int $userId, array $data): RestaurantOrder
    {
        return DB::transaction(function () use ($userId, $data) {
            $table = RestaurantTable::lockForUpdate()->findOrFail($data['table_id']);

            if ($table->status !== 'available') {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada ya se encuentra ocupada.',
                ]);
            }

            $orderNumber = $this->generateOrderNumber();

            $order = RestaurantOrder::create([
                'table_id' => $table->id,
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'order_type' => 'table',
                'status' => 'open',
                'customer_name' => $data['customer_name'] ?? null,
                'guest_count' => ! empty($data['guest_count']) ? (int) $data['guest_count'] : 1,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0.00,
                'total' => 0.00,
            ]);

            $table->update(['status' => 'occupied']);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->addItemsToOrder($order, $data['items']);
            }

            return $order->fresh(['table', 'items.product', 'user']);
        });
    }

    /**
     * Add dishes or products to an active order.
     *
     * @return Collection<int, RestaurantOrderItem>
     */
    public function addItemsToOrder(RestaurantOrder $order, array $itemsData): Collection
    {
        return DB::transaction(function () use ($order, $itemsData) {
            $hasPrintedItems = $order->items()
                ->where('printed_to_kitchen', true)
                ->where('status', '!=', 'cancelled')
                ->exists();

            $maxBatch = (int) ($order->items()->max('batch_number') ?? 0);
            $batchNumber = $hasPrintedItems ? max(1, $maxBatch + 1) : max(1, $maxBatch);

            $createdItems = new Collection();

            foreach ($itemsData as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);

                $quantity = round((float) $itemData['quantity'], 3);
                $unitPrice = round((float) ($product->precio_venta ?? $product->sale_price ?? 0), 2);
                $subtotal = round($quantity * $unitPrice, 2);

                $orderItem = RestaurantOrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'notes' => ! empty($itemData['notes']) ? trim($itemData['notes']) : null,
                    'status' => 'pending',
                    'printed_to_kitchen' => false,
                    'batch_number' => $batchNumber,
                ]);

                $createdItems->push($orderItem);
            }

            $order->recalculateTotals();

            return $createdItems;
        });
    }

    /**
     * Mark pending items as printed to kitchen and advance status.
     *
     * @return Collection<int, RestaurantOrderItem>
     */
    public function sendToKitchen(RestaurantOrder $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $pendingItems = $order->items()
                ->where('printed_to_kitchen', false)
                ->where('status', '!=', 'cancelled')
                ->with('product')
                ->lockForUpdate()
                ->get();

            if ($pendingItems->isNotEmpty()) {
                foreach ($pendingItems as $item) {
                    $item->update([
                        'printed_to_kitchen' => true,
                        'status' => 'kitchen',
                    ]);
                }

                if ($order->status === 'open') {
                    $order->update(['status' => 'in_kitchen']);
                }
            }

            return $pendingItems;
        });
    }

    /**
     * Open a new delivery or takeout order.
     */
    public function openDeliveryOrTakeoutOrder(int $userId, array $data): RestaurantOrder
    {
        return DB::transaction(function () use ($userId, $data) {
            $orderType = $data['order_type'] ?? 'delivery';
            $deliveryFee = $orderType === 'takeout' ? 0.00 : round((float) ($data['delivery_fee'] ?? 0.00), 2);

            if ($deliveryFee < 0) {
                throw ValidationException::withMessages([
                    'delivery_fee' => 'El costo de envío no puede ser negativo.',
                ]);
            }

            $customerName = $data['customer_name'] ?? null;
            $deliveryPhone = $data['delivery_phone'] ?? null;
            $deliveryAddress = $data['delivery_address'] ?? null;

            if (! empty($data['customer_id'])) {
                $customer = Customer::find($data['customer_id']);
                if ($customer) {
                    $customerName = $customerName ?: $customer->name;
                    $deliveryPhone = $deliveryPhone ?: $customer->phone;
                    $deliveryAddress = $deliveryAddress ?: $customer->address;
                }
            }

            $orderNumber = $this->generateOrderNumber();

            $order = RestaurantOrder::create([
                'table_id' => null,
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'order_type' => $orderType,
                'status' => 'open',
                'customer_id' => $data['customer_id'] ?? null,
                'customer_name' => $customerName,
                'delivery_phone' => $deliveryPhone,
                'delivery_address' => $deliveryAddress,
                'delivery_notes' => $data['delivery_notes'] ?? null,
                'delivery_fee' => $deliveryFee,
                'notes' => $data['notes'] ?? null,
                'subtotal' => 0.00,
                'total' => $deliveryFee,
            ]);

            if (! empty($data['items']) && is_array($data['items'])) {
                $this->addItemsToOrder($order, $data['items']);
            }

            return $order->fresh(['items.product', 'user', 'customer']);
        });
    }

    /**
     * Update delivery operational status (in_kitchen, dispatched, delivered, cancelled).
     */
    public function updateDeliveryStatus(RestaurantOrder $order, string $newStatus): RestaurantOrder
    {
        return DB::transaction(function () use ($order, $newStatus) {
            $validStatuses = ['open', 'in_kitchen', 'dispatched', 'delivered', 'cancelled'];
            if (! in_array($newStatus, $validStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Estado de pedido no válido.',
                ]);
            }

            $order->update(['status' => $newStatus]);

            return $order->fresh();
        });
    }

    /**
     * Cancel an empty order and release its assigned table.
     */
    public function cancelEmptyOrder(RestaurantOrder $order): RestaurantOrder
    {
        return DB::transaction(function () use ($order) {
            if ($order->status !== 'open') {
                throw ValidationException::withMessages([
                    'order' => 'Solo se pueden cancelar comandas en estado abierto.',
                ]);
            }

            if ($order->items()->count() > 0) {
                throw ValidationException::withMessages([
                    'order' => 'No se puede cancelar una comanda que contiene platos registrados. Anule o elimine los consumos primero.',
                ]);
            }

            $order->update(['status' => 'cancelled']);

            if ($order->table_id && $order->table) {
                $order->table->update(['status' => 'available']);
            }

            return $order->fresh();
        });
    }

    /**
     * Cancel an order with mandatory audit reason, lockForUpdate, mark pending items cancelled, and release table.
     */
    public function cancelOrder(RestaurantOrder $order, string $reason, User $user): RestaurantOrder
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'El motivo de cancelación debe tener entre 5 y 500 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($order, $reason, $user) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === 'closed') {
                throw new \DomainException('No se puede cancelar una comanda que ya ha sido cerrada.');
            }

            if ($lockedOrder->items()->where('is_paid', true)->exists()
                || $lockedOrder->splits()->where('status', 'paid')->exists()) {
                throw new \DomainException('Una comanda con cobros o pagos realizados no puede cancelarse directamente.');
            }

            $itemCount = $lockedOrder->items()->where('status', '!=', 'cancelled')->count();
            if ($itemCount > 0 && ! $user->isAdmin()) {
                throw new \DomainException('Solo un administrador puede cancelar una comanda que ya contiene productos registrados.');
            }

            $lockedOrder->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'closed_at' => now(),
            ]);

            $lockedOrder->items()->where('status', '!=', 'cancelled')->update(['status' => 'cancelled']);

            if ($lockedOrder->table) {
                $lockedOrder->table->update(['status' => 'available']);
            }

            return $lockedOrder->fresh();
        });
    }

    /**
     * Cancel an individual order item with audit trace and recalculate order totals.
     */
    public function cancelOrderItem(RestaurantOrder $order, RestaurantOrderItem $item, string $reason, User $user): RestaurantOrderItem
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'cancellation_reason' => 'El motivo de cancelación debe tener entre 5 y 500 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($order, $item, $reason, $user) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedOrder->status, ['closed', 'cancelled'], true)) {
                throw new \DomainException('No se pueden eliminar productos de una comanda cerrada o cancelada.');
            }

            /** @var RestaurantOrderItem $lockedItem */
            $lockedItem = RestaurantOrderItem::where('id', $item->id)
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->is_paid) {
                throw new \DomainException('No se puede eliminar un producto que ya ha sido pagado o liquidado.');
            }

            if ($lockedItem->status === 'cancelled') {
                throw new \DomainException('El producto ya se encuentra cancelado.');
            }

            $lockedItem->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
            ]);

            $lockedOrder->recalculateTotals();

            return $lockedItem->fresh(['product', 'cancelledByUser']);
        });
    }

    /**
     * Generate sequential order number ORD-XXXXXX.
     */
    protected function generateOrderNumber(): string
    {
        $candidateNumber = (RestaurantOrder::max('id') ?? 0) + 1;
        do {
            $candidate = sprintf('ORD-%06d', $candidateNumber);
            $exists = RestaurantOrder::where('order_number', $candidate)->exists();
            $candidateNumber++;
        } while ($exists);

        return $candidate;
    }

    /**
     * Divide la comanda en N partes iguales calculando los centavos exactos sin pérdida de redondeo.
     *
     * @param  RestaurantOrder  $order
     * @param  int              $shares
     * @param  array|null       $paymentData Opcional: Si se proporcionan datos de pago para liquidar una parte
     * @param  User|null        $seller      Usuario que registra el pago
     * @return array|Sale
     */
    public function splitOrderEqually(RestaurantOrder $order, int $shares, ?array $paymentData = null, ?User $seller = null): array|Sale
    {
        if ($shares < 2) {
            throw new InvalidArgumentException('La división en partes iguales requiere al menos 2 personas.');
        }

        return DB::transaction(function () use ($order, $shares, $paymentData, $seller) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->order_type !== 'table') {
                throw new InvalidArgumentException('Solo se pueden dividir comandas asignadas a mesas.');
            }

            if ($lockedOrder->status === 'closed' || $lockedOrder->status === 'cancelled') {
                throw new InvalidArgumentException("No se puede dividir una comanda en estado {$lockedOrder->status}.");
            }

            // Calcular monto por persona en centavos enteros exactos
            $totalCents = (int) round((float) $lockedOrder->total * 100);
            $baseCents = intdiv($totalCents, $shares);
            $remainderCents = $totalCents % $shares;

            $shareAmounts = [];
            for ($i = 1; $i <= $shares; $i++) {
                $cents = $baseCents + ($i <= $remainderCents ? 1 : 0);
                $shareAmounts[$i] = round($cents / 100, 2);
            }

            // Actualizar metadata de orden
            $lockedOrder->update([
                'split_type' => 'equal',
                'total_shares' => $shares,
                'status' => in_array($lockedOrder->status, ['open', 'in_kitchen'], true) ? 'billed' : $lockedOrder->status,
            ]);

            if ($lockedOrder->table && $lockedOrder->table->status === 'occupied') {
                $lockedOrder->table->update(['status' => 'billed']);
            }

            // Inicializar / Sincronizar registros en restaurant_order_splits
            $existingSplits = $lockedOrder->splits()->get()->keyBy('share_number');

            foreach ($shareAmounts as $num => $amt) {
                if (! $existingSplits->has($num)) {
                    RestaurantOrderSplit::create([
                        'restaurant_order_id' => $lockedOrder->id,
                        'share_number' => $num,
                        'total_shares' => $shares,
                        'amount' => $amt,
                        'status' => 'pending',
                    ]);
                }
            }

            // Si se suministraron datos de pago y usuario vendedor, liquidar la parte correspondiente
            if ($paymentData !== null && $seller !== null) {
                $shareNumberToPay = (int) ($paymentData['share_number'] ?? 1);

                return $this->settleEqualShare($lockedOrder, $shareNumberToPay, $paymentData, $seller);
            }

            $sharesList = [];
            $splitsByKey = $lockedOrder->splits()->get()->keyBy('share_number');
            foreach ($shareAmounts as $num => $amt) {
                $splitModel = $splitsByKey->get($num);
                $sharesList[] = [
                    'share_number' => $num,
                    'amount' => (float) $amt,
                    'is_paid' => $splitModel ? $splitModel->status === 'paid' : false,
                    'status' => $splitModel ? $splitModel->status : 'pending',
                ];
            }

            return [
                'total_shares' => $shares,
                'order_total' => (float) $lockedOrder->total,
                'shares' => $sharesList,
                'share_amounts' => $shareAmounts,
                'splits' => $lockedOrder->splits()->orderBy('share_number')->get(),
            ];
        });
    }

    /**
     * Liquida una de las partes de una comanda dividida equitativamente,
     * generando su venta/ticket independiente y descontando proporcionalmente insumos.
     */
    public function settleEqualShare(RestaurantOrder $order, int $shareNumber, array $paymentData, User $seller): Sale
    {
        return DB::transaction(function () use ($order, $shareNumber, $paymentData, $seller) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->order_type !== 'table') {
                throw new InvalidArgumentException('Solo se pueden liquidar partes de comandas de mesa.');
            }

            $totalShares = $lockedOrder->total_shares ?: 2;

            /** @var RestaurantOrderSplit|null $split */
            $split = $lockedOrder->splits()
                ->where('share_number', $shareNumber)
                ->lockForUpdate()
                ->first();

            if (! $split) {
                // Si aún no se han creado las partes, inicializarlas
                $this->splitOrderEqually($lockedOrder, $totalShares);
                $split = $lockedOrder->splits()
                    ->where('share_number', $shareNumber)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($split->status === 'paid') {
                throw new InvalidArgumentException("La parte #{$shareNumber} ya fue pagada anteriormente.");
            }

            $activeItems = $lockedOrder->items()
                ->where('status', '!=', 'cancelled')
                ->with(['additions', 'product'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($activeItems->isEmpty()) {
                throw new InvalidArgumentException('La comanda no tiene platos o consumos registrados.');
            }

            // Construir items proporcionales para la venta y deducción de recetas
            $saleItems = [];
            $fraction = 1.0 / $totalShares;

            foreach ($activeItems as $item) {
                $qty = round((float) $item->quantity * $fraction, 3);
                if ($qty <= 0) {
                    $qty = 0.001;
                }

                $resolvedAdditions = [];
                foreach ($item->additions as $a) {
                    $addQty = round((float) $a->quantity * $fraction, 3);
                    if ($addQty <= 0) {
                        $addQty = 0.001;
                    }
                    $resolvedAdditions[] = [
                        'addition_id' => $a->adicion_id,
                        'quantity' => $addQty,
                    ];
                }

                $saleItems[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $qty,
                    'additions' => $resolvedAdditions,
                    'notes' => "Fracción parte {$shareNumber}/{$totalShares}" . ($item->notes ? ": {$item->notes}" : ''),
                ];
            }

            // Comprobar si esta es la última parte pendiente
            $pendingSplitsCount = $lockedOrder->splits()
                ->where('status', 'pending')
                ->where('id', '!=', $split->id)
                ->count();

            $isLastShare = ($pendingSplitsCount === 0);

            // Preparar pagos para la venta
            $payments = $paymentData['payments'] ?? [
                [
                    'method' => $paymentData['method'] ?? 'cash',
                    'amount' => $split->amount,
                    'cash_received' => $paymentData['cash_received'] ?? $split->amount,
                    'reference' => $paymentData['reference'] ?? null,
                ],
            ];

            $salePayload = [
                'customer_id' => $paymentData['customer_id'] ?? $lockedOrder->customer_id,
                'customer_name' => $paymentData['customer_name'] ?? $lockedOrder->customer_name ?? 'Consumidor Final',
                'customer_document' => $paymentData['customer_document'] ?? $lockedOrder->customer?->document ?? '222222222222',
                'restaurant_order_id' => $lockedOrder->id,
                'order_type' => 'table',
                'waiter_name' => $lockedOrder->waiter_name,
                'delivery_fee' => 0.00,
                'service_fee' => 0.00,
                'discount_percentage' => 0.00,
                'notes' => "Pago Parcial {$shareNumber} de {$totalShares} - Comanda {$lockedOrder->order_number}",
                'items' => $saleItems,
                'override_subtotal' => $split->amount,
                'override_total' => $split->amount,
                'payments' => $payments,
                'close_restaurant_order' => $isLastShare,
            ];

            $sale = $this->saleService->processSale($salePayload, $seller);

            // Actualizar el split
            $primaryMethod = $sale->payment_method;
            $firstPayment = $sale->payments->first();

            $split->update([
                'status' => 'paid',
                'payment_method' => $primaryMethod,
                'cash_received' => $firstPayment?->cash_received,
                'change_given' => $firstPayment?->change_given,
                'reference' => $firstPayment?->reference,
                'user_id' => $seller->id,
                'sale_id' => $sale->id,
                'paid_at' => now(),
            ]);

            $newPaidShares = $lockedOrder->splits()->where('status', 'paid')->count();
            $lockedOrder->update(['paid_shares' => $newPaidShares]);

            if ($isLastShare) {
                $lockedOrder->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

                if ($lockedOrder->table) {
                    $lockedOrder->table->update(['status' => 'available']);
                }
            } else {
                $lockedOrder->update(['status' => 'billed']);
                if ($lockedOrder->table) {
                    $lockedOrder->table->update(['status' => 'billed']);
                }
            }

            return $sale;
        });
    }

    /**
     * Liquida una fracción de ítems específicos de una comanda, genera su comprobante independiente,
     * descuenta dichos platos del consumo pendiente y actualiza la mesa.
     *
     * @param  RestaurantOrder  $order
     * @param  array            $itemIds
     * @param  array            $paymentData
     * @param  User             $seller
     * @return Sale
     */
    public function settlePartialItems(RestaurantOrder $order, array $itemIds, array $paymentData, User $seller): Sale
    {
        return DB::transaction(function () use ($order, $itemIds, $paymentData, $seller) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->order_type !== 'table') {
                throw new InvalidArgumentException('Solo se pueden liquidar platos de comandas de mesa.');
            }

            if ($lockedOrder->status === 'closed' || $lockedOrder->status === 'cancelled') {
                throw new InvalidArgumentException("No se puede liquidar una comanda en estado {$lockedOrder->status}.");
            }

            if (empty($itemIds)) {
                throw new InvalidArgumentException('Debe seleccionar al menos un plato de la comanda para liquidar.');
            }

            $itemIds = array_values(array_unique(array_map('intval', (array) $itemIds)));
            sort($itemIds);

            $selectedItems = $lockedOrder->items()
                ->whereIn('id', $itemIds)
                ->where('status', '!=', 'cancelled')
                ->where('is_paid', false)
                ->with(['additions', 'product'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($selectedItems->isEmpty()) {
                throw new InvalidArgumentException('Los platos seleccionados ya fueron pagados o no pertenecen a esta comanda.');
            }

            // Construir el array de items con adiciones para SaleService
            $saleItems = [];
            foreach ($selectedItems as $item) {
                $adds = [];
                foreach ($item->additions as $a) {
                    $adds[] = [
                        'addition_id' => $a->adicion_id,
                        'quantity' => (float) $a->quantity,
                    ];
                }

                $saleItems[] = [
                    'product_id' => $item->product_id,
                    'quantity' => (float) $item->quantity,
                    'additions' => $adds,
                    'notes' => $item->notes,
                ];
            }

            // Determinar si tras esta liquidación quedan platos pendientes en la comanda
            $remainingUnpaidCount = $lockedOrder->items()
                ->whereNotIn('id', $itemIds)
                ->where('status', '!=', 'cancelled')
                ->where('is_paid', false)
                ->count();

            $isLastPayment = ($remainingUnpaidCount === 0);

            $salePayload = [
                'customer_id' => $paymentData['customer_id'] ?? $lockedOrder->customer_id,
                'customer_name' => $paymentData['customer_name'] ?? $lockedOrder->customer_name ?? 'Consumidor Final',
                'customer_document' => $paymentData['customer_document'] ?? $lockedOrder->customer?->document ?? '222222222222',
                'restaurant_order_id' => $lockedOrder->id,
                'order_type' => 'table',
                'waiter_name' => $lockedOrder->waiter_name,
                'delivery_fee' => 0.00,
                'service_fee' => (float) ($paymentData['service_fee'] ?? 0.00),
                'discount_percentage' => (float) ($paymentData['discount_percentage'] ?? 0.00),
                'discount' => (float) ($paymentData['discount'] ?? 0.00),
                'tax_inc' => (float) ($paymentData['tax_inc'] ?? 0.00),
                'tip' => (float) ($paymentData['tip'] ?? 0.00),
                'notes' => $paymentData['notes'] ?? "Liquidación parcial de platos - Comanda {$lockedOrder->order_number}",
                'items' => $saleItems,
                'payments' => $paymentData['payments'] ?? [],
                'close_restaurant_order' => $isLastPayment,
            ];

            // 1. Procesar venta atómica (descuenta inventario bajo lockForUpdate)
            $sale = $this->saleService->processSale($salePayload, $seller);

            // 2. Marcar items seleccionados como pagados y vincular a la venta
            $lockedOrder->items()
                ->whereIn('id', $selectedItems->pluck('id'))
                ->update([
                    'is_paid' => true,
                    'sale_id' => $sale->id,
                ]);

            // 3. Marcar split_type
            $lockedOrder->update(['split_type' => 'items']);

            // 4. Recalcular totales pendientes de la comanda
            $lockedOrder->recalculateTotals();

            // 5. Actualizar estado de orden y mesa
            if ($isLastPayment) {
                $lockedOrder->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

                if ($lockedOrder->table) {
                    $lockedOrder->table->update(['status' => 'available']);
                }
            } else {
                $lockedOrder->update(['status' => 'billed']);
                if ($lockedOrder->table) {
                    $lockedOrder->table->update(['status' => 'billed']);
                }
            }

            return $sale;
        });
    }

    /**
     * Liquida la totalidad restante de una comanda (o los ítems pendientes tras divisiones previas),
     * genera la venta respectiva deduciendo inventario únicamente de los ítems pendientes,
     * cierra la comanda y libera la mesa.
     *
     * @param  RestaurantOrder  $order
     * @param  array            $saleData
     * @param  User             $seller
     * @return Sale
     */
    public function settleOrder(RestaurantOrder $order, array $saleData, User $seller): Sale
    {
        return DB::transaction(function () use ($order, $saleData, $seller) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === 'closed') {
                throw new InvalidArgumentException("La comanda {$lockedOrder->order_number} ya fue cerrada y liquidada previamente.");
            }

            if ($lockedOrder->status === 'cancelled') {
                throw new InvalidArgumentException("No se puede liquidar la comanda {$lockedOrder->order_number} porque se encuentra cancelada.");
            }

            // Obtener exclusivamente los ítems pendientes de pago si la comanda tiene ítems registrados
            $hasOrderItems = $lockedOrder->items()->count() > 0;
            $unpaidItems = collect();

            if ($hasOrderItems) {
                $unpaidItems = $lockedOrder->items()
                    ->where('is_paid', false)
                    ->where('status', '!=', 'cancelled')
                    ->with(['additions', 'product'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($unpaidItems->isEmpty()) {
                    $lockedOrder->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);
                    if ($lockedOrder->table) {
                        $lockedOrder->table->update(['status' => 'available']);
                    }
                    throw new InvalidArgumentException("Todos los platos de la comanda {$lockedOrder->order_number} ya fueron pagados.");
                }

                $saleItems = [];
                foreach ($unpaidItems as $item) {
                    $adds = [];
                    foreach ($item->additions as $a) {
                        $adds[] = [
                            'addition_id' => $a->adicion_id,
                            'quantity' => (float) $a->quantity,
                        ];
                    }

                    $saleItems[] = [
                        'product_id' => $item->product_id,
                        'quantity' => (float) $item->quantity,
                        'additions' => $adds,
                        'notes' => $item->notes,
                    ];
                }
            } else {
                $saleItems = $saleData['items'] ?? [];
                if (empty($saleItems)) {
                    throw new InvalidArgumentException('La comanda no tiene platos registrados para liquidar.');
                }
            }

            $orderType = $lockedOrder->order_type ?? 'table';
            $deliveryFee = $orderType === 'takeout' ? 0.00 : (float) ($saleData['delivery_fee'] ?? $lockedOrder->delivery_fee ?? 0.00);

            $salePayload = [
                'customer_id' => $saleData['customer_id'] ?? $lockedOrder->customer_id,
                'customer_name' => $saleData['customer_name'] ?? $lockedOrder->customer_name ?? 'Consumidor Final',
                'customer_document' => $saleData['customer_document'] ?? $lockedOrder->customer?->document ?? '222222222222',
                'restaurant_order_id' => $lockedOrder->id,
                'order_type' => $orderType,
                'waiter_name' => $lockedOrder->waiter_name ?: ($saleData['waiter_name'] ?? null),
                'delivery_fee' => $deliveryFee,
                'service_fee' => (float) ($saleData['service_fee'] ?? 0.00),
                'discount_percentage' => (float) ($saleData['discount_percentage'] ?? 0.00),
                'discount' => (float) ($saleData['discount'] ?? 0.00),
                'tax_inc' => (float) ($saleData['tax_inc'] ?? 0.00),
                'tip' => (float) ($saleData['tip'] ?? 0.00),
                'notes' => $saleData['notes'] ?? "Liquidación total comanda {$lockedOrder->order_number}",
                'items' => $saleItems,
                'is_credit' => (bool) ($saleData['is_credit'] ?? false),
                'credit_due_date' => $saleData['credit_due_date'] ?? null,
                'payments' => $saleData['payments'] ?? [],
                'close_restaurant_order' => true,
            ];

            // Procesar la venta atómica con deducción de stock exclusiva para los ítems no pagados
            $sale = $this->saleService->processSale($salePayload, $seller);

            // Marcar todos los ítems liquidados como pagados si provienen de la comanda
            if ($unpaidItems->isNotEmpty()) {
                $lockedOrder->items()
                    ->whereIn('id', $unpaidItems->pluck('id'))
                    ->update([
                        'is_paid' => true,
                        'sale_id' => $sale->id,
                    ]);
            }

            // Actualizar estado de comanda y mesa
            $lockedOrder->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            if ($lockedOrder->table) {
                $lockedOrder->table->update(['status' => 'available']);
            }

            return $sale;
        });
    }
}

