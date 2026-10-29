<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddOrderItemsRequest;
use App\Http\Requests\OpenTableOrderRequest;
use App\Http\Requests\ProcessSaleRequest;
use App\Http\Requests\Restaurant\CancelRestaurantOrderItemRequest;
use App\Http\Requests\Restaurant\CancelRestaurantOrderRequest;
use App\Http\Requests\Restaurant\ChangeOrderTableRequest;
use App\Http\Requests\SplitAndSettleOrderRequest;
use App\Models\Addition;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantOrderItemAddition;
use App\Models\RestaurantTable;
use App\Services\Inventory\InventoryService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Sales\SaleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RestaurantOrderController extends Controller
{
    public function __construct(
        protected SaleService $saleService,
        protected InventoryService $inventoryService,
        protected ?RestaurantOrderService $orderService = null
    ) {
        $this->orderService = $orderService ?? app(RestaurantOrderService::class);
    }

    /**
     * Muestra el módulo Comandas: Listado de hoy e historial anterior.
     */
    public function index(Request $request): View
    {
        $today = now()->startOfDay();
        $tomorrow = now()->endOfDay();

        $search = trim((string) $request->input('search', ''));
        $channel = $request->input('channel') ?: $request->input('order_type');
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $applyFilters = function ($query) use ($search, $channel, $status) {
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('waiter_name', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($cq) use ($search) {
                            $cq->where('name', 'like', "%{$search}%")
                                ->orWhere('document', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            }

            if (! empty($channel) && $channel !== 'all') {
                $query->where('order_type', $channel);
            }

            if (! empty($status) && $status !== 'all') {
                $query->where('status', $status);
            }
        };

        $todayQuery = RestaurantOrder::with(['table', 'user', 'customer', 'cancelledByUser', 'items.product', 'sale'])
            ->where('created_at', '>=', $today)
            ->where('created_at', '<=', $tomorrow);
        $applyFilters($todayQuery);
        $todayOrders = $todayQuery->orderBy('created_at', 'desc')->get();

        $pastQuery = RestaurantOrder::with(['table', 'user', 'customer', 'cancelledByUser', 'items.product', 'sale'])
            ->where('created_at', '<', $today);
        $applyFilters($pastQuery);

        if (! empty($dateFrom)) {
            $pastQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if (! empty($dateTo)) {
            $pastQuery->whereDate('created_at', '<=', $dateTo);
        }

        $pastOrders = $pastQuery->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->appends(['tab' => 'history']);

        $todayCount = RestaurantOrder::where('created_at', '>=', $today)->where('created_at', '<=', $tomorrow)->count();
        $pastCount = RestaurantOrder::where('created_at', '<', $today)->count();

        return view('restaurant.orders.index', [
            'todayOrders' => $todayOrders,
            'pastOrders' => $pastOrders,
            'todayCount' => $todayCount,
            'pastCount' => $pastCount,
            'todayDate' => now()->toDateString(),
            'filters' => [
                'search' => $search,
                'channel' => $channel,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Abre una comanda para una mesa disponible.
     */
    public function open(OpenTableOrderRequest $request, RestaurantTable $table)
    {
        $validated = $request->validated();
        $rawUser = auth()->user()->name ?? 'Mesero';
        $defaultWaiter = explode(' ', trim($rawUser))[0] ?? 'Mesero';
        $waiterName = ! empty($validated['waiter_name']) ? trim($validated['waiter_name']) : $defaultWaiter;

        $hasInitialItems = false;

        try {
            $order = DB::transaction(function () use ($table, $validated, $waiterName, $request, &$hasInitialItems) {
                /** @var RestaurantTable $lockedTable */
                $lockedTable = RestaurantTable::where('id', $table->id)->lockForUpdate()->firstOrFail();

                if ($lockedTable->status !== 'available') {
                    throw new \DomainException('La mesa seleccionada ya se encuentra ocupada o en cobro.');
                }

                $orderNumber = $this->generateOrderNumber();
                $lockedTable->update(['status' => 'occupied']);

                $order = RestaurantOrder::create([
                    'table_id' => $lockedTable->id,
                    'user_id' => auth()->id(),
                    'order_number' => $orderNumber,
                    'order_type' => 'table',
                    'status' => 'open',
                    'guest_count' => $validated['guest_count'] ?? 1,
                    'waiter_name' => $waiterName,
                    'notes' => $validated['notes'] ?? null,
                    'subtotal' => 0.00,
                    'total' => 0.00,
                ]);

            $rawItems = $request->input('items');
            if (is_string($rawItems)) {
                $rawItems = json_decode($rawItems, true);
            }

            if (! empty($rawItems) && is_array($rawItems)) {
                foreach ($rawItems as $itemData) {
                    if (empty($itemData['product_id'])) {
                        continue;
                    }
                    $product = Product::find($itemData['product_id']);
                    if (! $product) {
                        continue;
                    }

                    $quantity = round((float) ($itemData['quantity'] ?? 1), 3);
                    $unitPrice = round((float) $product->precio_venta, 2);
                    $subtotal = round($quantity * $unitPrice, 2);

                    $orderItem = RestaurantOrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'notes' => $itemData['notes'] ?? null,
                        'status' => 'kitchen',
                        'printed_to_kitchen' => true,
                        'batch_number' => 1,
                    ]);

                    $hasInitialItems = true;

                    if (! empty($itemData['additions']) && is_array($itemData['additions'])) {
                        foreach ($itemData['additions'] as $additionData) {
                            $additionId = $additionData['addition_id'] ?? $additionData['id'] ?? null;
                            if (! $additionId) {
                                continue;
                            }
                            $addition = Addition::find($additionId);
                            if (! $addition) {
                                continue;
                            }

                            $addQty = round((float) ($additionData['quantity'] ?? 1), 3);
                            $addPrice = round((float) $addition->precio, 2);
                            $addSubtotal = round($addQty * $addPrice, 2);

                            RestaurantOrderItemAddition::create([
                                'order_item_id' => $orderItem->id,
                                'adicion_id' => $addition->id,
                                'quantity' => $addQty,
                                'unit_price' => $addPrice,
                                'subtotal' => $addSubtotal,
                            ]);

                            $orderItem->subtotal += $addSubtotal;
                        }
                        $orderItem->save();
                    }
                }

                $order->recalculateTotals();
            }

            return $order;
        });
        } catch (\DomainException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        $tableName = str_starts_with(trim($table->name), 'Mesa') 
            ? trim($table->name) 
            : 'Mesa ' . trim($table->name);

        $message = "{$tableName} abierta exitosamente con comanda #{$order->order_number}";

        if ($hasInitialItems) {
            session()->flash('auto_print_kitchen_url', route('prints.orders.kitchen', ['order' => $order->id, 'batch' => 1]));
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('restaurant.orders.show', $order),
                'auto_print_kitchen_url' => $hasInitialItems ? route('prints.orders.kitchen', ['order' => $order->id, 'batch' => 1]) : null,
            ]);
        }

        return redirect()->route('restaurant.orders.show', $order)->with('success', $message);
    }

    /**
     * Muestra el detalle operativo de la comanda de restaurante.
     */
    public function show(RestaurantOrder $order): View
    {
        $order->load(['table', 'user', 'customer', 'items.product', 'items.additions.addition']);
        $batches = $order->items->groupBy('batch_number');

        $products = Product::where('activo', true)
            ->with(['adiciones' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('nombre')
            ->get();

        $platos = $products->filter(fn ($p) => $p->isDish() || $p->tipo_producto === 'PLATO')->values();
        $suplementos = $products->filter(fn ($p) => $p->isSupplement() || $p->tipo_producto === 'SUPLEMENTO')->values();
        $adiciones = Addition::where('activo', true)->orderBy('nombre')->get();
        $customers = Customer::orderBy('name')->get();

        $unpaidItemsList = $order->items
            ->where('status', '!=', 'cancelled')
            ->where('is_paid', false);

        $pendingSubtotal = 0.0;
        foreach ($unpaidItemsList as $pi) {
            $pendingSubtotal += (float) $pi->subtotal;
        }

        $pendingDeliveryFee = ($order->order_type === 'delivery') ? (float) $order->delivery_fee : 0.0;
        $pendingTotal = round($pendingSubtotal + $pendingDeliveryFee, 2);

        $orderItemsData = $unpaidItemsList
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (float) $item->quantity,
                'additions' => $item->additions->map(fn ($a) => [
                    'addition_id' => $a->adicion_id,
                    'quantity' => (float) $a->quantity,
                ])->values()->all(),
            ])->values()->all();

        $availableTables = RestaurantTable::where('status', 'available')
            ->where('is_active', true)
            ->where('id', '!=', $order->table_id ?? 0)
            ->orderBy('name')
            ->get();

        return view('restaurant.orders.show', compact('order', 'batches', 'products', 'platos', 'suplementos', 'adiciones', 'customers', 'orderItemsData', 'availableTables', 'pendingSubtotal', 'pendingTotal'));
    }

    /**
     * Transfiere o reasigna la mesa de una comanda activa.
     */
    public function changeTable(
        ChangeOrderTableRequest $request,
        RestaurantOrder $order,
        RestaurantOrderService $orderService
    ) {
        $newTable = RestaurantTable::findOrFail($request->validated('table_id'));

        $updatedOrder = $orderService->changeTable($order, $newTable, $request->user());

        $message = "Mesa cambiada exitosamente a: {$newTable->name}";

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'order' => $updatedOrder,
                'table' => $updatedOrder->table,
                'redirect' => route('restaurant.orders.show', $updatedOrder),
            ]);
        }

        return redirect()->route('restaurant.orders.show', $updatedOrder)->with('success', $message);
    }

    /**
     * Agrega una nueva tanda de ítems (platos y adiciones) a la comanda.
     */
    public function addItems(AddOrderItemsRequest $request, RestaurantOrder $order): JsonResponse
    {
        return DB::transaction(function () use ($request, $order) {
            /** @var RestaurantOrder $lockedOrder */
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedOrder->status, ['closed', 'cancelled'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pueden agregar productos a una comanda cerrada o cancelada.',
                ], 422);
            }

            $maxBatch = (int) ($lockedOrder->items()->max('batch_number') ?? 0);
            $batchNumber = $maxBatch + 1;

            $itemsData = $request->validated()['items'];
            foreach ($itemsData as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = round((float) $itemData['quantity'], 3);
                $unitPrice = round((float) $product->precio_venta, 2);
                $subtotal = round($quantity * $unitPrice, 2);

                $orderItem = RestaurantOrderItem::create([
                    'order_id' => $lockedOrder->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'notes' => $itemData['notes'] ?? null,
                    'status' => 'pending',
                    'printed_to_kitchen' => false,
                    'batch_number' => $batchNumber,
                ]);

                if (! empty($itemData['additions']) && is_array($itemData['additions'])) {
                    foreach ($itemData['additions'] as $additionData) {
                        $addition = Addition::findOrFail($additionData['addition_id']);
                        $addQty = round((float) ($additionData['quantity'] ?? 1), 3);
                        $addPrice = round((float) $addition->precio, 2);
                        $addSubtotal = round($addQty * $addPrice, 2);

                        RestaurantOrderItemAddition::create([
                            'order_item_id' => $orderItem->id,
                            'adicion_id' => $addition->id,
                            'quantity' => $addQty,
                            'unit_price' => $addPrice,
                            'subtotal' => $addSubtotal,
                        ]);

                        $orderItem->subtotal += $addSubtotal;
                    }
                    $orderItem->save();
                }
            }

            $lockedOrder->recalculateTotals();

            return response()->json([
                'success' => true,
                'batch_number' => $batchNumber,
                'message' => 'Tanda agregada a la orden exitosamente',
            ]);
        });
    }

    /**
     * Genera la pre-cuenta informativa y cambia el estado a 'billed'.
     */
    public function preBill(RestaurantOrder $order)
    {
        if (! in_array($order->status, ['closed', 'cancelled'], true)) {
            $order->update(['status' => 'billed']);
            if ($order->table) {
                $order->table->update(['status' => 'billed']);
            }
        }

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Pre-cuenta generada correctamente',
                'redirect' => route('prints.orders.pre-bill', $order),
            ]);
        }

        return redirect()->route('prints.orders.pre-bill', $order);
    }

    /**
     * Liquida y cobra la comanda llamando al RestaurantOrderService transaccional.
     */
    public function settle(ProcessSaleRequest $request, RestaurantOrder $order): JsonResponse
    {
        $saleData = $request->validated();

        try {
            $sale = $this->orderService->settleOrder($order, $saleData, auth()->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'message' => 'Comanda liquidada y cobrada con éxito',
        ]);
    }

    /**
     * Endpoint para procesar la liquidación parcial de platos de una comanda de mesa.
     */
    public function splitAndSettle(SplitAndSettleOrderRequest $request, RestaurantOrder $order): JsonResponse
    {
        $data = $request->validated();
        $user = auth()->user();

        try {
            if (($data['split_type'] ?? 'items') === 'equal') {
                $shareNumber = (int) ($data['share_number'] ?? 1);
                $sale = $this->orderService->settleEqualShare($order, $shareNumber, $data, $user);
            } else {
                $itemIds = $data['item_ids'] ?? [];
                $sale = $this->orderService->settlePartialItems($order, $itemIds, $data, $user);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $freshOrder = $order->fresh(['table', 'splits']);
        $isClosed = $freshOrder->status === 'closed';

        return response()->json([
            'success' => true,
            'message' => $isClosed
                ? 'Todos los platos han sido liquidados: comanda cerrada y mesa liberada exitosamente.'
                : 'Pago parcial liquidado con éxito. Mesa permanece con saldo pendiente.',
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'is_order_closed' => $isClosed,
            'table_status' => $freshOrder->table?->status ?? 'available',
            'order_status' => $freshOrder->status,
            'remaining_total' => (float) $freshOrder->total,
            'paid_shares' => (int) $freshOrder->paid_shares,
            'total_shares' => (int) $freshOrder->total_shares,
            'print_url' => route('prints.sales.receipt', $sale),
        ]);
    }

    /**
     * Emite la pre-cuenta térmica de 80 mm para platos seleccionados de la comanda (división por platos).
     */
    public function printPartialPreBill(Request $request, RestaurantOrder $order): View
    {
        $rawItems = $request->input('item_ids') ?? $request->input('items') ?? [];
        $itemIds = is_array($rawItems) ? $rawItems : array_filter(explode(',', (string) $rawItems));

        if (empty($itemIds)) {
            abort(422, 'Debe seleccionar al menos un plato de la comanda para imprimir la pre-cuenta individual.');
        }

        $order->load(['table', 'user', 'customer']);

        $items = $order->items()
            ->whereIn('id', $itemIds)
            ->where('status', '!=', 'cancelled')
            ->with(['product', 'additions.addition'])
            ->get();

        if ($items->isEmpty()) {
            abort(404, 'No se encontraron platos activos para los IDs seleccionados.');
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += (float) $item->subtotal;
        }

        return view('prints.pre-bill-partial', [
            'order' => $order,
            'items' => $items,
            'subtotal' => $subtotal,
            'total' => $subtotal,
        ]);
    }

    /**
     * Cancela la comanda y libera la mesa con motivo obligatorio de auditoría.
     */
    public function cancel(CancelRestaurantOrderRequest $request, RestaurantOrder $order)
    {
        $user = auth()->user();
        $reason = $request->validated('cancellation_reason');

        try {
            $this->orderService->cancelOrder($order, $reason, $user);
        } catch (\DomainException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Comanda cancelada y mesa liberada exitosamente',
            ]);
        }

        return redirect()->route('restaurant.tables.index')->with('success', 'Comanda cancelada y mesa liberada exitosamente.');
    }

    /**
     * Cancela un ítem individual de la comanda con motivo obligatorio de auditoría.
     */
    public function cancelItem(CancelRestaurantOrderItemRequest $request, RestaurantOrder $order, RestaurantOrderItem $item)
    {
        $user = auth()->user();
        $reason = $request->validated('cancellation_reason');

        try {
            $cancelledItem = $this->orderService->cancelOrderItem($order, $item, $reason, $user);
        } catch (\DomainException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }
            return back()->with('error', $e->getMessage());
        }

        $productName = $cancelledItem->product->nombre ?? 'Producto';

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Producto '{$productName}' eliminado de la comanda correctamente (Motivo registrado en auditoría).",
                'order_subtotal' => (float) $order->fresh()->subtotal,
                'order_total' => (float) $order->fresh()->total,
            ]);
        }

        return redirect()->route('restaurant.orders.show', $order)
            ->with('success', "Producto '{$productName}' eliminado de la comanda correctamente (Motivo registrado en auditoría).");
    }

    /**
     * Genera un número correlativo secuencial ORD-XXXXXX.
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
}
