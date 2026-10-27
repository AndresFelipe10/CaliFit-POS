<?php

namespace Tests\Feature\Concurrency;

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
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Kitchen\RecipeService;
use App\Services\Sales\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected SaleService $saleService;
    protected InventoryService $inventoryService;
    protected RecipeService $recipeService;

    protected User $cajero;
    protected Customer $cliente;
    protected Category $categoriaComidas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();
        $this->recipeService = new RecipeService();
        $this->saleService = new SaleService($this->inventoryService, $this->recipeService);

        $roleEmployee = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado POS']
        );

        $this->cajero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero Bochalema',
            'email' => 'cajero.concurrencia@test.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->cliente = Customer::create([
            'name' => 'Cliente Concurrente',
            'document' => '1144000111',
            'email' => 'cliente.concurrencia@test.com',
            'phone' => '3001234567',
        ]);

        $this->categoriaComidas = Category::firstOrCreate(
            ['slug' => 'platos-fitness'],
            ['name' => 'Platos Fitness']
        );
    }

    /**
     * 1. Competencia por stock límite de insumo base:
     * Insumo crítico (Pechuga de Pollo) con stock exacto para 1 porción (200 g).
     * Dos peticiones de venta compiten por el plato.
     * Resultado exigido: Exactamente 1 venta aprobada (FAC generada, stock en 0 g)
     * y 1 venta rechazada con InsufficientInventoryException sin descontar gramos negativos.
     */
    public function test_concurrent_sales_competing_for_boundary_base_ingredient_stock(): void
    {
        // Insumo con stock límite exacto de 200 g (1 porción)
        $pollo = Product::create([
            'sku' => 'INS-POLLO-200',
            'nombre' => 'Pechuga de Pollo',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaComidas->id,
            'stock' => 200.0,
            'stock_minimo' => 500.0,
            'precio_compra' => 15.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        // Plato compuesto que consume 200 g de Pechuga de Pollo
        $bowlPollo = Product::create([
            'sku' => 'PLATO-BOWL-POLLO',
            'nombre' => 'Bowl Proteico de Pollo',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->categoriaComidas->id,
            'stock' => 0.0,
            'stock_minimo' => 0.0,
            'precio_compra' => 5000.0,
            'precio_venta' => 24000.0,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $bowlPollo->id,
            'nombre' => 'Receta Bowl Proteico de Pollo',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $pollo->id,
            'cantidad_por_porcion' => 200.0,
            'unidad' => 'GRAMO',
        ]);

        $salePayload = [
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $bowlPollo->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 24000.0,
                    'amount_tendered' => 24000.0,
                ],
            ],
        ];

        // Intento 1: Debe aprobarse con éxito y consumir los 200 g
        $sale1 = $this->saleService->processSale($salePayload, $this->cajero);

        $this->assertInstanceOf(Sale::class, $sale1);
        $this->assertStringStartsWith('FAC-', $sale1->invoice_number);
        $this->assertEquals(0.0, (float) $pollo->fresh()->stock);

        // Intento 2 concurrente / subsiguiente por el mismo insumo ya agotado:
        // Debe ser rechazado atómicamente lanzando InsufficientInventoryException
        $exceptionThrown = false;
        try {
            $this->saleService->processSale($salePayload, $this->cajero);
        } catch (InsufficientInventoryException $e) {
            $exceptionThrown = true;
            $this->assertEquals('Pechuga de Pollo', $e->getProductName());
            $this->assertEquals(0.0, $e->getAvailableStock());
            $this->assertEquals(200.0, $e->getRequiredQuantity());
        }

        $this->assertTrue($exceptionThrown, 'El segundo intento de venta debió lanzar InsufficientInventoryException');

        // Verificación estricta de Cero Stock Negativo e integridad transaccional
        $pollo->refresh();
        $this->assertEquals(0.0, (float) $pollo->stock, 'El stock nunca debe caer por debajo de 0');
        $this->assertEquals(1, Sale::count(), 'Solo debe existir exactamente 1 venta registrada en la base de datos');
        $this->assertEquals(1, InventoryMovement::where('product_id', $pollo->id)->where('type', 'SALIDA_VENTA')->count());
    }

    /**
     * 2. Competencia por insumo compartido entre plato y adición:
     * Insumo compartido (Aguacate Hass) con stock límite de 50 g.
     * Compiten una venta de plato base (Bowl Guacamole, requiere 50 g)
     * y otra venta de plato distinto con adición de Aguacate Extra (requiere 50 g).
     * Resultado exigido: Deducción atómica limpia, sin desbordamiento ni valores negativos.
     */
    public function test_concurrent_sales_competing_for_shared_ingredient_between_dish_and_addition(): void
    {
        // Insumo compartido con stock límite de 50 g
        $aguacate = Product::create([
            'sku' => 'INS-AGUACATE-50',
            'nombre' => 'Aguacate Hass',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaComidas->id,
            'stock' => 50.0,
            'stock_minimo' => 100.0,
            'precio_compra' => 20.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        // Insumo Atún para el Wrap
        $atun = Product::create([
            'sku' => 'INS-ATUN-150',
            'nombre' => 'Lomo de Atún',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $this->categoriaComidas->id,
            'stock' => 500.0,
            'stock_minimo' => 200.0,
            'precio_compra' => 30.0,
            'precio_venta' => 0.0,
            'activo' => true,
        ]);

        // Plato A: Bowl Guacamole (requiere 50 g de Aguacate)
        $bowlGuacamole = Product::create([
            'sku' => 'PLATO-BOWL-GUACAMOLE',
            'nombre' => 'Bowl Guacamole Salad',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->categoriaComidas->id,
            'stock' => 0.0,
            'stock_minimo' => 0.0,
            'precio_compra' => 3000.0,
            'precio_venta' => 20000.0,
            'activo' => true,
        ]);

        $recetaBowl = Recipe::create([
            'producto_plato_id' => $bowlGuacamole->id,
            'nombre' => 'Receta Bowl Guacamole',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $recetaBowl->id,
            'ingrediente_id' => $aguacate->id,
            'cantidad_por_porcion' => 50.0,
            'unidad' => 'GRAMO',
        ]);

        // Plato B: Wrap Proteico (requiere 150 g de Atún)
        $wrapAtun = Product::create([
            'sku' => 'PLATO-WRAP-ATUN',
            'nombre' => 'Wrap Proteico de Atún',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->categoriaComidas->id,
            'stock' => 0.0,
            'stock_minimo' => 0.0,
            'precio_compra' => 4000.0,
            'precio_venta' => 22000.0,
            'activo' => true,
        ]);

        $recetaWrap = Recipe::create([
            'producto_plato_id' => $wrapAtun->id,
            'nombre' => 'Receta Wrap Atún',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $recetaWrap->id,
            'ingrediente_id' => $atun->id,
            'cantidad_por_porcion' => 150.0,
            'unidad' => 'GRAMO',
        ]);

        // Adición: Porción Aguacate Extra (consume 50 g de Aguacate)
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

        // Venta 1: Wrap con Adición de Aguacate Extra (debe consumir 150 g de atún y los 50 g de aguacate)
        $saleWrap = $this->saleService->processSale([
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $wrapAtun->id,
                    'quantity' => 1,
                    'additions' => [$adicionAguacate->id => 1],
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 26000.0, // 22000 + 4000
                    'amount_tendered' => 26000.0,
                ],
            ],
        ], $this->cajero);

        $this->assertInstanceOf(Sale::class, $saleWrap);
        $this->assertEquals(0.0, (float) $aguacate->fresh()->stock, 'Aguacate debe quedar en 0 g');
        $this->assertEquals(350.0, (float) $atun->fresh()->stock, 'Atún debe restar 150 g quedando en 350 g');

        // Venta 2: Intento de vender Bowl Guacamole (que requiere 50 g del mismo aguacate ahora en 0 g)
        $rejected = false;
        try {
            $this->saleService->processSale([
                'customer_id' => $this->cliente->id,
                'items' => [
                    [
                        'product_id' => $bowlGuacamole->id,
                        'quantity' => 1,
                    ],
                ],
                'payments' => [
                    [
                        'payment_method' => 'transfer',
                        'amount' => 20000.0,
                        'amount_tendered' => 20000.0,
                    ],
                ],
            ], $this->cajero);
        } catch (InsufficientInventoryException $e) {
            $rejected = true;
            $this->assertEquals('Aguacate Hass', $e->getProductName());
            $this->assertEquals(0.0, $e->getAvailableStock());
        }

        $this->assertTrue($rejected, 'La venta que requería el insumo compartido agotado debió ser rechazada');
        $this->assertEquals(0.0, (float) $aguacate->fresh()->stock, 'El stock nunca debe ser negativo tras competencia concurrente');
    }
}
