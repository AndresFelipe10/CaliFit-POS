<?php

namespace App\Http\Controllers;

use App\Models\RestaurantOrder;
use App\Models\Sale;
use App\Services\Printing\PrintQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrintJobController extends Controller
{
    public function __construct(
        protected PrintQueueService $queueService
    ) {}

    /**
     * Encola un trabajo de impresión a petición del frontend vía AJAX.
     */
    public function enqueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo'       => 'required|string|in:comanda,factura,despacho,precuenta,precuenta_parcial,cuadre',
            'id'         => 'nullable|integer',
            'batch'      => 'nullable|integer',
            'reprint'    => 'nullable|boolean',
            'item_ids'   => 'nullable|array',
            'item_ids.*' => 'integer',
        ]);

        $tipo = $validated['tipo'];
        $id = $validated['id'] ?? null;
        $job = null;

        switch ($tipo) {
            case 'comanda':
                $order = RestaurantOrder::findOrFail($id);
                $batch = $validated['batch'] ?? null;
                $isReprint = (bool) ($validated['reprint'] ?? true);
                $job = $this->queueService->enqueueKitchenTicket($order, $batch, $isReprint);
                break;

            case 'factura':
                $sale = Sale::findOrFail($id);
                $job = $this->queueService->enqueueSaleReceipt($sale);
                break;

            case 'despacho':
                $order = RestaurantOrder::findOrFail($id);
                $job = $this->queueService->enqueueDispatchTicket($order);
                break;

            case 'precuenta':
                $order = RestaurantOrder::findOrFail($id);
                $job = $this->queueService->enqueuePreBill($order);
                break;

            case 'precuenta_parcial':
                $order = RestaurantOrder::findOrFail($id);
                $itemIds = $validated['item_ids'] ?? [];
                $items = $order->items()
                    ->whereIn('id', $itemIds)
                    ->with(['product', 'additions.addition'])
                    ->get();
                $job = $this->queueService->enqueuePreBill($order, $items);
                break;

            case 'cuadre':
                $cashRegisterCtrl = app(CashRegisterController::class);
                $metrics = $cashRegisterCtrl->calculateDailyMetrics($request->input('date'));
                $job = $this->queueService->enqueueCashRegister($metrics);
                break;
        }

        return response()->json([
            'ok'      => true,
            'job_id'  => $job?->id,
            'message' => 'Ticket enviado a la cola de impresión exitosamente',
        ]);
    }
}

