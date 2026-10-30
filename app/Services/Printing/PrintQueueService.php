<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Models\RestaurantOrder;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PrintQueueService
{
    public function __construct(
        protected EscPosTicketBuilder $ticketBuilder
    ) {}

    /**
     * Encola comanda de cocina / barra.
     */
    public function enqueueKitchenTicket(RestaurantOrder $order, ?int $batchNumber = null, bool $isReprint = false): ?PrintJob
    {
        if (!config('services.print.enabled', true)) {
            return null;
        }

        try {
            $base64 = $this->ticketBuilder->comanda($order, $batchNumber, $isReprint);

            return PrintJob::create([
                'tipo'          => PrintJob::TIPO_COMANDA,
                'referencia_id' => $order->id,
                'contenido'     => $base64,
                'estado'        => PrintJob::ESTADO_PENDIENTE,
                'intentos'      => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al encolar comanda de cocina ESC/POS: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Encola comprobante / factura de venta.
     */
    public function enqueueSaleReceipt(Sale $sale): ?PrintJob
    {
        if (!config('services.print.enabled', true)) {
            return null;
        }

        try {
            $base64 = $this->ticketBuilder->factura($sale);

            return PrintJob::create([
                'tipo'          => PrintJob::TIPO_FACTURA,
                'referencia_id' => $sale->id,
                'contenido'     => $base64,
                'estado'        => PrintJob::ESTADO_PENDIENTE,
                'intentos'      => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al encolar factura de venta ESC/POS: ' . $e->getMessage(), [
                'sale_id' => $sale->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Encola tirilla de despacho (Domicilio o Para Llevar).
     */
    public function enqueueDispatchTicket(RestaurantOrder $order): ?PrintJob
    {
        if (!config('services.print.enabled', true)) {
            return null;
        }

        try {
            $base64 = $this->ticketBuilder->despacho($order);

            return PrintJob::create([
                'tipo'          => PrintJob::TIPO_DESPACHO,
                'referencia_id' => $order->id,
                'contenido'     => $base64,
                'estado'        => PrintJob::ESTADO_PENDIENTE,
                'intentos'      => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al encolar tirilla de despacho ESC/POS: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Encola pre-cuenta informativa (total o parcial).
     */
    public function enqueuePreBill(RestaurantOrder $order, ?Collection $selectedItems = null, array $extraData = []): ?PrintJob
    {
        if (!config('services.print.enabled', true)) {
            return null;
        }

        try {
            $isPartial = $selectedItems !== null && $selectedItems->isNotEmpty();
            $base64 = $this->ticketBuilder->precuenta($order, $selectedItems, $extraData);

            return PrintJob::create([
                'tipo'          => $isPartial ? PrintJob::TIPO_PRECUENTA_PARCIAL : PrintJob::TIPO_PRECUENTA,
                'referencia_id' => $order->id,
                'contenido'     => $base64,
                'estado'        => PrintJob::ESTADO_PENDIENTE,
                'intentos'      => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al encolar pre-cuenta ESC/POS: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Encola comprobante de cuadre de caja diario.
     */
    public function enqueueCashRegister(array $summaryData): ?PrintJob
    {
        if (!config('services.print.enabled', true)) {
            return null;
        }

        try {
            $base64 = $this->ticketBuilder->cuadreCaja($summaryData);

            return PrintJob::create([
                'tipo'          => PrintJob::TIPO_CUADRE,
                'referencia_id' => null,
                'contenido'     => $base64,
                'estado'        => PrintJob::ESTADO_PENDIENTE,
                'intentos'      => 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al encolar cuadre de caja ESC/POS: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return null;
        }
    }
}

