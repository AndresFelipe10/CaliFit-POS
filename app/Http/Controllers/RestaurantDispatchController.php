<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryOrderRequest;
use App\Models\Addition;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantOrderItemAddition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RestaurantDispatchController extends Controller
{
    /**
     * Muestra el tablero Kanban de despacho para Domicilios y Para Llevar.
     */
    public function index(): View
    {
        $orders = RestaurantOrder::whereIn('order_type', ['delivery', 'takeout'])
            ->whereIn('status', ['open', 'in_kitchen', 'dispatched', 'delivered'])
            ->with(['items.product', 'items.additions.addition', 'user'])
            ->orderByDesc('created_at')
            ->get();

        $inKitchen = $orders->filter(fn ($o) => in_array($o->status, ['open', 'in_kitchen']))->values();
        $dispatched = $orders->filter(fn ($o) => $o->status === 'dispatched')->values();
        $delivered = $orders->filter(fn ($o) => $o->status === 'delivered')->values();

        $products = Product::where('activo', true)
            ->with(['adiciones' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('nombre')
            ->get();

        $adiciones = Addition::where('activo', true)->orderBy('nombre')->get();
        $customers = Customer::orderBy('name')->get();

        return view('restaurant.dispatch.index', compact('inKitchen', 'dispatched', 'delivered', 'products', 'adiciones', 'customers'));
    }

    /**
     * Registra un nuevo pedido a domicilio o para llevar con su primera tanda de cocina.
     */
    public function store(StoreDeliveryOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ($data['order_type'] === 'takeout') {
            $data['delivery_fee'] = 0.00;
        }

        $order = DB::transaction(function () use ($data) {
            $candidateNumber = (RestaurantOrder::max('id') ?? 0) + 1;
            do {
                $orderNumber = sprintf('ORD-%06d', $candidateNumber);
                $exists = RestaurantOrder::where('order_number', $orderNumber)->exists();
                $candidateNumber++;
            } while ($exists);

            $order = RestaurantOrder::create([
                'table_id' => null,
                'user_id' => auth()->id(),
                'order_number' => $orderNumber,
                'order_type' => $data['order_type'],
                'status' => 'in_kitchen',
                'customer_name' => $data['customer_name'],
                'delivery_fee' => $data['delivery_fee'] ?? 0.00,
                'notes' => trim("Tel: {$data['phone']}" . (! empty($data['address']) ? " | Dir: {$data['address']}" : '') . (! empty($data['notes']) ? " | {$data['notes']}" : '')),
                'subtotal' => 0.00,
                'total' => 0.00,
            ]);

            foreach ($data['items'] as $itemData) {
                $product = Product::findOrFail($itemData['product_id']);
                $quantity = round((float) $itemData['quantity'], 3);
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
                    'printed_to_kitchen' => false,
                    'batch_number' => 1,
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

            $order->recalculateTotals();

            return $order;
        });

        $kitchenPrintUrl = route('prints.orders.kitchen', ['order' => $order->id, 'batch' => 1]);
        session()->flash('print_url', $kitchenPrintUrl);

        return response()->json([
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'print_url' => $kitchenPrintUrl,
            'message' => 'Pedido de despacho creado con éxito',
        ], 201);
    }

    /**
     * Actualiza el estado operativo entre las columnas del tablero Kanban.
     */
    public function updateStatus(Request $request, RestaurantOrder $order): JsonResponse
    {
        if (in_array($order->status, ['closed', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar una orden que ya ha sido cerrada o cancelada.',
            ], 422);
        }

        $newStatus = $request->input('status');
        if (in_array($newStatus, ['closed', 'cancelled'], true)) {
            $msg = $newStatus === 'closed'
                ? 'Para cerrar o facturar la orden debe utilizar el proceso de cobro y liquidación de comanda.'
                : 'Para anular la comanda debe utilizar la acción de cancelación con motivo de auditoría requerido.';

            return response()->json([
                'success' => false,
                'message' => $msg,
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:open,in_kitchen,dispatched,delivered,billed',
        ]);

        return DB::transaction(function () use ($order, $validated) {
            $lockedOrder = RestaurantOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if (in_array($lockedOrder->status, ['closed', 'cancelled'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede modificar una orden que ya ha sido cerrada o cancelada.',
                ], 422);
            }

            $lockedOrder->update(['status' => $validated['status']]);
            $order->status = $validated['status'];

            return response()->json([
                'success' => true,
                'status' => $order->status,
                'message' => 'Estado del despacho actualizado correctamente',
            ]);
        });
    }
}
