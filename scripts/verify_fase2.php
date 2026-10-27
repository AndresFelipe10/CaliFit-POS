<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Exceptions\InsufficientInventoryException;
use App\Models\Addition;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Kitchen\RecipeService;
use App\Services\Sales\SaleService;

echo "=== VERIFICACIÓN FASE 2: CAPA DE SERVICIOS Y TRANSACCIONALIDAD ATÓMICA ===" . PHP_EOL;

$inventoryService = new InventoryService();
$recipeService = new RecipeService();
$saleService = new SaleService($inventoryService, $recipeService);

$mesero = User::where('email', 'mesero@saborcalifit.com')->firstOrFail();
$cliente = Customer::where('document', '1144123456')->firstOrFail();
$plato1 = Product::where('sku', 'PLA-001')->firstOrFail(); // Ejercicio 200g con frijoles
$adicionCarne = Addition::where('nombre', 'Carne adicional 120g')->firstOrFail();

$carne = Product::where('sku', 'INS-001')->firstOrFail();
$initialCarneStock = (float) $carne->stock;

echo "[1] Stock inicial carne: {$initialCarneStock} g" . PHP_EOL;

// 1. Venta exitosa con plato + adición
$saleData = [
    'customer_id' => $cliente->id,
    'order_type' => 'retail',
    'items' => [
        [
            'product_id' => $plato1->id,
            'quantity' => 1.0,
            'additions' => [
                $adicionCarne->id => 1,
            ],
        ],
    ],
    'payments' => [
        [
            'method' => 'cash',
            'amount' => 33000.00, // 25,000 plato + 8,000 adición
            'cash_received' => 50000.00,
        ],
    ],
];

$sale = $saleService->processSale($saleData, $mesero);
echo "[✓] Venta procesada exitosamente: {$sale->invoice_number} | Total: \${$sale->total} | Vuelto: \${$sale->payments->first()->change_given}" . PHP_EOL;

// Insumo consumido: 200g base + 120g adición = 320g
$postSaleCarneStock = (float) $carne->fresh()->stock;
$expectedPostStock = $initialCarneStock - 320.0;
assert($postSaleCarneStock === $expectedPostStock, "Stock de carne descontó exactamente 320g");
echo "[✓] Stock de carne actualizado: {$postSaleCarneStock} g (descontó 320g exactos)" . PHP_EOL;

// 2. Anulación simétrica
$cancelledSale = $saleService->cancelSale($sale, $mesero, 'Prueba de anulación simétrica Fase 2');
$revertedCarneStock = (float) $carne->fresh()->stock;
assert($revertedCarneStock === $initialCarneStock, "Stock de carne revertido exactamente al valor inicial");
echo "[✓] Venta anulada: {$cancelledSale->invoice_number} (Estado: {$cancelledSale->status})" . PHP_EOL;
echo "[✓] Stock de carne restaurado simétricamente al 100%: {$revertedCarneStock} g" . PHP_EOL;

// 3. Prueba de Cero Stock Negativo (Intento de compra por encima de stock)
$hugeQuantity = 9999999.0;
$failData = [
    'items' => [
        [
            'product_id' => $plato1->id,
            'quantity' => $hugeQuantity,
        ],
    ],
];

$rejected = false;
try {
    $saleService->processSale($failData, $mesero);
} catch (InsufficientInventoryException $e) {
    $rejected = true;
    echo "[✓] Cero Stock Negativo validado: Venta rechazada atómicamente con mensaje -> '{$e->getMessage()}'" . PHP_EOL;
}

assert($rejected === true, 'La venta con stock insuficiente debió ser rechazada.');

echo "=== TODAS LAS PRUEBAS DE INTEGRACIÓN DE FASE 2 PASARON AL 100% ===" . PHP_EOL;
