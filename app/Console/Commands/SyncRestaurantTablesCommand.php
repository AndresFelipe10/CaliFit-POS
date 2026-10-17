<?php

namespace App\Console\Commands;

use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncRestaurantTablesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'restaurant:sync-tables {--business= : ID opcional de negocio para sincronizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sanea y sincroniza el estado de mesas y comandas huérfanas o inconsistentes en la base de datos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $businessId = $this->option('business');

        $this->info('Iniciando saneamiento y sincronización de mesas y comandas...');

        $ordersQuery = RestaurantOrder::withoutGlobalScopes();
        $tablesQuery = RestaurantTable::withoutGlobalScopes();

        if ($businessId) {
            $ordersQuery->where('business_id', (int) $businessId);
            $tablesQuery->where('business_id', (int) $businessId);
        }

        $repairedOrders = 0;
        $syncedTables = 0;

        DB::transaction(function () use ($ordersQuery, $tablesQuery, &$repairedOrders, &$syncedTables) {
            // 1. Sanear comandas en falso cierre ('billed' sin venta asociada o con saldo pendiente)
            $corruptedOrders = (clone $ordersQuery)
                ->where('status', 'billed')
                ->whereNull('sale_id')
                ->lockForUpdate()
                ->get();

            foreach ($corruptedOrders as $order) {
                if ($order->pendingBalance() > 0) {
                    $newStatus = $order->items()->where('status', 'kitchen')->exists() ? 'in_kitchen' : 'open';
                    $order->update(['status' => $newStatus]);
                    $repairedOrders++;
                    $this->warn("Comanda #{$order->order_number} (ID {$order->id}) restaurada a '{$newStatus}' (Saldo pendiente: \${$order->pendingBalance()}).");
                }
            }

            // 2. Sincronizar mesas según sus comandas activas
            $tables = (clone $tablesQuery)->lockForUpdate()->get();

            foreach ($tables as $table) {
                // Buscar si tiene comanda realmente activa
                $activeOrder = RestaurantOrder::withoutGlobalScopes()
                    ->where('table_id', $table->id)
                    ->whereNotIn('status', ['billed', 'closed', 'cancelled'])
                    ->latest('id')
                    ->first();

                if (! $activeOrder) {
                    if ($table->status !== 'available') {
                        $oldStatus = $table->status;
                        $table->update(['status' => 'available']);
                        $syncedTables++;
                        $this->line("Mesa '{$table->name}' (ID {$table->id}, Negocio #{$table->business_id}): '{$oldStatus}' -> 'available' (Sin comanda activa).");
                    }
                } else {
                    $targetStatus = ! empty($activeOrder->precount_printed_at) ? 'billed' : 'occupied';
                    if ($table->status !== $targetStatus) {
                        $oldStatus = $table->status;
                        $table->update(['status' => $targetStatus]);
                        $syncedTables++;
                        $this->line("Mesa '{$table->name}' (ID {$table->id}, Negocio #{$table->business_id}): '{$oldStatus}' -> '{$targetStatus}' (Comanda #{$activeOrder->order_number}).");
                    }
                }
            }
        });

        $this->newLine();
        $this->info("Sincronización finalizada exitosamente.");
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Comandas reparadas', $repairedOrders],
                ['Mesas sincronizadas / liberadas', $syncedTables],
            ]
        );

        return self::SUCCESS;
    }
}
