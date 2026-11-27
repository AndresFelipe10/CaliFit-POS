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
use Illuminate\Support\Collection as SupportCollection;
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

            // Verificar explícitamente que la mesa destino no tenga otra comanda activa real
            $newTableHasActiveOrder = RestaurantOrder::where('table_id', $lockedNewTable->id)
                ->where('id', '!=', $lockedOrder->id)
                ->whereIn('status', RestaurantOrder::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($newTableHasActiveOrder) {
                throw ValidationException::withMessages([
                    'table_id' => 'La mesa seleccionada ya tiene una comanda activa en curso.',
                ]);
            }

            $lockedOldTable = $oldTableId ? $lockedTables->get($oldTableId) : null;
            $oldTableName = $lockedOldTable ? $lockedOldTable->name : ('Mesa #' . ($oldTableId ?? 'N/A'));

            // Actualizar mesa previa a libre solo si no le quedan otras órdenes activas
            if ($lockedOldTable) {
                $oldTableHasOtherActiveOrder = RestaurantOrder::where('table_id', $lockedOldTable->id)
                    ->where('id', '!=', $lockedOrder->id)
                    ->whereIn('status', RestaurantOrder::ACTIVE_STATUSES)
                    ->exists();

                if (! $oldTableHasOtherActiveOrder) {
                    $lockedOldTable->update(['status' => 'available']);
                }
            }

            // Actualizar nueva mesa al estado correspondiente según el estado de la orden
            $targetTableStatus = $lockedOrder->status === 'billed' ? 'billed' : 'occupied';
            $lockedNewTable->update(['status' => $targetTableStatus]);

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

            if ($lockedItem->is_paid || (float) ($lockedItem->paid_quantity ?? 0) > 0) {
                throw new \DomainException('No se puede eliminar un producto que ya tiene unidades pagadas o liquidadas.');
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
     * Construye las filas desagregadas por unidad para la vista de "División de Cuenta por Platos".
     * Cada ítem pendiente con cantidad entera > 1 se desagrega en filas individuales de 1 unidad.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildSplitUnitsViewData(RestaurantOrder $order): array
    {
        $rows = [];
        $activeItems = $order->items
            ->where('status', '!=', 'cancelled')
            ->where('is_paid', false)
            ->sortBy('id');

        foreach ($activeItems as $item) {
            $pendingQty = $item->pending_quantity;
            if ($pendingQty <= 0.0001) {
                continue;
            }

            $totalQty = (float) $item->quantity;
            $paidQty = (float) ($item->paid_quantity ?? 0);
            $unitPrice = round((float) $item->unit_price, 2);
            $productName = $item->product->nombre ?? 'Producto';

            $isWholePending = abs($pendingQty - round($pendingQty)) < 0.0001 && $pendingQty >= 1;

            if ($isWholePending) {
                $unitCount = (int) round($pendingQty);

                // Preparar adiciones pendientes distribuidas enteramente entre las $unitCount filas
                $perRowAdditions = array_fill(0, $unitCount, []);

                foreach ($item->additions as $add) {
                    $pendingAddQty = $add->pending_quantity;
                    if ($pendingAddQty <= 0.0001) {
                        continue;
                    }

                    $addName = $add->addition->nombre ?? 'Adición';
                    $addUnitPrice = round((float) $add->unit_price, 2);

                    if (abs($pendingAddQty - round($pendingAddQty)) < 0.0001) {
                        $intAdd = (int) round($pendingAddQty);
                        $basePerUnit = intdiv($intAdd, $unitCount);
                        $remainder = $intAdd % $unitCount;

                        for ($i = 0; $i < $unitCount; $i++) {
                            $qtyForUnit = $basePerUnit + ($i < $remainder ? 1 : 0);
                            if ($qtyForUnit > 0) {
                                $perRowAdditions[$i][] = [
                                    'addition_id' => (int) $add->adicion_id,
                                    'name' => $addName,
                                    'quantity' => $qtyForUnit,
                                    'unit_price' => $addUnitPrice,
                                    'subtotal' => round($qtyForUnit * $addUnitPrice, 2),
                                ];
                            }
                        }
                    } else {
                        // Adición fraccionaria: asignar a la primera fila pendiente
                        $perRowAdditions[0][] = [
                            'addition_id' => (int) $add->adicion_id,
                            'name' => $addName,
                            'quantity' => $pendingAddQty,
                            'unit_price' => $addUnitPrice,
                            'subtotal' => round($pendingAddQty * $addUnitPrice, 2),
                        ];
                    }
                }

                $startOffset = (int) round($paidQty);
                $totalDisplayQty = abs($totalQty - round($totalQty)) < 0.0001 ? (int) round($totalQty) : $totalQty;

                for ($i = 0; $i < $unitCount; $i++) {
                    $rowAdds = $perRowAdditions[$i];
                    $addsTotal = array_sum(array_column($rowAdds, 'subtotal'));
                    $rowSubtotal = round($unitPrice + $addsTotal, 2);

                    $unitNumber = $startOffset + $i + 1;
                    $unitLabel = $totalDisplayQty > 1
                        ? "Unidad {$unitNumber} de {$totalDisplayQty}"
                        : null;

                    $rows[] = [
                        'row_key' => $item->id . '_u' . ($i + 1),
                        'item_id' => (int) $item->id,
                        'product_name' => $productName,
                        'unit_label' => $unitLabel,
                        'quantity' => 1,
                        'unit_price' => $unitPrice,
                        'additions' => $rowAdds,
                        'subtotal' => $rowSubtotal,
                        'notes' => $item->notes,
                        'is_fractional' => false,
                    ];
                }
            } else {
                // Cantidad fraccionaria: mantener como una fila seleccionable por su cantidad pendiente
                $rowAdds = [];
                $addsTotal = 0.0;
                foreach ($item->additions as $add) {
                    $pendingAddQty = $add->pending_quantity;
                    if ($pendingAddQty <= 0.0001) {
                        continue;
                    }
                    $addUnitPrice = round((float) $add->unit_price, 2);
                    $addSub = round($pendingAddQty * $addUnitPrice, 2);
                    $addsTotal += $addSub;
                    $rowAdds[] = [
                        'addition_id' => (int) $add->adicion_id,
                        'name' => $add->addition->nombre ?? 'Adición',
                        'quantity' => $pendingAddQty,
                        'unit_price' => $addUnitPrice,
                        'subtotal' => $addSub,
                    ];
                }

                $rows[] = [
                    'row_key' => $item->id . '_frac',
                    'item_id' => (int) $item->id,
                    'product_name' => $productName,
                    'unit_label' => null,
                    'quantity' => $pendingQty,
                    'unit_price' => $unitPrice,
                    'additions' => $rowAdds,
                    'subtotal' => round(($pendingQty * $unitPrice) + $addsTotal, 2),
                    'notes' => $item->notes,
                    'is_fractional' => true,
                ];
            }
        }

        return $rows;
    }

    /**
     * Normaliza selecciones mixtas (legacy item_ids o items por cantidad) consolidando por item_id.
     *
     * @return array<int, array{item_id: int, quantity: float|null, additions: array<int, float>|null}>
     */
    protected function normalizePartialSelection(array $selection): array
    {
        $consolidated = [];

        foreach ($selection as $entry) {
            if (is_numeric($entry)) {
                $itemId = (int) $entry;
                if ($itemId <= 0) {
                    continue;
                }
                if (! isset($consolidated[$itemId])) {
                    $consolidated[$itemId] = [
                        'item_id' => $itemId,
                        'quantity' => null, // Modo legacy: toda la cantidad pendiente
                        'additions' => null,
                    ];
                }
                continue;
            }

            if (! is_array($entry) || empty($entry['item_id'])) {
                continue;
            }

            $itemId = (int) $entry['item_id'];
            $qty = round((float) ($entry['quantity'] ?? 1), 3);
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }

            if (! isset($consolidated[$itemId])) {
                $consolidated[$itemId] = [
                    'item_id' => $itemId,
                    'quantity' => $qty,
                    'additions' => array_key_exists('additions', $entry) ? [] : null,
                ];
            } else {
                $consolidated[$itemId]['quantity'] = round((float) ($consolidated[$itemId]['quantity'] ?? 0) + $qty, 3);
                if (array_key_exists('additions', $entry) && $consolidated[$itemId]['additions'] === null) {
                    $consolidated[$itemId]['additions'] = [];
                }
            }

            if (! empty($entry['additions']) && is_array($entry['additions'])) {
                foreach ($entry['additions'] as $add) {
                    $addId = (int) ($add['addition_id'] ?? $add['adicion_id'] ?? 0);
                    $addQty = round((float) ($add['quantity'] ?? 0), 3);
                    if ($addId > 0 && $addQty > 0) {
                        $consolidated[$itemId]['additions'][$addId] = round(
                            ($consolidated[$itemId]['additions'][$addId] ?? 0.0) + $addQty,
                            3
                        );
                    }
                }
            }
        }

        return $consolidated;
    }

    /**
     * Construye colección virtual de ítems con cantidades y adiciones exactas para Pre-cuenta parcial.
     */
    public function buildVirtualItemsForPreBill(RestaurantOrder $order, array $selection): SupportCollection
    {
        $consolidated = $this->normalizePartialSelection($selection);
        if (empty($consolidated)) {
            return new SupportCollection();
        }

        $orderItems = $order->items()
            ->whereIn('id', array_keys($consolidated))
            ->where('status', '!=', 'cancelled')
            ->with(['product', 'additions.addition'])
            ->get()
            ->keyBy('id');

        $virtualItems = new SupportCollection();

        foreach ($consolidated as $itemId => $req) {
            /** @var \App\Models\RestaurantOrderItem|null $item */
            $item = $orderItems->get($itemId);
            if (! $item) {
                continue;
            }

            $pendingQty = $item->pending_quantity > 0 ? $item->pending_quantity : (float) $item->quantity;
            $qty = $req['quantity'] !== null ? min((float) $req['quantity'], $pendingQty) : $pendingQty;
            if ($qty <= 0) {
                continue;
            }

            $clone = $item->replicate();
            $clone->id = $item->id;
            $clone->quantity = $qty;
            $baseSub = round($qty * (float) $item->unit_price, 2);
            $addsSub = 0.0;
            $virtualAdds = new SupportCollection();

            foreach ($item->additions as $add) {
                $pendingAddQty = $add->pending_quantity > 0 ? $add->pending_quantity : (float) $add->quantity;
                if ($req['additions'] !== null) {
                    $addQty = min((float) ($req['additions'][$add->adicion_id] ?? 0.0), $pendingAddQty);
                } else {
                    $ratio = $pendingQty > 0 ? ($qty / $pendingQty) : 1.0;
                    $addQty = round($pendingAddQty * $ratio, 3);
                }

                if ($addQty > 0) {
                    $addClone = $add->replicate();
                    $addClone->quantity = $addQty;
                    $addClone->subtotal = round($addQty * (float) $add->unit_price, 2);
                    $addClone->setRelation('addition', $add->addition);
                    $addsSub += $addClone->subtotal;
                    $virtualAdds->push($addClone);
                }
            }

            $clone->subtotal = round($baseSub + $addsSub, 2);
            $clone->setRelation('product', $item->product);
            $clone->setRelation('additions', $virtualAdds);

            $virtualItems->push($clone);
        }

        return $virtualItems;
    }

    /**
     * Liquida una fracción de ítems (por cantidad exacta o ítems completos) de una comanda,
     * soporta contado o crédito/fiado, genera comprobante independiente, registra traza en
     * restaurant_order_splits, descuenta inventario y actualiza estado de mesa/orden.
     *
     * @param  RestaurantOrder  $order
     * @param  array            $selection
     * @param  array            $paymentData
     * @param  User             $seller
     * @return Sale
     */
    public function settlePartialItems(RestaurantOrder $order, array $selection, array $paymentData, User $seller): Sale
    {
        return DB::transaction(function () use ($order, $selection, $paymentData, $seller) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->order_type !== 'table') {
                throw new InvalidArgumentException('Solo se pueden liquidar platos de comandas de mesa.');
            }

            if ($lockedOrder->status === 'closed' || $lockedOrder->status === 'cancelled') {
                throw new InvalidArgumentException("No se puede liquidar una comanda en estado {$lockedOrder->status}.");
            }

            $consolidated = $this->normalizePartialSelection($selection);

            if (empty($consolidated)) {
                throw new InvalidArgumentException('Debe seleccionar al menos un plato de la comanda para liquidar.');
            }

            $requestedItemIds = array_keys($consolidated);
            sort($requestedItemIds);

            $selectedItems = $lockedOrder->items()
                ->whereIn('id', $requestedItemIds)
                ->where('status', '!=', 'cancelled')
                ->where('is_paid', false)
                ->with(['additions.addition', 'product'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($selectedItems->isEmpty()) {
                throw new InvalidArgumentException('Los platos seleccionados ya fueron pagados o no pertenecen a esta comanda.');
            }

            $saleItems = [];
            $itemsToUpdate = [];
            $itemsSummary = [];

            foreach ($consolidated as $itemId => $req) {
                /** @var \App\Models\RestaurantOrderItem|null $item */
                $item = $selectedItems->get($itemId);
                if (! $item) {
                    throw new InvalidArgumentException('Los platos seleccionados ya fueron pagados o no pertenecen a esta comanda.');
                }

                $pendingQty = $item->pending_quantity;
                if ($pendingQty <= 0.0001) {
                    throw new InvalidArgumentException("El plato '{$item->product->nombre}' ya fue liquidado en su totalidad.");
                }

                $qtyToPay = $req['quantity'] !== null ? round((float) $req['quantity'], 3) : $pendingQty;

                if ($qtyToPay <= 0 || $qtyToPay > ($pendingQty + 0.0001)) {
                    throw new InvalidArgumentException(
                        "La cantidad seleccionada ({$qtyToPay}) para '{$item->product->nombre}' excede la cantidad pendiente ({$pendingQty})."
                    );
                }

                $completesItem = ($pendingQty - $qtyToPay) <= 0.0001;
                if ($completesItem) {
                    $qtyToPay = $pendingQty;
                }

                $addsPayload = [];
                $additionsPaidMap = [];
                $addsSummary = [];
                $addsSubtotal = 0.0;

                foreach ($item->additions as $orderAdd) {
                    $pendingAddQty = $orderAdd->pending_quantity;
                    if ($pendingAddQty <= 0.0001) {
                        continue;
                    }

                    if ($completesItem) {
                        // Al completar la última unidad del ítem, absorber todas las adiciones pendientes del ítem
                        $addQtyToPay = $pendingAddQty;
                    } elseif ($req['additions'] !== null) {
                        $requestedAddQty = round((float) ($req['additions'][$orderAdd->adicion_id] ?? 0.0), 3);
                        if ($requestedAddQty > ($pendingAddQty + 0.0001)) {
                            throw new InvalidArgumentException(
                                "La cantidad de adición solicitada para '{$item->product->nombre}' excede la pendiente."
                            );
                        }
                        $addQtyToPay = min($requestedAddQty, $pendingAddQty);
                    } else {
                        $ratio = $pendingQty > 0 ? ($qtyToPay / $pendingQty) : 1.0;
                        $addQtyToPay = round($pendingAddQty * $ratio, 3);
                    }

                    if ($addQtyToPay > 0.0001) {
                        $addsPayload[] = [
                            'addition_id' => $orderAdd->adicion_id,
                            'quantity' => $addQtyToPay,
                        ];
                        $additionsPaidMap[$orderAdd->adicion_id] = $addQtyToPay;
                        $addSub = round($addQtyToPay * (float) $orderAdd->unit_price, 2);
                        $addsSubtotal += $addSub;
                        $addsSummary[] = [
                            'name' => $orderAdd->addition->nombre ?? 'Adición',
                            'quantity' => $addQtyToPay,
                            'subtotal' => $addSub,
                        ];
                    }
                }

                $saleItems[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $qtyToPay,
                    'additions' => $addsPayload,
                    'notes' => $item->notes,
                ];

                $itemsToUpdate[] = [
                    'item' => $item,
                    'qty_paid' => $qtyToPay,
                    'completes_item' => $completesItem,
                    'additions_paid' => $additionsPaidMap,
                ];

                $itemsSummary[] = [
                    'order_item_id' => $item->id,
                    'product_name' => $item->product->nombre ?? 'Producto',
                    'quantity' => $qtyToPay,
                    'subtotal' => round(($qtyToPay * (float) $item->unit_price) + $addsSubtotal, 2),
                    'additions' => $addsSummary,
                ];
            }

            // Determinar si tras esta liquidación quedan unidades pendientes en la comanda
            $allUnpaidItems = $lockedOrder->items()
                ->where('status', '!=', 'cancelled')
                ->where('is_paid', false)
                ->lockForUpdate()
                ->get();

            $hasRemainingPending = false;
            foreach ($allUnpaidItems as $uItem) {
                $paidNow = 0.0;
                foreach ($itemsToUpdate as $entry) {
                    if ($entry['item']->id === $uItem->id) {
                        $paidNow = $entry['qty_paid'];
                        break;
                    }
                }
                $remainingForItem = round($uItem->pending_quantity - $paidNow, 3);
                if ($remainingForItem > 0.0001) {
                    $hasRemainingPending = true;
                    break;
                }
            }

            $isLastPayment = ! $hasRemainingPending;
            $isCredit = (bool) ($paymentData['is_credit'] ?? false);

            $defaultNote = $isCredit
                ? "Liquidación parcial a crédito/fiado - Comanda {$lockedOrder->order_number}"
                : "Liquidación parcial de platos - Comanda {$lockedOrder->order_number}";

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
                'notes' => ! empty($paymentData['notes']) ? $paymentData['notes'] : $defaultNote,
                'items' => $saleItems,
                'is_credit' => $isCredit,
                'credit_due_date' => $paymentData['credit_due_date'] ?? null,
                'payments' => $isCredit ? [] : ($paymentData['payments'] ?? []),
                'close_restaurant_order' => $isLastPayment,
            ];

            // 1. Procesar venta atómica (descuenta inventario bajo lockForUpdate)
            $sale = $this->saleService->processSale($salePayload, $seller);

            // 2. Actualizar cantidades pagadas / estado en items y adiciones
            foreach ($itemsToUpdate as $entry) {
                /** @var \App\Models\RestaurantOrderItem $item */
                $item = $entry['item'];
                $newPaidQty = round((float) ($item->paid_quantity ?? 0) + $entry['qty_paid'], 3);
                $fullyPaid = $entry['completes_item'] || ($newPaidQty >= ((float) $item->quantity - 0.0001));

                $item->update([
                    'paid_quantity' => $fullyPaid ? $item->quantity : $newPaidQty,
                    'is_paid' => $fullyPaid,
                    'sale_id' => $sale->id,
                ]);

                foreach ($entry['additions_paid'] as $addId => $addQtyPaid) {
                    $orderAdd = $item->additions->firstWhere('adicion_id', $addId);
                    if ($orderAdd) {
                        $newAddPaid = round((float) ($orderAdd->paid_quantity ?? 0) + $addQtyPaid, 3);
                        $addFullyPaid = $newAddPaid >= ((float) $orderAdd->quantity - 0.0001);
                        $orderAdd->update([
                            'paid_quantity' => $addFullyPaid ? $orderAdd->quantity : $newAddPaid,
                        ]);
                    }
                }
            }

            // 3. Registrar corte en restaurant_order_splits para trazabilidad completa
            $nextShareNumber = ((int) ($lockedOrder->splits()->max('share_number') ?? 0)) + 1;
            $firstPayment = $sale->payments->first();

            RestaurantOrderSplit::create([
                'restaurant_order_id' => $lockedOrder->id,
                'share_number' => $nextShareNumber,
                'total_shares' => $nextShareNumber,
                'amount' => (float) $sale->total,
                'status' => 'paid',
                'is_credit' => $isCredit,
                'payment_method' => $sale->payment_method,
                'cash_received' => $firstPayment?->cash_received,
                'change_given' => $firstPayment?->change_given,
                'reference' => $isCredit ? 'Crédito / Fiado' : ($firstPayment?->reference),
                'items_summary' => $itemsSummary,
                'user_id' => $seller->id,
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'paid_at' => now(),
            ]);

            $paidSplitsCount = $lockedOrder->splits()->where('status', 'paid')->count();
            $lockedOrder->update([
                'split_type' => 'items',
                'paid_shares' => $paidSplitsCount,
                'total_shares' => $lockedOrder->splits()->count(),
            ]);

            // 4. Recalcular totales pendientes de la comanda
            $lockedOrder->recalculateTotals();

            // 5. Actualizar estado de orden y mesa
            if ($isLastPayment) {
                $lockedOrder->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

                if ($lockedOrder->table) {
                    $otherActive = RestaurantOrder::where('table_id', $lockedOrder->table->id)
                        ->where('id', '!=', $lockedOrder->id)
                        ->whereIn('status', RestaurantOrder::ACTIVE_STATUSES)
                        ->exists();

                    if (! $otherActive) {
                        $lockedOrder->table->update(['status' => 'available']);
                    }
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
                    $pendingQty = $item->pending_quantity;
                    if ($pendingQty <= 0.0001) {
                        continue;
                    }

                    $adds = [];
                    foreach ($item->additions as $a) {
                        $pendingAddQty = $a->pending_quantity;
                        if ($pendingAddQty > 0.0001) {
                            $adds[] = [
                                'addition_id' => $a->adicion_id,
                                'quantity' => $pendingAddQty,
                            ];
                        }
                    }

                    $saleItems[] = [
                        'product_id' => $item->product_id,
                        'quantity' => $pendingQty,
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

            // Marcar todos los ítems liquidados y sus adiciones como pagados si provienen de la comanda
            if ($unpaidItems->isNotEmpty()) {
                foreach ($unpaidItems as $uItem) {
                    $uItem->update([
                        'paid_quantity' => $uItem->quantity,
                        'is_paid' => true,
                        'sale_id' => $sale->id,
                    ]);
                    foreach ($uItem->additions as $uAdd) {
                        $uAdd->update([
                            'paid_quantity' => $uAdd->quantity,
                        ]);
                    }
                }
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

