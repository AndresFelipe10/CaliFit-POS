<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\RestaurantTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetProductionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:reset-production {--force : Forzar la ejecución sin confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Vaciado atómico de tablas operativas y de prueba para salida a producción real de CaliFit POS';

    /**
     * Tablas operativas a vaciar en orden de dependencia inversa.
     *
     * @var array<string>
     */
    protected array $operationalTables = [
        'print_jobs',
        'restaurant_order_item_additions',
        'restaurant_order_splits',
        'restaurant_order_items',
        'restaurant_orders',
        'customer_credit_payments',
        'sale_detail_additions',
        'sale_details',
        'sale_payments',
        'sales',
        'inventory_movements',
        'expenses',
        'receta_detalle',
        'recetas',
        'producto_adicion',
        'adicion_detalle',
        'adiciones',
        'products',
        'categories',
        'suppliers',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('====================================================================');
        $this->warn('  CALIFIT POS — REINICIO TOTAL PARA SALIDA A PRODUCCIÓN EN VIVO     ');
        $this->warn('====================================================================');
        $this->line('Este comando vaciará todas las ventas, comandas, créditos, gastos,');
        $this->line('movimientos de inventario, recetas, productos y proveedores de prueba.');
        $this->line('Se PRESERVARÁN los usuarios, roles y el cliente Consumidor Final.');
        $this->newLine();

        if (! $this->option('force')) {
            $confirmed = $this->confirm('¿Está ABSOLUTAMENTE SEGURO de reiniciar la base de datos de producción?', false);
            if (! $confirmed) {
                $this->info('Operación cancelada por el usuario.');
                return self::SUCCESS;
            }
        }

        $this->info('Iniciando proceso de limpieza...');
        $driver = DB::getDriverName();

        // 1. Desactivar validación de claves foráneas
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        try {
            // 2. Vaciar tablas operativas
            foreach ($this->operationalTables as $table) {
                if (Schema::hasTable($table)) {
                    $this->line("  -> Vaciando tabla: {$table}...");
                    DB::table($table)->delete();

                    // Reiniciar AUTO_INCREMENT
                    if ($driver === 'mysql') {
                        DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1;");
                    }
                }
            }

            // 3. Limpiar clientes preservando Consumidor Final (222222222222)
            if (Schema::hasTable('customers')) {
                $this->line('  -> Limpiando clientes (conservando Consumidor Final)...');
                DB::table('customers')->where('document', '!=', '222222222222')->delete();
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE `customers` AUTO_INCREMENT = 2;");
                }
            }

            // 4. Asegurar existencia del cliente Consumidor Final
            Customer::firstOrCreate(
                ['document' => '222222222222'],
                [
                    'name' => 'Consumidor Final',
                    'email' => 'cliente@califit.com',
                    'phone' => '3000000000',
                    'address' => 'Cali, Colombia',
                    'city' => 'Cali',
                ]
            );

            // 5. Restablecer estado de mesas físicas del salón a 'available'
            if (Schema::hasTable('restaurant_tables')) {
                $this->line("  -> Restableciendo estado de mesas a 'available'...");
                RestaurantTable::query()->update(['status' => 'available']);
            }

            // 6. Reset secuencias SQLite si aplica
            if ($driver === 'sqlite') {
                if (Schema::hasTable('sqlite_sequence')) {
                    DB::table('sqlite_sequence')
                        ->whereIn('name', $this->operationalTables)
                        ->delete();
                }
            }

            // 7. Limpiar caché del sistema
            cache()->flush();

            $this->info('Limpieza y restablecimiento completados exitosamente.');
        } catch (\Throwable $e) {
            $this->error('Error durante el restablecimiento: ' . $e->getMessage());
            return self::FAILURE;
        } finally {
            // Reactivar validación de claves foráneas
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        }

        $this->newLine();
        $this->info('✓ Base de datos lista para operar en producción real en SaborCaliFit Bochalema.');
        return self::SUCCESS;
    }
}
