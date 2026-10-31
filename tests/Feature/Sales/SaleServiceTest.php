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
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Kitchen\RecipeService;
use App\Services\Sales\SaleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SaleService $saleService;
    protected InventoryService $inventoryService;
    protected RecipeService $recipeService;

    protected User $cajero;
    protected Customer $cliente;

    protected Product $carne;
    protected Product $arroz;
    protected Product $frijoles;
    protected Product $papa;
    protected Product $platoEjecutivo;
    protected Product $suplementoCreatina;
    protected Addition $adicionCarne120g;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();
        $this->recipeService = new RecipeService();
        $this->saleService = new SaleService($this->inventoryService, $this->recipeService);

        $roleEmployee = Role::create([
            'slug' => Role::ROLE_EMPLOYEE,
            'name' => 'Empleado POS',
        ]);

        $this->cajero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero Test',
            'email' => 'cajero@test.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $this->cliente = Customer::create([
            'name' => 'Juan Pérez',
            'document' => '1144000111',
            'email' => 'juan@test.com',
            'phone' => '3150001122',
            'is_active' => true,
        ]);

        $catInsumos = Category::create(['name' => 'Insumos Cocina', 'is_active' => true]);
        $catPlatos = Category::create(['name' => 'Platos Fit', 'is_active' => true]);
        $catSuplementos = Category::create(['name' => 'Suplementos', 'is_active' => true]);

        // Insumos base con stock conocido
        $this->carne = Product::create([
            'sku' => 'INS-001',
            'nombre' => 'Carne Magra',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 1000.000, // 1000g de carne
            'precio_compra' => 35.00,
            'activo' => true,
        ]);

        $this->arroz = Product::create([
            'sku' => 'INS-002',
            'nombre' => 'Arroz Cocido',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 1000.000,
            'precio_compra' => 6.00,
            'activo' => true,
        ]);

        $this->frijoles = Product::create([
            'sku' => 'INS-003',
            'nombre' => 'Frijoles Cocidos',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 1000.000,
            'precio_compra' => 10.00,
            'activo' => true,
        ]);

        $this->papa = Product::create([
            'sku' => 'INS-006',
            'nombre' => 'Papa Cocida',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 1000.000,
            'precio_compra' => 6.00,
            'activo' => true,
        ]);

        // Plato compuesto
        $this->platoEjecutivo = Product::create([
            'sku' => 'PLA-001',
            'nombre' => 'Ejecutivo 200g con frijoles',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlatos->id,
            'stock' => 0.000,
            'precio_venta' => 25000.00,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $this->platoEjecutivo->id,
            'nombre' => 'Fórmula Ejecutivo 200g con frijoles',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad_por_porcion' => 200.000,
            'unidad' => 'GRAMO',
        ]);
        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->arroz->id,
            'cantidad_por_porcion' => 62.500,
            'unidad' => 'GRAMO',
        ]);
        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->frijoles->id,
            'cantidad_por_porcion' => 23.810,
            'unidad' => 'GRAMO',
        ]);
        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->papa->id,
            'cantidad_por_porcion' => 100.000,
            'unidad' => 'GRAMO',
        ]);

        // Suplemento directo
        $this->suplementoCreatina = Product::create([
            'sku' => 'SUP-001',
            'nombre' => 'Creatina 300g',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catSuplementos->id,
            'stock' => 10.000,
            'precio_venta' => 140000.00,
            'activo' => true,
        ]);

        // Adición reutilizable
        $this->adicionCarne120g = Addition::create([
            'nombre' => 'Carne adicional 120g',
            'precio' => 8000.00,
            'activo' => true,
        ]);
        AdditionItem::create([
            'adicion_id' => $this->adicionCarne120g->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad' => 120.000,
        ]);
    }

    public function test_successful_sale_of_composite_dish_deducts_exact_inventory(): void
    {
        $saleData = [
            'customer_id' => $this->cliente->id,
            'order_type' => 'retail',
            'items' => [
                [
                    'product_id' => $this->platoEjecutivo->id,
                    'quantity' => 2.0, // 2 platos: 400g carne, 125g arroz, 47.62g frijoles, 200g papa
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 50000.00,
                    'cash_received' => 50000.00,
                ],
            ],
        ];

        $sale = $this->saleService->processSale($saleData, $this->cajero);

        $this->assertNotNull($sale);
        $this->assertEquals('completed', $sale->status);
        $this->assertEquals(50000.00, (float) $sale->total);
        $this->assertEquals('FAC-000001', $sale->invoice_number);
        $this->assertEquals('Juan Pérez', $sale->customer_name);
        $this->assertEquals('1144000111', $sale->customer_document);

        // Verificar deducción exacta de stock
        $this->assertEquals(600.000, (float) $this->carne->fresh()->stock); // 1000 - 400 = 600
        $this->assertEquals(875.000, (float) $this->arroz->fresh()->stock); // 1000 - 125 = 875
        $this->assertEquals(952.380, (float) $this->frijoles->fresh()->stock); // 1000 - 47.62 = 952.38
        $this->assertEquals(800.000, (float) $this->papa->fresh()->stock); // 1000 - 200 = 800

        // Verificar registro de movimientos de inventario
        $movements = InventoryMovement::where('reference_type', get_class($sale))
            ->where('reference_id', $sale->id)
            ->where('type', InventoryMovement::TYPE_SALIDA_VENTA)
            ->get();

        $this->assertCount(4, $movements);
    }

    public function test_successful_sale_of_direct_supplement(): void
    {
        $saleData = [
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $this->suplementoCreatina->id,
                    'quantity' => 2.0,
                ],
            ],
            'payments' => [
                [
                    'method' => 'card',
                    'amount' => 280000.00,
                    'reference' => 'VOUCHER-9876',
                ],
            ],
        ];

        $sale = $this->saleService->processSale($saleData, $this->cajero);

        $this->assertEquals('completed', $sale->status);
        $this->assertEquals(280000.00, (float) $sale->total);
        $this->assertEquals(8.000, (float) $this->suplementoCreatina->fresh()->stock); // 10 - 2 = 8

        $movement = InventoryMovement::where('product_id', $this->suplementoCreatina->id)
            ->where('type', InventoryMovement::TYPE_SALIDA_VENTA)
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(2.000, (float) $movement->quantity);
        $this->assertEquals(10.000, (float) $movement->previous_stock);
        $this->assertEquals(8.000, (float) $movement->new_stock);
    }

    public function test_atomic_rollback_when_insufficient_stock_zero_negative_stock(): void
    {
        // Reducir stock de carne a solo 150g (insuficiente para 1 plato que requiere 200g)
        $this->carne->update(['stock' => 150.000]);

        $initialArroz = (float) $this->arroz->stock;
        $initialFrijoles = (float) $this->frijoles->stock;
        $initialPapa = (float) $this->papa->stock;

        $saleData = [
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $this->platoEjecutivo->id,
                    'quantity' => 1.0, // Requiere 200g carne
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 25000.00,
                ],
            ],
        ];

        $this->expectException(InsufficientInventoryException::class);

        try {
            $this->saleService->processSale($saleData, $this->cajero);
        } finally {
            // Comprobar que NINGÚN ingrediente fue modificado (Rollback atómico)
            $this->assertEquals(150.000, (float) $this->carne->fresh()->stock);
            $this->assertEquals($initialArroz, (float) $this->arroz->fresh()->stock);
            $this->assertEquals($initialFrijoles, (float) $this->frijoles->fresh()->stock);
            $this->assertEquals($initialPapa, (float) $this->papa->fresh()->stock);

            // No se creó venta
            $this->assertEquals(0, \App\Models\Sale::count());
            $this->assertEquals(0, InventoryMovement::count());
        }
    }

    public function test_mixed_payments_and_change_calculation(): void
    {
        // 1 plato ($25,000) + 1 adición de carne ($8,000) = $33,000
        $saleData = [
            'customer_id' => $this->cliente->id,
            'items' => [
                [
                    'product_id' => $this->platoEjecutivo->id,
                    'quantity' => 1.0,
                    'additions' => [
                        $this->adicionCarne120g->id => 1,
                    ],
                ],
            ],
            'payments' => [
                [
                    'method' => 'card',
                    'amount' => 13000.00,
                    'reference' => 'DATAFONO-01',
                ],
                [
                    'method' => 'cash',
                    'amount' => 20000.00,
                    'cash_received' => 50000.00, // Paga con billete de $50k -> vuelto $30k
                ],
            ],
        ];

        $sale = $this->saleService->processSale($saleData, $this->cajero);

        $this->assertEquals(33000.00, (float) $sale->total);
        $this->assertEquals('mixed', $sale->payment_method);

        // Verificar pagos registrados
        $payments = $sale->payments;
        $this->assertCount(2, $payments);

        $cardPayment = $payments->where('method', 'card')->first();
        $this->assertEquals(13000.00, (float) $cardPayment->amount);
        $this->assertNull($cardPayment->change_given);

        $cashPayment = $payments->where('method', 'cash')->first();
        $this->assertEquals(20000.00, (float) $cashPayment->amount);
        $this->assertEquals(50000.00, (float) $cashPayment->cash_received);
        $this->assertEquals(30000.00, (float) $cashPayment->change_given);

        // Verificar snapshot inmutable de adición en sale_detail_additions
        $detail = $sale->details->first();
        $this->assertCount(1, $detail->additions);
        $additionSnapshot = $detail->additions->first();
        $this->assertEquals(8000.00, (float) $additionSnapshot->unit_price);
        $this->assertEquals(8000.00, (float) $additionSnapshot->subtotal);
    }

    public function test_rejects_invalid_payments(): void
    {
        // Caso 1: Pagos no coinciden con el total
        $saleData = [
            'items' => [
                ['product_id' => $this->platoEjecutivo->id, 'quantity' => 1.0],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 20000.00], // Total es 25,000
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->saleService->processSale($saleData, $this->cajero);
    }

    public function test_symmetric_cancellation_reverts_exact_grams_to_inventory(): void
    {
        // 1 plato con adición de carne (200g carne base + 120g adición = 320g carne)
        $initialCarneStock = (float) $this->carne->stock; // 1000g

        $saleData = [
            'items' => [
                [
                    'product_id' => $this->platoEjecutivo->id,
                    'quantity' => 1.0,
                    'additions' => [
                        $this->adicionCarne120g->id => 1,
                    ],
                ],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => 33000.00],
            ],
        ];

        $sale = $this->saleService->processSale($saleData, $this->cajero);

        // Tras la venta: 1000 - 320 = 680g
        $this->assertEquals(680.000, (float) $this->carne->fresh()->stock);

        // Ahora anulamos la venta
        $cancelledSale = $this->saleService->cancelSale(
            $sale,
            $this->cajero,
            'Error al ingresar pedido por el cliente'
        );

        $this->assertEquals('cancelled', $cancelledSale->status);

        // El stock de carne debe volver EXACTAMENTE a 1000g
        $this->assertEquals($initialCarneStock, (float) $this->carne->fresh()->stock);

        // Movimientos de devolución registrados
        $returnMovements = InventoryMovement::where('reference_type', get_class($sale))
            ->where('reference_id', $sale->id)
            ->where('type', InventoryMovement::TYPE_DEVOLUCION_ANULACION)
            ->get();

        $this->assertTrue($returnMovements->count() >= 5); // 4 base + 1 adición
    }
}
