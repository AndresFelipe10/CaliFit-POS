<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Customer;

class CustomerAiTools
{
    /**
     * List customers (read-only with data minimization and full Spanish fields).
     */
    public function list(array $filters = []): AiQueryResult
    {
        $query = Customer::where('is_active', true);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('document', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('name')->limit(15)->get();
        $count = $customers->count();

        if ($count === 0) {
            return new AiQueryResult(
                intent: 'list_customers',
                summary: 'No se encontraron clientes activos registrados con los criterios solicitados.',
                data: []
            );
        }

        $data = $customers->map(function ($c) {
            $salesCount = $c->sales()->where('status', 'completed')->count();
            $balance = $c->credit_balance;

            return [
                'Nombre' => $c->name,
                'Documento' => $c->document ?? 'N/A',
                'Teléfono' => $c->phone ?? 'N/A',
                'Dirección' => $c->address ?? 'N/A',
                'Compras Realizadas' => $salesCount > 0 ? "{$salesCount} ventas" : '0 compras',
                'Saldo en Cartera' => $balance > 0 ? ('$' . number_format($balance, 0, ',', '.') . ' COP') : '$0 COP (Al día)',
            ];
        })->toArray();

        return new AiQueryResult(
            intent: 'list_customers',
            summary: "Se encontraron {$count} clientes activos registrados en el directorio de SaborCaliFit.",
            data: $data
        );
    }

    /**
     * Count active customers (read-only).
     */
    public function count(): AiQueryResult
    {
        $total = Customer::where('is_active', true)->count();

        return new AiQueryResult(
            intent: 'count_customers',
            summary: "SaborCaliFit cuenta actualmente con {$total} clientes activos registrados en el directorio.",
            data: [
                'Clientes Activos Registrados' => $total,
                'total_active_customers' => $total,
            ]
        );
    }
}
