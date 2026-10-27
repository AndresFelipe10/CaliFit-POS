<?php

namespace App\Http\Controllers;

use App\Models\RestaurantOrder;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalePrintController extends Controller
{
    /**
     * Comprobante térmico de venta no DIAN (58 mm).
     */
    public function receipt(Sale $sale): View
    {
        $sale->load([
            'details.product',
            'details.additions.addition',
            'payments',
            'user',
        ]);

        return view('prints.receipt', compact('sale'));
    }

    /**
     * Ticket térmico de cocina / barra por tanda (58 mm).
     * OMITIR ESTRICTAMENTE PRECIOS, TOTALES O SÍMBOLOS DE MONEDA.
     */
    public function kitchenTicket(Request $request, RestaurantOrder $order, $batch = null): View
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

        return view('prints.kitchen', compact('order', 'batch', 'items', 'isReprint'));
    }

    /**
     * Pre-cuenta informativa para el cliente (58 mm).
     */
    public function preBill(RestaurantOrder $order): View
    {
        $order->load([
            'table',
            'user',
            'customer',
            'items' => function ($q) {
                $q->where('status', '!=', 'cancelled')->with(['product', 'additions.addition']);
            },
        ]);

        return view('prints.pre-bill', compact('order'));
    }

    /**
     * Tirilla de despacho y domicilio (58 mm).
     */
    public function dispatchTicket(RestaurantOrder $order): View
    {
        $order->load([
            'user',
            'customer',
            'items' => function ($q) {
                $q->where('status', '!=', 'cancelled')->with(['product', 'additions.addition']);
            },
        ]);

        return view('prints.dispatch', compact('order'));
    }
}
