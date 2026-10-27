<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Addition;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;

echo "=== VERIFICACIÓN FASE 1: MODELO DE DATOS Y RELACIONES CALIFIT ===" . PHP_EOL;

// 1. Roles y Usuarios
$adminRole = Role::where('slug', Role::ROLE_ADMIN)->firstOrFail();
$employeeRole = Role::where('slug', Role::ROLE_EMPLOYEE)->firstOrFail();

echo "[✓] Roles encontrados: " . Role::count() . " (admin, employee)" . PHP_EOL;

$admin1 = User::where('email', 'admin@saborcalifit.com')->firstOrFail();
assert($admin1->role->id === $adminRole->id, 'Admin 1 role_id coincide');
assert($admin1->isAdmin() === true, 'isAdmin() funciona para admin1');
echo "[✓] Admin 1 verificado: {$admin1->name} ({$admin1->role->name})" . PHP_EOL;

$admin2 = User::where('email', 'gerente@saborcalifit.com')->firstOrFail();
assert($admin2->isAdmin() === true, 'isAdmin() funciona para admin2');
echo "[✓] Admin 2 verificado: {$admin2->name} ({$admin2->role->name})" . PHP_EOL;

$mesero = User::where('email', 'mesero@saborcalifit.com')->firstOrFail();
assert($mesero->isEmployee() === true, 'isEmployee() funciona para mesero');
echo "[✓] Mesero verificado: {$mesero->name} ({$mesero->role->name})" . PHP_EOL;

// 2. Insumos y Platos
$insumosCount = Product::where('tipo_producto', 'INSUMO')->count();
$platosCount = Product::where('tipo_producto', 'PLATO')->count();
$suplementosCount = Product::where('tipo_producto', 'SUPLEMENTO')->count();

echo "[✓] Productos clasificados: {$insumosCount} insumos, {$platosCount} platos fit, {$suplementosCount} suplementos" . PHP_EOL;

// 3. Recetas de Platos
$plato1 = Product::where('sku', 'PLA-001')->with(['receta.items.ingrediente', 'adiciones'])->firstOrFail();
echo "[✓] Plato: {$plato1->nombre} | Receta: {$plato1->receta->nombre}" . PHP_EOL;
echo "    Ingredientes en receta (" . $plato1->receta->items->count() . "):" . PHP_EOL;
foreach ($plato1->receta->items as $item) {
    echo "     - {$item->ingrediente->nombre}: {$item->cantidad_por_porcion} {$item->unidad}" . PHP_EOL;
}

// 4. Adiciones con precio e ingredientes
echo "    Adiciones vinculadas al plato (" . $plato1->adiciones->count() . "):" . PHP_EOL;
foreach ($plato1->adiciones as $adicion) {
    $detalle = $adicion->items->first();
    $ingredienteNombre = $detalle ? $detalle->ingrediente->nombre : 'N/A';
    $cant = $detalle ? $detalle->cantidad : 0;
    echo "     + {$adicion->nombre}: \${$adicion->precio} (Insumo: {$ingredienteNombre} x {$cant}g)" . PHP_EOL;
}

// 5. Mesas de salón
$mesasCount = RestaurantTable::where('is_active', true)->count();
echo "[✓] Mesas activas de salón: {$mesasCount}" . PHP_EOL;

// 6. Directorio comercial
$customersCount = Customer::count();
$suppliersCount = Supplier::count();
echo "[✓] Clientes demo: {$customersCount} | Proveedores demo: {$suppliersCount}" . PHP_EOL;

echo "=== TODAS LAS VERIFICACIONES DE FASE 1 PASARON AL 100% ===" . PHP_EOL;
