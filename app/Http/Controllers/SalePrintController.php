<?php

namespace App\Http\Controllers;

use App\Models\RestaurantOrder;
use App\Models\Sale;
use App\Services\Printing\PrintQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalePrintController extends Controller
{
    public function __construct(
        protected PrintQueueService $queueService
    ) {}

    /**
     * Comprobante térmico de venta no DIAN (80 mm).
     */
    public function receipt(Request|Sale $request, ?Sale $sale = null): View|JsonResponse
    {
        if ($request instanceof Sale) {
            $sale = $request;
            $request = request();
        }

        $sale->load([
            'details.product',
            'details.additions.addition',
            'payments',
            'user',
            'restaurantOrder.table',
            'restaurantOrder.items',
        ]);

        if ($request->boolean('enqueue')) {
            $this->queueService->enqueueSaleReceipt($sale);
        }

        if ($request->wantsJson() || $request->ajax()) {
            if (! $request->boolean('enqueue')) {
                $this->queueService->enqueueSaleReceipt($sale);
            }

            return response()->json([
                'ok'      => true,
                'message' => 'Comprobante de venta enviado a la cola de impresión exitosamente',
            ]);
        }

        return view('prints.receipt', compact('sale'));
    }

    /**
     * Ticket térmico de cocina / barra por tanda (80 mm).
     * OMITIR ESTRICTAMENTE PRECIOS, TOTALES O SÍMBOLOS DE MONEDA.
     */
    public function kitchenTicket(Request $request, RestaurantOrder $order, $batch = null): View|JsonResponse
    {
        $order->load(['table', 'user', 'customer']);

        $batchNumber = $batch ? (int) $batch : (int) ($order->items()->max('batch_number') ?: 1);

        $isReprint = $request->boolean('reprint');

        $items = $order->items()
            ->where('batch_number', $batchNumber)
            ->where('status', '!=', 'cancelled')
            ->with(['product', 'additions.addition'])
            ->get();

        // Marcar como impreso en cocina
        $order->items()
            ->where('batch_number', $batchNumber)
            ->where('status', 'pending')
            ->update([
                'printed_to_kitchen' => true,
                'status' => 'kitchen',
            ]);

        $batch = $batchNumber;

        if ($request->boolean('enqueue')) {
            $this->queueService->enqueueKitchenTicket($order, $batch, $isReprint);
        }

        if ($request->wantsJson() || $request->ajax()) {
            if (! $request->boolean('enqueue')) {
                $this->queueService->enqueueKitchenTicket($order, $batch, $isReprint);
            }

            return response()->json([
                'ok'      => true,
                'message' => 'Comanda de cocina enviada a la cola de impresión exitosamente',
            ]);
        }

        return view('prints.kitchen', compact('order', 'batch', 'items', 'isReprint'));
    }

    /**
     * Pre-cuenta informativa para el cliente (80 mm).
     */
    public function preBill(Request|RestaurantOrder $request, ?RestaurantOrder $order = null): View|JsonResponse
    {
        if ($request instanceof RestaurantOrder) {
            $order = $request;
            $request = request();
        }

        $order->load([
            'table',
            'user',
            'customer',
            'items' => function ($q) {
                $q->where('status', '!=', 'cancelled')->with(['product', 'additions.addition']);
            },
        ]);

        if ($request->boolean('enqueue')) {
            $this->queueService->enqueuePreBill($order);
        }

        if ($request->wantsJson() || $request->ajax()) {
            if (! $request->boolean('enqueue')) {
                $this->queueService->enqueuePreBill($order);
            }

            return response()->json([
                'ok'      => true,
                'message' => 'Pre-cuenta enviada a la cola de impresión exitosamente',
            ]);
        }

        return view('prints.pre-bill', compact('order'));
    }

    /**
     * Tirilla de despacho y domicilio (80 mm).
     */
    public function dispatchTicket(Request|RestaurantOrder $request, ?RestaurantOrder $order = null): View|JsonResponse
    {
        if ($request instanceof RestaurantOrder) {
            $order = $request;
            $request = request();
        }

        $order->load([
            'user',
            'customer',
            'items' => function ($q) {
                $q->where('status', '!=', 'cancelled')->with(['product', 'additions.addition']);
            },
        ]);

        if ($request->boolean('enqueue')) {
            $this->queueService->enqueueDispatchTicket($order);
        }

        if ($request->wantsJson() || $request->ajax()) {
            if (! $request->boolean('enqueue')) {
                $this->queueService->enqueueDispatchTicket($order);
            }

            return response()->json([
                'ok'      => true,
                'message' => 'Tirilla de despacho enviada a la cola de impresión exitosamente',
            ]);
        }

        return view('prints.dispatch', compact('order'));
    }
}
