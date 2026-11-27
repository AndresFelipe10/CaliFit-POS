<?php

namespace Tests\Feature\Restaurant;

use App\Models\Addition;
use App\Models\AdditionItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantOrderItemAddition;
use App\Models\RestaurantOrderSplit;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitBillCreditAndUnitsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Customer $finalConsumer;
    protected Customer $validCustomer;
    protected Customer $customerWithoutPhone;
    protected RestaurantTable $table;
    protected Product $insumoCarne;
    protected Product $insumoPapa;
    protected Product $platoEjecutivo;
    protected Addition $adicionPapa;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => Role::ROLE_ADMIN], ['name' => 'Administrador']);
        $roleEmployee = Role::firstOrCreate(['slug' => Role::ROLE_EMPLOYEE], ['name' => 'Cajero / Mesero']);

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin.split@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Mesero Turno',
            'email' => 'mesero.split@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->finalConsumer = Customer::create([
            'name' => 'Consumidor Final',
            'document' => '222222222222',
            'phone' => '0000000000',
            'is_active' => true,
        ]);

        $this->validCustomer = Customer::create([
            'name' => 'Laura Gómez',
            'document' => '1144123456',
            'phone' => '3157778899',
            'email' => 'laura@califit.com',
            'is_active' => true,
        ]);

        $this->customerWithoutPhone = Customer::create([
            'name' => 'Cliente Sin Teléfono',
            'document' => '1144999888',
            'phone' => null,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'name' => 'Mesa 5',
            'number' => 5,
            'capacity' => 6,
            'status' => 'occupied',
        ]);

        $catPlatos = Category::create(['name' => 'Ejecutivos Fit', 'is_active' => true]);
        $catInsumos = Category::create(['name' => 'Insumos Cocina', 'is_active' => true]);

        $this->insumoCarne = Product::create([
            'sku' => 'INS-CARNE-SPLIT',
            'nombre' => 'Carne Magra',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 1000.000,
            'precio_compra' => 28,
            'activo' => true,
        ]);

        $this->insumoPapa = Product::create([
            'sku' => 'INS-PAPA-SPLIT',
            'nombre' => 'Papa Criolla',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 1000.000,
            'precio_compra' => 5,
            'activo' => true,
        ]);

        $this->platoEjecutivo = Product::create([
            'sku' => 'PLA-EJE-200',
            'nombre' => 'Ejecutivo 200g con lentejas',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlatos->id,
            'precio_venta' => 25000.00,
            'activo' => true,
        ]);

        $recipe = Recipe::create([
            'producto_plato_id' => $this->platoEjecutivo->id,
            'nombre' => 'Receta Ejecutivo 200g',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $recipe->id,
            'ingrediente_id' => $this->insumoCarne->id,
            'cantidad_por_porcion' => 200.000,
            'unidad' => 'GRAMO',
        ]);

        $this->adicionPapa = Addition::create([
            'nombre' => 'Porción adicional de Papa 100g',
            'precio' => 3000.00,
            'activo' => true,
        ]);

        AdditionItem::create([
            'adicion_id' => $this->adicionPapa->id,
            'ingrediente_id' => $this->insumoPapa->id,
            'cantidad' => 100.000,
        ]);
    }

    /**
     * Crea una comanda de prueba con N unidades del plato Ejecutivo ($25.000 c/u).
     */
    private function createOrderWithQuantity(int $quantity, int $additionQty = 0): array
    {
        $order = RestaurantOrder::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'customer_id' => $this->finalConsumer->id,
            'status' => 'in_kitchen',
            'order_type' => 'table',
            'subtotal' => 0,
            'total' => 0,
        ]);

        $additionsSubtotal = $additionQty * 3000.00;
        $lineSubtotal = ($quantity * 25000.00) + $additionsSubtotal;

        $orderItem = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoEjecutivo->id,
            'batch_number' => 1,
            'quantity' => $quantity,
            'paid_quantity' => 0,
            'unit_price' => 25000.00,
            'subtotal' => $lineSubtotal,
            'is_paid' => false,
        ]);

        if ($additionQty > 0) {
            RestaurantOrderItemAddition::create([
                'order_item_id' => $orderItem->id,
                'adicion_id' => $this->adicionPapa->id,
                'quantity' => $additionQty,
                'paid_quantity' => 0,
                'unit_price' => 3000.00,
                'subtotal' => $additionsSubtotal,
            ]);
        }

        $order->recalculateTotals();

        return [$order->fresh(), $orderItem->fresh()];
    }

    /**
     * Escenario 1: Desagregación por unidad y venta parcial a crédito (1 de 3 unidades).
     */
    public function test_desaggregates_multi_unit_item_and_settles_one_unit_on_credit(): void
    {
        [$order, $item] = $this->createOrderWithQuantity(3);

        // La vista debe mostrar 3 filas unitarias desagregadas antes de dividir
        $showBefore = $this->actingAs($this->employee)->get(route('restaurant.orders.show', $order));
        $showBefore->assertOk();
        $showBefore->assertViewHas('splitUnitRows', function (array $rows) {
            return count($rows) === 3
                && $rows[0]['unit_label'] === 'Unidad 1 de 3'
                && $rows[1]['unit_label'] === 'Unidad 2 de 3'
                && $rows[2]['unit_label'] === 'Unidad 3 de 3'
                && (float) $rows[0]['subtotal'] === 25000.0;
        });
        $showBefore->assertSee('splitCreditSwitch', false);
        $showBefore->assertSee('Despachar bajo Venta a Crédito / Fiado');
        $showBefore->assertSee('Unidad 1 de 3');
        $showBefore->assertSee('Unidad 2 de 3');
        $showBefore->assertSee('Unidad 3 de 3');

        // Dividir exactamente 1 unidad bajo Venta a Crédito / Fiado para Cliente A
        $dueDate = now()->addDays(15)->format('Y-m-d');
        $response = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 1,
                        'additions' => [],
                    ],
                ],
                'is_credit' => true,
                'customer_id' => $this->validCustomer->id,
                'credit_due_date' => $dueDate,
                'payments' => [],
            ]
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_credit', true)
            ->assertJsonPath('is_order_closed', false)
            ->assertJsonPath('remaining_total', 50000);

        $order->refresh();
        $item->refresh();
        $this->table->refresh();

        $this->assertEquals(1, $item->paid_quantity);
        $this->assertFalse($item->is_paid);
        $this->assertEquals(2, $item->pending_quantity);
        $this->assertEquals(50000.00, (float) $item->pending_subtotal);
        $this->assertEquals('items', $order->split_type);
        $this->assertEquals(50000.00, (float) $order->pending_amount);
        $this->assertEquals('billed', $order->status);
        $this->assertEquals('billed', $this->table->status);

        // Descuento inmediato de inventario solo para 1 unidad (200g)
        $this->assertEquals(800.000, (float) $this->insumoCarne->fresh()->stock);

        // Venta a crédito y trazabilidad en restaurant_order_splits
        $sale = Sale::findOrFail($response->json('sale_id'));
        $this->assertTrue((bool) $sale->is_credit);
        $this->assertEquals('pending', $sale->credit_status);
        $this->assertEquals($this->validCustomer->id, $sale->customer_id);
        $this->assertEquals(25000.00, (float) $sale->total);

        $split = RestaurantOrderSplit::where('restaurant_order_id', $order->id)->firstOrFail();
        $this->assertTrue((bool) $split->is_credit);
        $this->assertEquals($this->validCustomer->id, $split->customer_id);
        $this->assertEquals($sale->id, $split->sale_id);
        $this->assertEquals(25000.00, (float) $split->amount);
        $this->assertEquals('Ejecutivo 200g con lentejas', $split->items_summary[0]['product_name'] ?? null);
        $this->assertEquals(1, $split->items_summary[0]['quantity'] ?? null);

        // La vista después del cobro parcial debe mostrar exactamente 2 filas pendientes y trazabilidad del fiado
        $showAfter = $this->actingAs($this->employee)->get(route('restaurant.orders.show', $order));
        $showAfter->assertOk();
        $showAfter->assertViewHas('splitUnitRows', fn (array $rows) => count($rows) === 2);
        $showAfter->assertSee('Historial de Divisiones y Cobros Parciales');
        $showAfter->assertSee('A Crédito / Fiado');
        $showAfter->assertSee('Laura Gómez');
        $showAfter->assertDontSee('Unidad 1 de 3');
        $showAfter->assertSee('Unidad 2 de 3');
        $showAfter->assertSee('Unidad 3 de 3');
    }

    /**
     * Escenario 2: Validación estricta de cliente con teléfono en crédito parcial (HTTP 422).
     */
    public function test_split_credit_rejects_final_consumer_and_customer_without_phone(): void
    {
        [$order, $item] = $this->createOrderWithQuantity(2);

        // 1. Consumidor Final (222222222222) debe ser rechazado con 422
        $resFinal = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
                'is_credit' => true,
                'customer_id' => $this->finalConsumer->id,
                'credit_due_date' => now()->addDays(7)->format('Y-m-d'),
            ]
        );
        $resFinal->assertStatus(422)->assertJsonValidationErrors('customer_id');

        // 2. Cliente sin teléfono registrado debe ser rechazado con 422
        $resNoPhone = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
                'is_credit' => true,
                'customer_id' => $this->customerWithoutPhone->id,
                'credit_due_date' => now()->addDays(7)->format('Y-m-d'),
            ]
        );
        $resNoPhone->assertStatus(422)->assertJsonValidationErrors('customer_id');

        // 3. Sin customer_id también debe fallar con 422
        $resMissing = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
                'is_credit' => true,
            ]
        );
        $resMissing->assertStatus(422)->assertJsonValidationErrors('customer_id');

        // Ningún movimiento ni venta creada
        $this->assertSame(0, Sale::count());
        $this->assertEquals(1000.000, (float) $this->insumoCarne->fresh()->stock);
    }

    /**
     * Escenario 3: Liquidación mixta secuencial y cierre automático sin doble descuento de inventario.
     */
    public function test_sequential_split_and_settle_order_closes_table_without_double_inventory_deduction(): void
    {
        // 3 unidades con 3 adiciones de Papa ($25.000*3 + $3.000*3 = $84.000)
        [$order, $item] = $this->createOrderWithQuantity(3, 3);
        $additionPivot = $item->additions()->firstOrFail();

        // Paso 1: Unidad 1 a crédito ($28.000 con 1 adición)
        $res1 = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 1,
                        'additions' => [
                            ['addition_id' => $this->adicionPapa->id, 'quantity' => 1],
                        ],
                    ],
                ],
                'is_credit' => true,
                'customer_id' => $this->validCustomer->id,
                'credit_due_date' => now()->addDays(10)->format('Y-m-d'),
            ]
        );
        $res1->assertOk()->assertJsonPath('is_order_closed', false);
        $this->assertEquals(28000.00, (float) Sale::findOrFail($res1->json('sale_id'))->total);
        $this->assertEquals(800.000, (float) $this->insumoCarne->fresh()->stock);
        $this->assertEquals(900.000, (float) $this->insumoPapa->fresh()->stock);

        // Paso 2: Cobrar Total por el saldo pendiente restante (2 unidades + 2 adiciones = $56.000)
        $res2 = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.settle', $order),
            [
                'customer_id' => $this->finalConsumer->id,
                'customer_name' => 'Consumidor Final',
                'customer_document' => '222222222222',
                'items' => [
                    [
                        'product_id' => $this->platoEjecutivo->id,
                        'quantity' => 2,
                        'additions' => [
                            ['addition_id' => $this->adicionPapa->id, 'quantity' => 2],
                        ],
                    ],
                ],
                'payments' => [
                    ['method' => 'cash', 'amount' => 56000, 'cash_received' => 60000],
                ],
                'discount' => 0,
                'tip' => 0,
            ]
        );
        $res2->assertOk()->assertJsonPath('success', true);

        $order->refresh();
        $item->refresh();
        $additionPivot->refresh();
        $this->table->refresh();

        $this->assertEquals('closed', $order->status);
        $this->assertEquals('available', $this->table->status);
        $this->assertTrue($item->is_paid);
        $this->assertEquals(3, $item->paid_quantity);
        $this->assertEquals(3, $additionPivot->paid_quantity);

        // Stock exacto final: 3 * 200g = 600g carne consumida (400g restantes), 3 * 100g = 300g papa consumida (700g restantes)
        $this->assertEquals(400.000, (float) $this->insumoCarne->fresh()->stock);
        $this->assertEquals(700.000, (float) $this->insumoPapa->fresh()->stock);

        // Total ventas registradas: $28.000 crédito + $56.000 contado = $84.000 exactos
        $this->assertEquals(84000.00, (float) Sale::sum('total'));
    }

    /**
     * Escenario 4: Aislamiento financiero del crédito parcial hasta su abono en Cartera.
     */
    public function test_partial_credit_split_is_isolated_from_dashboard_and_cash_until_collected(): void
    {
        [$order, $item] = $this->createOrderWithQuantity(2); // $50.000

        // 1 unidad a crédito ($25.000)
        $resCredit = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
                'is_credit' => true,
                'customer_id' => $this->validCustomer->id,
                'credit_due_date' => now()->addDays(15)->format('Y-m-d'),
            ]
        );
        $resCredit->assertOk();
        $creditSaleId = $resCredit->json('sale_id');

        // 1 unidad en efectivo ($25.000) -> cierra la comanda
        $resCash = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [['item_id' => $item->id, 'quantity' => 1]],
                'is_credit' => false,
                'customer_id' => $this->finalConsumer->id,
                'payments' => [
                    ['method' => 'cash', 'amount' => 25000, 'cash_received' => 30000],
                ],
            ]
        );
        $resCash->assertOk()->assertJsonPath('is_order_closed', true);

        // Antes del cobro de Cartera: Dashboard y Cuadre de Caja solo cuentan $25.000 contado y aíslan $25.000 fiados
        $dashBefore = $this->actingAs($this->admin)->get(route('dashboard'));
        $dashBefore->assertOk()
            ->assertViewHas('todaySalesTotal', 25000.0)
            ->assertViewHas('todaySalesCount', 1);

        $cashBefore = $this->actingAs($this->admin)->get(route('cash-register.index'));
        $cashBefore->assertOk()
            ->assertViewHas('netTotalBilled', 25000.0)
            ->assertViewHas('totalCollected', 25000.0)
            ->assertViewHas('cashInDrawer', 25000.0)
            ->assertViewHas('creditSalesTotal', 25000.0)
            ->assertViewHas('creditSalesCount', 1);

        // Cobro real de la deuda en Cartera
        $payResponse = $this->actingAs($this->admin)->post(route('credit.pay'), [
            'sale_id' => $creditSaleId,
            'amount' => 25000,
            'method' => 'cash',
            'cash_received' => 25000,
        ]);
        $payResponse->assertRedirect();

        $this->assertEquals('paid', Sale::findOrFail($creditSaleId)->credit_status);

        // Después del cobro: Dashboard y Cuadre de Caja reflejan los $50.000 recaudados
        $dashAfter = $this->actingAs($this->admin)->get(route('dashboard'));
        $dashAfter->assertOk()
            ->assertViewHas('todaySalesTotal', 50000.0)
            ->assertViewHas('todaySalesCount', 2);

        $cashAfter = $this->actingAs($this->admin)->get(route('cash-register.index'));
        $cashAfter->assertOk()
            ->assertViewHas('totalCollected', 50000.0)
            ->assertViewHas('cashInDrawer', 50000.0)
            ->assertViewHas('creditCollectionsTotal', 25000.0);
    }

    /**
     * Escenario 5: Pre-cuenta parcial unitaria y bloqueo ante exceso de cantidad / doble cobro.
     */
    public function test_partial_pre_bill_renders_unit_price_and_rejects_excess_unit_quantity(): void
    {
        [$order, $item] = $this->createOrderWithQuantity(3); // $75.000

        // Pre-cuenta parcial por 1 sola unidad -> debe mostrar $25.000 y NO $75.000
        $selectionJson = json_encode([
            [
                'item_id' => $item->id,
                'quantity' => 1,
                'additions' => [],
            ],
        ]);

        $preBill = $this->actingAs($this->employee)->get(
            route('restaurant.orders.pre-bill-partial', [
                'order' => $order->id,
                'selection' => $selectionJson,
            ])
        );

        $preBill->assertOk();
        $preBill->assertSee('Ejecutivo 200g con lentejas');
        $preBill->assertSee('$25.000');
        $preBill->assertDontSee('$75.000');

        // Cobrar 2 unidades válidas en efectivo ($50.000) -> queda 1 unidad pendiente
        $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [['item_id' => $item->id, 'quantity' => 2]],
                'is_credit' => false,
                'customer_id' => $this->finalConsumer->id,
                'payments' => [
                    ['method' => 'cash', 'amount' => 50000, 'cash_received' => 50000],
                ],
            ]
        )->assertOk();

        // Intentar cobrar 2 unidades cuando solo queda 1 pendiente -> HTTP 422 atómico
        $overRequest = $this->actingAs($this->employee)->postJson(
            route('restaurant.orders.split-settle', $order),
            [
                'split_type' => 'items',
                'items' => [['item_id' => $item->id, 'quantity' => 2]],
                'is_credit' => false,
                'customer_id' => $this->finalConsumer->id,
                'payments' => [
                    ['method' => 'cash', 'amount' => 50000, 'cash_received' => 50000],
                ],
            ]
        );

        $overRequest->assertStatus(422);
        $this->assertEquals(1, $item->fresh()->pending_quantity);
        $this->assertEquals(600.000, (float) $this->insumoCarne->fresh()->stock);
    }
}
