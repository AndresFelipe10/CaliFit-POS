<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleAiTools
{
    /**
     * Sales summary for today or current month (read-only).
     */
    public function summary(array $filters = []): AiQueryResult
    {
        $period = $filters['period'] ?? 'today';

        if ($period === 'month') {
            $dateFrom = Carbon::now()->startOfMonth();
            $dateTo = Carbon::now()->endOfMonth();
            $periodLabel = "este mes (" . Carbon::now()->translatedFormat('F Y') . ")";
        } else {
            $dateFrom = Carbon::today()->startOfDay();
            $dateTo = Carbon::today()->endOfDay();
            $periodLabel = "el día de hoy";
        }

        $sales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $totalRevenue = (float) $sales->sum('total');
        $count = $sales->count();
        $avgTicket = $count > 0 ? round($totalRevenue / $count, 2) : 0.0;
        $periodSpanish = match ($period) {
            'month' => 'Este Mes',
            'yesterday' => 'Ayer',
            'week' => 'Esta Semana',
            default => 'Hoy',
        };

        return new AiQueryResult(
            intent: 'sales_summary',
            summary: "En {$periodLabel} se han registrado {$count} ventas cerradas por un valor total de $" .
                number_format($totalRevenue, 0, ',', '.') . " COP con un ticket promedio de $" .
                number_format($avgTicket, 0, ',', '.') . " COP.",
            data: [
                'Período' => $periodSpanish,
                'Ventas / Órdenes' => $count,
                'Ingresos Totales' => '$' . number_format($totalRevenue, 0, ',', '.') . ' COP',
                'Ticket Promedio' => '$' . number_format($avgTicket, 0, ',', '.') . ' COP',
                'period' => $period,
                'sales_count' => $count,
                'total_revenue' => '$' . number_format($totalRevenue, 0, ',', '.'),
                'average_ticket' => '$' . number_format($avgTicket, 0, ',', '.'),
            ]
        );
    }

    /**
     * Sales by date range or period (read-only).
     */
    public function byPeriod(array $filters = []): AiQueryResult
    {
        $dateFrom = !empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : Carbon::now()->startOfMonth();
        $dateTo = !empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : Carbon::now()->endOfDay();

        $sales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $totalRevenue = (float) $sales->sum('total');
        $count = $sales->count();

        $fromFormatted = $dateFrom->format('d/m/Y');
        $toFormatted = $dateTo->format('d/m/Y');

        return new AiQueryResult(
            intent: 'sales_by_period',
            summary: "Entre {$fromFormatted} y {$toFormatted} se acumularon {$count} ventas cerradas por $" .
                number_format($totalRevenue, 0, ',', '.') . " COP.",
            data: [
                'Fecha Desde' => $fromFormatted,
                'Fecha Hasta' => $toFormatted,
                'Ventas / Órdenes' => $count,
                'Ingresos Totales' => '$' . number_format($totalRevenue, 0, ',', '.') . ' COP',
            ]
        );
    }

    /**
     * Sales segmented by service channel (table, delivery, takeout, retail) (read-only).
     */
    public function byChannel(array $filters = []): AiQueryResult
    {
        $dateFrom = Carbon::today()->startOfDay();
        $dateTo = Carbon::today()->endOfDay();

        $sales = Sale::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        $channelMap = [
            'table' => ['name' => 'Salón (Mesas)', 'total' => 0.0, 'count' => 0],
            'delivery' => ['name' => 'Domicilios', 'total' => 0.0, 'count' => 0],
            'takeout' => ['name' => 'Para Llevar', 'total' => 0.0, 'count' => 0],
            'retail' => ['name' => 'Mostrador POS / Retail', 'total' => 0.0, 'count' => 0],
        ];

        foreach ($sales as $sale) {
            $rawType = $sale->order_type ?? $sale->sale_type ?? 'retail';
            $type = match (strtolower(trim((string) $rawType))) {
                'table', 'restaurant' => 'table',
                'delivery' => 'delivery',
                'takeout' => 'takeout',
                'retail', 'direct', '' => 'retail',
                default => $rawType,
            };

            if (!isset($channelMap[$type])) {
                $channelMap[$type] = ['name' => ucfirst($type), 'total' => 0.0, 'count' => 0];
            }
            $channelMap[$type]['total'] += (float) $sale->total;
            $channelMap[$type]['count']++;
        }

        $breakdown = [];
        foreach ($channelMap as $key => $info) {
            $breakdown[] = [
                'Canal de Venta' => $info['name'],
                'Ventas / Órdenes' => $info['count'],
                'Total' => '$' . number_format($info['total'], 0, ',', '.') . ' COP',
                'channel' => $info['name'],
                'sales_count' => $info['count'],
                'total' => '$' . number_format($info['total'], 0, ',', '.'),
            ];
        }

        return new AiQueryResult(
            intent: 'sales_by_channel',
            summary: "Balance de ventas por canal hoy: Salón: $" . number_format($channelMap['table']['total'] ?? 0, 0, ',', '.') . 
                     " ({$channelMap['table']['count']} órdenes) | Domicilios: $" . number_format($channelMap['delivery']['total'] ?? 0, 0, ',', '.') . 
                     " ({$channelMap['delivery']['count']} órdenes) | Para Llevar: $" . number_format($channelMap['takeout']['total'] ?? 0, 0, ',', '.') . 
                     " ({$channelMap['takeout']['count']} órdenes) | Mostrador: $" . number_format($channelMap['retail']['total'] ?? 0, 0, ',', '.') . 
                     " ({$channelMap['retail']['count']} ventas).",
            data: $breakdown
        );
    }

    /**
     * Top selling products ranking (read-only).
     */
    public function topSelling(array $filters = []): AiQueryResult
    {
        $limit = max(1, min((int) ($filters['limit'] ?? 5), 20));

        $top = SaleDetail::select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('SUM(subtotal) as total_revenue'))
            ->whereHas('sale', function ($q) {
                $q->where('status', 'completed');
            })
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->with('product')
            ->get();

        if ($top->isEmpty()) {
            return new AiQueryResult(
                intent: 'top_selling_products',
                summary: 'Aún no hay suficientes ventas pagadas para generar el ranking de productos más vendidos.',
                data: []
            );
        }

        $data = $top->map(function ($item, $index) {
            $productName = $item->product ? $item->product->nombre : "Producto #{$item->product_id}";
            $productType = $item->product ? $item->product->tipo_producto : 'N/A';

            return [
                'Puesto' => '#' . ($index + 1),
                'Producto' => $productName,
                'Tipo' => $productType,
                'Unidades Vendidas' => (float) $item->total_quantity,
                'Ingresos Generados' => '$' . number_format((float) $item->total_revenue, 0, ',', '.') . ' COP',
            ];
        })->toArray();

        $topName = $data[0]['Producto'] ?? 'Producto Líder';

        return new AiQueryResult(
            intent: 'top_selling_products',
            summary: "El producto más vendido en SaborCaliFit es '{$topName}'. Se consultaron los top {$top->count()} productos por volumen de venta.",
            data: $data
        );
    }
}
