<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\CustomerCreditPayment;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\SalePayment;
use Carbon\Carbon;

class RestaurantAiTools
{
    /**
     * Active orders in dining hall and dispatch (read-only).
     */
    public function activeOrders(): AiQueryResult
    {
        $openOrders = RestaurantOrder::whereIn('status', ['open', 'in_kitchen', 'dispatched', 'delivered', 'billed'])
            ->with(['table', 'items'])
            ->get();

        $occupiedTables = RestaurantTable::whereIn('status', ['occupied', 'billed'])->count();
        $totalTables = RestaurantTable::count();

        $data = $openOrders->map(fn($o) => [
            'Folio Comanda' => $o->order_number,
            'Ubicación / Mesa' => $o->table ? $o->table->name : 'Despacho',
            'Estado' => match($o->status) {
                'open' => 'Abierta / Preparación',
                'in_kitchen' => 'En Cocina',
                'dispatched' => 'En Despacho',
                'delivered' => 'Entregada',
                'billed' => 'En Cobro / Pre-cuenta',
                'closed' => 'Cerrada / Facturada',
                'cancelled' => 'Cancelada',
                default => $o->status,
            },
            'Cantidad de Platos' => $o->items->count(),
            'Hora Apertura' => $o->created_at ? $o->created_at->format('H:i') : '—',
        ])->toArray();

        return new AiQueryResult(
            intent: 'active_orders',
            summary: "Hay {$openOrders->count()} comandas activas en proceso. Mesas ocupadas: {$occupiedTables} de {$totalTables} mesas en el salón.",
            data: $data
        );
    }

    /**
     * Daily cash register summary by payment methods (read-only).
     */
    public function dailyCashSummary(): AiQueryResult
    {
        $start = Carbon::today()->startOfDay();
        $end = Carbon::today()->endOfDay();

        // 1. Pagos directos de ventas completadas (no crédito) del día
        $directPayments = SalePayment::whereHas('sale', function ($q) use ($start, $end) {
                $q->where('status', 'completed')
                  ->where('is_credit', false)
                  ->whereBetween('created_at', [$start, $end]);
            })
            ->get();

        // 2. Abonos de cartera (CustomerCreditPayment) recibidos hoy
        $creditCollections = CustomerCreditPayment::whereBetween('paid_at', [$start, $end])
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed'))
            ->get();

        $matchesMethod = function ($payment, string $method): bool {
            $val = $payment->method instanceof \BackedEnum ? $payment->method->value : (string) $payment->method;
            return strtolower($val) === strtolower($method);
        };

        $cash = (float) $directPayments->filter(fn ($p) => $matchesMethod($p, 'cash'))->sum('amount')
            + (float) $creditCollections->filter(fn ($p) => $matchesMethod($p, 'cash'))->sum('amount');

        $transfer = (float) $directPayments->filter(fn ($p) => $matchesMethod($p, 'transfer'))->sum('amount')
            + (float) $creditCollections->filter(fn ($p) => $matchesMethod($p, 'transfer'))->sum('amount');

        $card = (float) $directPayments->filter(fn ($p) => $matchesMethod($p, 'card'))->sum('amount')
            + (float) $creditCollections->filter(fn ($p) => $matchesMethod($p, 'card'))->sum('amount');

        $other = (float) $directPayments->filter(fn ($p) => $matchesMethod($p, 'other'))->sum('amount')
            + (float) $creditCollections->filter(fn ($p) => $matchesMethod($p, 'other'))->sum('amount');

        $total = $cash + $transfer + $card + $other;

        return new AiQueryResult(
            intent: 'daily_cash_summary',
            summary: "Resumen de caja de hoy: Total recaudado $" . number_format($total, 0, ',', '.') . 
                " COP (Efectivo: $" . number_format($cash, 0, ',', '.') . 
                " | Transferencias digitales: $" . number_format($transfer, 0, ',', '.') . 
                " | Tarjetas/Datáfono: $" . number_format($card, 0, ',', '.') . 
                ($other > 0 ? " | Otros medios: $" . number_format($other, 0, ',', '.') : "") . ").",
            data: [
                'Total Recaudado' => '$' . number_format($total, 0, ',', '.') . ' COP',
                'Efectivo' => '$' . number_format($cash, 0, ',', '.') . ' COP',
                'Transferencias Digitales' => '$' . number_format($transfer, 0, ',', '.') . ' COP',
                'Datáfono / Tarjetas' => '$' . number_format($card, 0, ',', '.') . ' COP',
                'Otros Medios' => '$' . number_format($other, 0, ',', '.') . ' COP',
                'total_collected' => '$' . number_format($total, 0, ',', '.'),
                'cash' => '$' . number_format($cash, 0, ',', '.'),
                'digital_transfers' => '$' . number_format($transfer, 0, ',', '.'),
                'cards' => '$' . number_format($card, 0, ',', '.'),
                'other' => '$' . number_format($other, 0, ',', '.'),
            ]
        );
    }
}
