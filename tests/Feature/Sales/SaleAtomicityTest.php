<?php

namespace Tests\Feature\Sales;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Addition;
use App\Models\AdditionItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Kitchen\RecipeService;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected SaleService $saleService;
    protected InventoryService $inventoryService;
    protected RecipeService $recipeService;

    protected User $cajero;
    protected User $admin;
    protected Customer $cliente;
    protected Category $categoriaFitness;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();
        $this->recipeService = new RecipeService();
        $this->saleService = new SaleService($this->inventoryService, $this->recipeService);

        $roleAdmin = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador']
        );

        $roleEmployee = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado POS']
        );

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Gerente CaliFit',
            'email' => 'gerente.atomicity@test.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->cajero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero CaliFit',
            'email' => 'cajero.atomicity@test.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->cliente = Customer::create([
            'name' => 'Cliente Atómico',
            'document' => '1005001122',
            'email' => 'cliente.atomicity@test.com',
            'phone' => '3120001122',
        ]);

        $this->categoriaFitness = Category::firstOrCreate(
            ['slug' => 'almuerzos-fitness'],
            ['name' => 'Almuerzos Fitness']
        );
    }

    /**
     * 1. Fallo en insumo secundario con Rollback Total:
     * Plato compuesto: Arroz (62.5 g) + Pollo (200 g) + Ensalada (50 g).
     * Arroz y Pollo tienen stock abundante (1000 g cada uno), pero Ensalada tiene 0 g.
     * Resultado exigido:
     * - Falla la transacción arrojando InsufficientInventoryException.
     * - Stock de Arroz y Pollo permanece intacto (1000 g exactos).
     * - Cero registros en sales, sale_details e inventory_movements.
     */
    public function test_secondary_ingredient_failure_triggers_complete_transactional_rollback(): void
    {
        $pollo = Product::create([
            'sku' => 'INS-POLLO-TEST',
            'nombre' => 'Pechuga de Pollo Fresca',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 1000.0,
            'stock_minimo' => 200.0,
            'precio_compra' => 18.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        $arroz = Product::create([
            'sku' => 'INS-ARROZ-TEST',
            'nombre' => 'Arroz Integral Cocido',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 1000.0,
            'stock_minimo' => 200.0,
            'precio_compra' => 6.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        // Ensalada sin stock (0.0 g)
        $ensalada = Product::create([
            'sku' => 'INS-ENSALADA-TEST',
            'nombre' => 'Ensalada Verde de la Casa',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 0.0,
            'stock_minimo' => 100.0,
            'precio_compra' => 10.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        $platoCompuesto = Product::create([
            'sku' => 'PLATO-BOWL-TRIO',
            'nombre' => 'Bowl Trío Proteico',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 0.0,
            'stock_minimo' => 0.0,
            'precio_compra' => 5000.0,
            'precio_venta' => 25000.0,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $platoCompuesto->id,
            'nombre' => 'Receta Bowl Trío',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $pollo->id,
            'cantidad_por_porcion' => 200.0,
            'unidad' => 'GRAMO',
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $arroz->id,
            'cantidad_por_porcion' => 62.5,
            'unidad' => 'GRAMO',
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $ensalada->id,
            'cantidad_por_porcion' => 50.0,
            'unidad' => 'GRAMO',
        ]);

        $saleData = [
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $platoCompuesto->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 25000.0,
                    'amount_tendered' => 25000.0,
                ],
            ],
        ];

        $exceptionFired = false;
        try {
            $this->saleService->processSale($saleData, $this->cajero);
        } catch (InsufficientInventoryException $e) {
            $exceptionFired = true;
            $this->assertEquals('Ensalada Verde de la Casa', $e->getProductName());
            $this->assertEquals(0.0, $e->getAvailableStock());
            $this->assertEquals(50.0, $e->getRequiredQuantity());
        }

        $this->assertTrue($exceptionFired, 'Debió fallar con InsufficientInventoryException');

        // Verificación estricta de Rollback Atómico total
        $this->assertEquals(1000.0, (float) $pollo->fresh()->stock, 'El stock de pollo debe permanecer intacto en 1000 g');
        $this->assertEquals(1000.0, (float) $arroz->fresh()->stock, 'El stock de arroz debe permanecer intacto en 1000 g');
        $this->assertEquals(0.0, (float) $ensalada->fresh()->stock, 'El stock de ensalada permanece en 0 g');

        $this->assertEquals(0, Sale::count(), 'No debe crearse ningún registro en sales');
        $this->assertEquals(0, SaleDetail::count(), 'No debe crearse ningún registro en sale_details');
        $this->assertEquals(0, InventoryMovement::count(), 'No debe registrarse ningún movimiento en inventory_movements');
    }

    /**
     * 2. Reversión Simétrica Profunda en Anulación:
     * Venta multi-ítem con platos compuestos y adiciones.
     * Al anular vía SaleService::cancelSale(), cada insumo (base y adiciones) se reintegra
     * exactamente a su inventario con movimiento DEVOLUCION_ANULACION.
     */
    public function test_deep_symmetric_reversion_on_sale_cancellation(): void
    {
        // Insumos base
        $carne = Product::create([
            'sku' => 'INS-CARNE-BASE',
            'nombre' => 'Carne Molida Especial',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 1000.0,
            'stock_minimo' => 200.0,
            'precio_compra' => 20.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        $arroz = Product::create([
            'sku' => 'INS-ARROZ-BASE',
            'nombre' => 'Arroz Integral Especial',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 1000.0,
            'stock_minimo' => 200.0,
            'precio_compra' => 8.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        $aguacate = Product::create([
            'sku' => 'INS-AGUACATE-BASE',
            'nombre' => 'Aguacate Porción Base',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 500.0,
            'stock_minimo' => 100.0,
            'precio_compra' => 22.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        // Plato 1: Almuerzo Fitness (200 g Carne + 62.5 g Arroz)
        $platoAlmuerzo = Product::create([
            'sku' => 'PLA-ALMUERZO-FIT',
            'nombre' => 'Almuerzo Fitness Alto en Proteína',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 0.0,
            'stock_minimo' => 0.0,
            'precio_compra' => 5000.0,
            'precio_venta' => 26000.0,
            'activo' => true,
        ]);

        $recetaAlmuerzo = Recipe::create([
            'producto_plato_id' => $platoAlmuerzo->id,
            'nombre' => 'Receta Almuerzo Fitness',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $recetaAlmuerzo->id,
            'ingrediente_id' => $carne->id,
            'cantidad_por_porcion' => 200.0,
            'unidad' => 'GRAMO',
        ]);

        RecipeItem::create([
            'receta_id' => $recetaAlmuerzo->id,
            'ingrediente_id' => $arroz->id,
            'cantidad_por_porcion' => 62.5,
            'unidad' => 'GRAMO',
        ]);

        // Plato 2: Bowl Solo Carne (150 g Carne)
        $platoBowlSoloCarne = Product::create([
            'sku' => 'PLA-BOWL-CARNE',
            'nombre' => 'Bowl Keto Solo Carne',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->categoriaFitness->id,
            'stock' => 0.0,
            'stock_minimo' => 0.0,
            'precio_compra' => 3500.0,
            'precio_venta' => 19000.0,
            'activo' => true,
        ]);

        $recetaBowl = Recipe::create([
            'producto_plato_id' => $platoBowlSoloCarne->id,
            'nombre' => 'Receta Bowl Solo Carne',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $recetaBowl->id,
            'ingrediente_id' => $carne->id,
            'cantidad_por_porcion' => 150.0,
            'unidad' => 'GRAMO',
        ]);

        // Adición: Aguacate Extra (50 g de Aguacate, precio $4000)
        $adicionAguacate = Addition::create([
            'nombre' => 'Porción Aguacate Extra',
            'precio' => 4000.0,
            'activo' => true,
        ]);

        AdditionItem::create([
            'adicion_id' => $adicionAguacate->id,
            'ingrediente_id' => $aguacate->id,
            'cantidad' => 50.0,
        ]);

        $platoAlmuerzo->additions()->attach($adicionAguacate->id);

        // Venta: 2 x Plato Almuerzo (con 1 adición de aguacate cada uno) + 1 x Bowl Solo Carne
        // Consumos calculados:
        // - Carne: (2 * 200 g) + (1 * 150 g) = 550 g
        // - Arroz: (2 * 62.5 g) = 125 g
        // - Aguacate: (2 * 50 g) = 100 g
        $salePayload = [
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $platoAlmuerzo->id,
                    'quantity' => 2,
                    'additions' => [$adicionAguacate->id => 1],
                ],
                [
                    'product_id' => $platoBowlSoloCarne->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 75000.0, // (2 * 26000) + (1 * 4000) + 19000
                    'amount_tendered' => 80000.0,
                ],
            ],
        ];

        $sale = $this->saleService->processSale($salePayload, $this->cajero);

        $this->assertEquals('completed', $sale->status);
        $this->assertEquals(450.0, (float) $carne->fresh()->stock, 'Carne: 1000 - 550 = 450 g');
        $this->assertEquals(875.0, (float) $arroz->fresh()->stock, 'Arroz: 1000 - 125 = 875 g');
        $this->assertEquals(450.0, (float) $aguacate->fresh()->stock, 'Aguacate: 500 - 50 = 450 g');

        // Anular la venta autorizada por el Administrador
        $cancelledSale = $this->saleService->cancelSale(
            $sale,
            $this->admin,
            'Cliente solicitó cambio total de orden'
        );

        $this->assertEquals('cancelled', $cancelledSale->status);
        $this->assertStringContainsString('Anulada el', $cancelledSale->notes);
        $this->assertStringContainsString('Gerente CaliFit', $cancelledSale->notes);

        // Reversión simétrica perfecta al 100% de los gramos
        $this->assertEquals(1000.0, (float) $carne->fresh()->stock, 'Carne debe retornar a 1000.0 g exactos');
        $this->assertEquals(1000.0, (float) $arroz->fresh()->stock, 'Arroz debe retornar a 1000.0 g exactos');
        $this->assertEquals(500.0, (float) $aguacate->fresh()->stock, 'Aguacate debe retornar a 500.0 g exactos');

        // Verificar trazabilidad inmutable de DEVOLUCION_ANULACION
        $reversalMovements = InventoryMovement::where('type', InventoryMovement::TYPE_DEVOLUCION_ANULACION)->get();
        $this->assertGreaterThanOrEqual(3, $reversalMovements->count());

        $this->assertTrue(
            $reversalMovements->contains(fn ($m) => $m->product_id === $carne->id && ((float) $m->quantity === 400.0 || (float) $m->quantity === 150.0)),
            'Debe existir movimiento de devolución de carne'
        );
        $this->assertTrue(
            $reversalMovements->contains(fn ($m) => $m->product_id === $arroz->id && (float) $m->quantity === 125.0),
            'Debe existir movimiento de devolución de arroz por 125 g'
        );
        $this->assertTrue(
            $reversalMovements->contains(fn ($m) => $m->product_id === $aguacate->id && (float) $m->quantity === 50.0),
            'Debe existir movimiento de devolución de aguacate por 50 g'
        );
    }
}
