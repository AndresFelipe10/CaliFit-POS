<?php

namespace Tests\Feature\Http;

use App\Models\Addition;
use App\Models\AdditionItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $mesero;
    protected Customer $cliente;
    protected RestaurantTable $mesa;
    protected Product $carne;
    protected Product $platoFit;
    protected Product $suplemento;
    protected Addition $adicionCarne;

    protected function setUp(): void
    {
        parent::setUp();

        $roleEmployee = Role::create([
            'slug' => Role::ROLE_EMPLOYEE,
            'name' => 'Mesero / Empleado',
        ]);

        $this->mesero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Mesero CaliFit',
            'email' => 'mesero@califit.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->cliente = Customer::create([
            'name' => 'Laura Atleta',
            'document' => '1144112233',
            'email' => 'laura@atleta.com',
            'phone' => '3157778899',
            'is_active' => true,
        ]);

        $this->mesa = RestaurantTable::create([
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $catInsumo = Category::create(['name' => 'Insumos', 'is_active' => true]);
        $catPlato = Category::create(['name' => 'Platos', 'is_active' => true]);
        $catSupl = Category::create(['name' => 'Suplementos', 'is_active' => true]);

        $this->carne = Product::create([
            'sku' => 'INS-001',
            'nombre' => 'Carne Magra Res',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumo->id,
            'stock' => 10000.000,
            'stock_minimo' => 1000.000,
            'precio_compra' => 25.00,
            'precio_venta' => 0.00,
            'activo' => true,
        ]);

        $this->platoFit = Product::create([
            'sku' => 'PLA-001',
            'nombre' => 'Almuerzo Proteico Res',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlato->id,
            'stock' => 0.000,
            'precio_compra' => 12000.00,
            'precio_venta' => 28000.00,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $this->platoFit->id,
            'nombre' => 'Receta Almuerzo Proteico Res',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad_por_porcion' => 200.000,
            'unidad' => 'GRAMO',
        ]);

        $this->adicionCarne = Addition::create([
            'nombre' => 'Carne Extra 100g',
            'precio' => 7000.00,
            'activo' => true,
        ]);

        AdditionItem::create([
            'adicion_id' => $this->adicionCarne->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad' => 100.000,
        ]);

        $this->suplemento = Product::create([
            'sku' => 'SUP-001',
            'nombre' => 'Bebida Energética Amino',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catSupl->id,
            'stock' => 20.000,
            'stock_minimo' => 5.000,
            'precio_compra' => 5000.00,
            'precio_venta' => 9000.00,
            'activo' => true,
        ]);
    }

    public function test_table_opening_and_item_batching(): void
    {
        $this->actingAs($this->mesero);

        // 1. Abrir Mesa
        $openResponse = $this->post(route('restaurant.orders.open', $this->mesa), [
            'guest_count' => 2,
            'notes' => 'Comensales deportistas',
        ]);

        $openResponse->assertRedirect();
        $openResponse->assertSessionMissing('auto_print_kitchen_url');
        $this->mesa->refresh();
        $this->assertEquals('occupied', $this->mesa->status);

        $order = RestaurantOrder::where('table_id', $this->mesa->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals('open', $order->status);
        $this->assertEquals(2, $order->guest_count);
        $openResponse->assertRedirect(route('restaurant.orders.show', $order));
        $openResponse->assertSessionHas('success', "Mesa 1 abierta exitosamente con comanda #{$order->order_number}");

        // 1.1 Verificar que la pantalla de la comanda renderiza limpiamente (HTTP 200)
        $showResponse = $this->get(route('restaurant.orders.show', $order));
        $showResponse->assertStatus(200);
        $showResponse->assertViewIs('restaurant.orders.show');
        $showResponse->assertSee($order->order_number);
        $showResponse->assertSee($this->mesa->name);

        // 2. Agregar Tanda 1 con Adición: 1 Plato Fit ($28.000) + 1 Adición ($7.000) = $35.000
        $batch1Payload = [
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 1,
                    'notes' => 'Término medio',
                    'additions' => [
                        [
                            'addition_id' => $this->adicionCarne->id,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $batch1Response = $this->postJson(route('restaurant.orders.add-items', $order), $batch1Payload);
        $batch1Response->assertStatus(200);
        $batch1Response->assertJson([
            'success' => true,
            'batch_number' => 1,
        ]);

        $order->refresh();
        $this->assertEquals(35000.00, (float) $order->total);

        // 2.1 Verificar que la pantalla de comanda renderiza con la nueva tanda y adición
        $showWithBatchResponse = $this->get(route('restaurant.orders.show', $order));
        $showWithBatchResponse->assertStatus(200);
        $showWithBatchResponse->assertSee('Tanda #1');
        $showWithBatchResponse->assertSee('Carne Extra 100g');

        // 3. Agregar Tanda 2: 1 Bebida Suplemento ($9.000)
        $batch2Payload = [
            'items' => [
                [
                    'product_id' => $this->suplemento->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $batch2Response = $this->postJson(route('restaurant.orders.add-items', $order), $batch2Payload);
        $batch2Response->assertStatus(200);
        $batch2Response->assertJson([
            'success' => true,
            'batch_number' => 2,
        ]);

        $order->refresh();
        // Total acumulado: $35.000 + $9.000 = $44.000
        $this->assertEquals(44000.00, (float) $order->total);
        $this->assertEquals(2, $order->items()->max('batch_number'));
    }

    public function test_pre_bill_changes_status_to_billed(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000101',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 3,
            'subtotal' => 28000.00,
            'total' => 28000.00,
        ]);

        $this->mesa->update(['status' => 'occupied']);

        $response = $this->post(route('restaurant.orders.pre-bill', $order));

        $response->assertRedirect(route('prints.orders.pre-bill', $order));

        $order->refresh();
        $this->mesa->refresh();

        $this->assertEquals('billed', $order->status);
        $this->assertEquals('billed', $this->mesa->status);
    }

    public function test_order_settlement_creates_sale_and_frees_table(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000102',
            'order_type' => 'table',
            'status' => 'billed',
            'guest_count' => 2,
            'subtotal' => 37000.00,
            'total' => 37000.00,
        ]);
        $this->mesa->update(['status' => 'billed']);

        // Liquidar comanda con 1 Plato Fit ($28.000) + 1 Bebida ($9.000) = $37.000
        // Pago: $37.000 Tarjeta
        $settlePayload = [
            'customer_id' => $this->cliente->id,
            'customer_name' => $this->cliente->name,
            'customer_document' => $this->cliente->document,
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 1,
                ],
                [
                    'product_id' => $this->suplemento->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'card',
                    'amount' => 37000.00,
                    'reference' => 'DATAFONO-1234',
                ],
            ],
        ];

        $response = $this->postJson(route('restaurant.orders.settle', $order), $settlePayload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Comanda liquidada y cobrada con éxito',
        ]);
        $response->assertJsonStructure(['success', 'sale_id', 'invoice_number']);

        $saleId = $response->json('sale_id');

        // Verificar que la orden se cerró
        $order->refresh();
        $this->assertEquals('closed', $order->status);
        $this->assertNotNull($order->closed_at);

        // Verificar que la mesa quedó libre
        $this->mesa->refresh();
        $this->assertEquals('available', $this->mesa->status);

        // Verificar la venta generada
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'restaurant_order_id' => $order->id,
            'order_type' => 'table',
            'total' => 37000.00,
            'status' => 'completed',
        ]);
    }

    public function test_order_cancellation_frees_table(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000103',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 1,
            'subtotal' => 0.00,
            'total' => 0.00,
        ]);
        $this->mesa->update(['status' => 'occupied']);

        $response = $this->post(route('restaurant.orders.cancel', $order), [
            'cancellation_reason' => 'Cliente se retiró antes de ordenar',
        ]);
        $response->assertRedirect(route('restaurant.tables.index'));

        $order->refresh();
        $this->mesa->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('available', $this->mesa->status);
    }

    public function test_table_opening_with_waiter_name_and_initial_items_propagates_to_sale_and_print(): void
    {
        $this->actingAs($this->mesero);

        // 1. Abrir Mesa con mesero personalizado e items iniciales
        $openResponse = $this->post(route('restaurant.orders.open', $this->mesa), [
            'guest_count' => 3,
            'notes' => 'Mesa de atletas VIP',
            'waiter_name' => 'Felipe Mesero',
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 1,
                    'notes' => 'Sin sal agregada',
                    'additions' => [
                        [
                            'addition_id' => $this->adicionCarne->id,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $openResponse->assertRedirect();
        $this->mesa->refresh();
        $this->assertEquals('occupied', $this->mesa->status);

        $order = RestaurantOrder::where('table_id', $this->mesa->id)->firstOrFail();
        $this->assertEquals('Felipe Mesero', $order->waiter_name);
        $this->assertEquals(35000.00, (float) $order->total);
        $this->assertCount(1, $order->items);

        // Verificar auto-impresión y estado de ítems en cocina
        $openResponse->assertSessionHas('auto_print_kitchen_url', route('prints.orders.kitchen', ['order' => $order->id, 'batch' => 1]));
        $this->assertTrue($order->items->first()->printed_to_kitchen);
        $this->assertEquals('kitchen', $order->items->first()->status);
        $openResponse->assertSessionHas('success', "Mesa 1 abierta exitosamente con comanda #{$order->order_number}");

        // 2. Verificar comanda de cocina incluye el nombre del mesero
        $kitchenPrintResponse = $this->get(route('prints.orders.kitchen', ['order' => $order, 'batch' => 1]));
        $kitchenPrintResponse->assertStatus(200);
        $kitchenPrintResponse->assertSee('Felipe Mesero');

        // 3. Verificar pre-cuenta incluye el nombre del mesero
        $preBillResponse = $this->get(route('prints.orders.pre-bill', $order));
        $preBillResponse->assertStatus(200);
        $preBillResponse->assertSee('Felipe Mesero');

        // 4. Liquidar y cobrar la comanda
        $settlePayload = [
            'customer_id' => $this->cliente->id,
            'customer_name' => $this->cliente->name,
            'customer_document' => $this->cliente->document,
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 1,
                    'notes' => 'Sin sal agregada',
                    'additions' => [
                        [
                            'addition_id' => $this->adicionCarne->id,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 35000.00,
                ],
            ],
        ];

        $settleResponse = $this->postJson(route('restaurant.orders.settle', $order), $settlePayload);
        $settleResponse->assertStatus(200);
        $saleId = $settleResponse->json('sale_id');

        // 5. Verificar que la venta persistió el mesero de la orden
        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'waiter_name' => 'Felipe Mesero',
            'total' => 35000.00,
        ]);

        // 6. Verificar impresión de factura/recibo con el nombre del mesero
        $receiptPrintResponse = $this->get(route('prints.sales.receipt', $saleId));
        $receiptPrintResponse->assertStatus(200);
        $receiptPrintResponse->assertSee('Felipe Mesero');
    }
}

