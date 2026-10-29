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
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SplitBillTest extends TestCase
{
    use RefreshDatabase;

    protected User $mesero;
    protected User $cajero;
    protected Customer $cliente;
    protected RestaurantTable $mesa;
    protected Product $insumoCarne;
    protected Product $insumoQueso;
    protected Product $platoFit;
    protected Product $platoBatido;
    protected Addition $adicionQueso;
    protected RestaurantOrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = app(RestaurantOrderService::class);

        $roleEmployee = Role::firstOrCreate(['slug' => Role::ROLE_EMPLOYEE], ['name' => 'Empleado']);
        $this->mesero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Mesero Salon',
            'email' => 'mesero@califit.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->cajero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero Salon',
            'email' => 'cajero.salon@califit.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->cliente = Customer::create([
            'name' => 'Marta Mesa',
            'document' => '1005556667',
            'email' => 'marta@salon.com',
            'phone' => '3001234567',
            'is_active' => true,
        ]);

        $this->mesa = RestaurantTable::create([
            'name' => 'Mesa 5',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $catInsumos = Category::create(['name' => 'Insumos Salon', 'is_active' => true]);
        $catPlatos = Category::create(['name' => 'Platos Salon', 'is_active' => true]);

        // Insumo para plato: Carne
        $this->insumoCarne = Product::create([
            'sku' => 'INS-CARNE-MESAS',
            'nombre' => 'Carne 100g',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 5000.000,
            'precio_compra' => 30.00,
            'activo' => true,
        ]);

        // Insumo para adición: Queso
        $this->insumoQueso = Product::create([
            'sku' => 'INS-QUESO-MESAS',
            'nombre' => 'Queso Mozzarella',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 3000.000,
            'precio_compra' => 25.00,
            'activo' => true,
        ]);

        // Plato 1: Bowl Proteico
        $this->platoFit = Product::create([
            'sku' => 'PLA-BOWL-FIT',
            'nombre' => 'Bowl Proteico Bochalema',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlatos->id,
            'precio_venta' => 30000.00,
            'activo' => true,
        ]);

        $receta1 = Recipe::create([
            'producto_plato_id' => $this->platoFit->id,
            'nombre' => 'Receta Bowl Bochalema',
            'activa' => true,
        ]);
        RecipeItem::create([
            'receta_id' => $receta1->id,
            'ingrediente_id' => $this->insumoCarne->id,
            'cantidad_por_porcion' => 200.000,
            'unidad' => 'GRAMO',
        ]);

        // Plato 2: Batido Detox
        $this->platoBatido = Product::create([
            'sku' => 'PLA-BATIDO-VERDE',
            'nombre' => 'Batido Detox Verde',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlatos->id,
            'precio_venta' => 15000.00,
            'activo' => true,
        ]);

        $receta2 = Recipe::create([
            'producto_plato_id' => $this->platoBatido->id,
            'nombre' => 'Receta Batido Detox',
            'activa' => true,
        ]);
        RecipeItem::create([
            'receta_id' => $receta2->id,
            'ingrediente_id' => $this->insumoCarne->id,
            'cantidad_por_porcion' => 50.000,
            'unidad' => 'GRAMO',
        ]);

        // Adición reutilizable con receta de insumos
        $this->adicionQueso = Addition::create([
            'nombre' => 'Queso Extra 50g',
            'precio' => 5000.00,
            'activo' => true,
        ]);
        AdditionItem::create([
            'adicion_id' => $this->adicionQueso->id,
            'ingrediente_id' => $this->insumoQueso->id,
            'cantidad' => 50.000,
        ]);
    }

    /**
     * Test 1: La selección de platos específicos liquida únicamente esos ítems y genera la venta con sus adiciones,
     * descontando inventario atómicamente y manteniendo pendientes los platos restantes.
     */
    public function test_item_split_settles_selected_dishes_with_additions_and_keeps_remaining_items_pending(): void
    {
        $this->actingAs($this->cajero);

        $initialCarneStock = (float) $this->insumoCarne->fresh()->stock;
        $initialQuesoStock = (float) $this->insumoQueso->fresh()->stock;

        $this->mesa->update(['status' => 'occupied']);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'customer_id' => $this->cliente->id,
            'order_number' => 'ORD-SPLIT-ITEMS-01',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 50000.00,
            'total' => 50000.00,
        ]);

        // Ítem 1: Bowl Fit ($30.000) con adición de queso ($5.000) = $35.000
        $item1 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 35000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        RestaurantOrderItemAddition::create([
            'order_item_id' => $item1->id,
            'adicion_id' => $this->adicionQueso->id,
            'quantity' => 1,
            'unit_price' => 5000.00,
            'subtotal' => 5000.00,
        ]);

        // Ítem 2: Batido Detox ($15.000)
        $item2 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoBatido->id,
            'quantity' => 1,
            'unit_price' => 15000.00,
            'subtotal' => 15000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        // Liquidar únicamente el Ítem 1 (con su adición) en efectivo ($35.000)
        $res = $this->postJson(route('restaurant.orders.split-settle', $order), [
            'split_type' => 'items',
            'item_ids' => [$item1->id],
            'amount' => 35000.00,
            'method' => 'cash',
            'cash_received' => 50000.00,
            'reference' => 'Comensal 1 Bowl con adición',
        ]);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'is_order_closed' => false,
            'message' => 'Pago parcial liquidado con éxito. Mesa permanece con saldo pendiente.',
        ]);

        // Verificar venta generada
        $saleId = $res->json('sale_id');
        $sale = Sale::with(['details.additions'])->findOrFail($saleId);
        $this->assertEquals(35000.00, (float) $sale->total);
        $this->assertEquals('completed', $sale->status);
        $this->assertCount(1, $sale->details);
        $this->assertCount(1, $sale->details->first()->additions);
        $this->assertEquals($this->adicionQueso->id, $sale->details->first()->additions->first()->adicion_id);

        // Descuento exacto de insumos de plato y adición
        $this->assertEquals($initialCarneStock - 200.000, (float) $this->insumoCarne->fresh()->stock);
        $this->assertEquals($initialQuesoStock - 50.000, (float) $this->insumoQueso->fresh()->stock);

        // Estado de los ítems
        $this->assertTrue((bool) $item1->fresh()->is_paid);
        $this->assertEquals($saleId, $item1->fresh()->sale_id);
        $this->assertFalse((bool) $item2->fresh()->is_paid);

        // Comanda y mesa permanecen en cobro con saldo pendiente ($15.000)
        $freshOrder = $order->fresh();
        $this->assertEquals(15000.00, (float) $freshOrder->subtotal);
        $this->assertEquals(15000.00, (float) $freshOrder->total);
        $this->assertEquals('billed', $freshOrder->status);
        $this->assertEquals('billed', $this->mesa->fresh()->status);
    }

    /**
     * Test 2: Los platos pagados pasan a is_paid = true y son rechazados atómicamente si se intentan volver a cobrar.
     */
    public function test_cannot_resettle_already_paid_dish_items_atomic_rejection(): void
    {
        $this->actingAs($this->cajero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'customer_id' => $this->cliente->id,
            'order_number' => 'ORD-SPLIT-RETRY-02',
            'order_type' => 'table',
            'status' => 'billed',
            'guest_count' => 2,
            'subtotal' => 15000.00,
            'total' => 15000.00,
        ]);

        $itemPagado = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => true,
        ]);

        $initialSaleCount = Sale::count();

        // Intento de cobrar nuevamente el ítem que ya está pagado
        $res = $this->postJson(route('restaurant.orders.split-settle', $order), [
            'split_type' => 'items',
            'item_ids' => [$itemPagado->id],
            'amount' => 30000.00,
            'method' => 'cash',
            'cash_received' => 30000.00,
        ]);

        $res->assertStatus(422);
        $res->assertJson([
            'success' => false,
            'message' => 'Los platos seleccionados ya fueron pagados o no pertenecen a esta comanda.',
        ]);

        // Cero ventas duplicadas creadas
        $this->assertEquals($initialSaleCount, Sale::count());
    }

    /**
     * Test 3: La mesa física permanece en cobro/ocupada hasta que el último plato sea liquidado,
     * momento en el cual la mesa pasa a available (Libre) y la comanda a closed.
     */
    public function test_table_and_order_are_closed_and_freed_only_when_final_dish_is_settled(): void
    {
        $this->actingAs($this->cajero);

        $this->mesa->update(['status' => 'occupied']);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'customer_id' => $this->cliente->id,
            'order_number' => 'ORD-SPLIT-FINAL-03',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 1,
            'subtotal' => 15000.00,
            'total' => 15000.00,
        ]);

        $itemUnico = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoBatido->id,
            'quantity' => 1,
            'unit_price' => 15000.00,
            'subtotal' => 15000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        // Liquidar el único plato restante
        $res = $this->postJson(route('restaurant.orders.split-settle', $order), [
            'split_type' => 'items',
            'item_ids' => [$itemUnico->id],
            'amount' => 15000.00,
            'method' => 'transfer',
            'reference' => 'Nequi A123',
        ]);

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'is_order_closed' => true,
            'message' => 'Todos los platos han sido liquidados: comanda cerrada y mesa liberada exitosamente.',
            'table_status' => 'available',
        ]);

        // La comanda está cerrada y la mesa liberada
        $this->assertEquals('closed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->closed_at);
        $this->assertEquals('available', $this->mesa->fresh()->status);
    }

    /**
     * Test 4: Emisión de pre-cuenta parcial de platos seleccionados sin alterar el estado de la comanda
     * ni de la mesa, y con purga total de anglicismos.
     */
    public function test_partial_pre_bill_renders_selected_dishes_without_altering_order_state(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'order_number' => 'ORD-PREBILL-04',
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 45000.00,
            'total' => 45000.00,
        ]);
        $this->mesa->update(['status' => 'occupied']);

        $item1 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'status' => 'pending',
            'batch_number' => 1,
            'is_paid' => false,
        ]);

        $item2 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoBatido->id,
            'quantity' => 1,
            'unit_price' => 15000.00,
            'subtotal' => 15000.00,
            'status' => 'pending',
            'batch_number' => 1,
            'is_paid' => false,
        ]);

        $res = $this->get(route('restaurant.orders.pre-bill-partial', [
            'order' => $order->id,
            'items' => $item1->id,
        ]));

        $res->assertStatus(200);
        $res->assertViewIs('prints.pre-bill-partial');
        $res->assertSee('*** PRE-CUENTA PARCIAL (CONSUMO INDIVIDUAL) ***');
        $res->assertSee('Bowl Proteico Bochalema');
        $res->assertSee('30.000');
        $res->assertDontSee('SPLIT');
        $res->assertDontSee('Split Bill');

        // Estado inalterado
        $this->assertFalse((bool) $item1->fresh()->is_paid);
        $this->assertFalse((bool) $item2->fresh()->is_paid);
        $this->assertEquals('open', $order->fresh()->status);
        $this->assertEquals('occupied', $this->mesa->fresh()->status);
    }

    /**
     * Test 5: Comprobante térmico de 80 mm para pago parcial muestra el encabezado oficial en español.
     */
    public function test_partial_payment_receipt_renders_official_spanish_header_without_anglicisms(): void
    {
        $this->actingAs($this->cajero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-RECEIPT-05',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000.00,
            'total' => 30000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 30000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        $resSettle = $this->postJson(route('restaurant.orders.split-settle', $order), [
            'split_type' => 'items',
            'item_ids' => [$item->id],
            'amount' => 30000.00,
            'method' => 'cash',
            'cash_received' => 30000.00,
        ]);

        $resSettle->assertStatus(200);
        $saleId = $resSettle->json('sale_id');

        // Consultar la vista de tirilla térmica
        $resReceipt = $this->get(route('prints.sales.receipt', ['sale' => $saleId]));
        $resReceipt->assertStatus(200);
        $resReceipt->assertSee('*** COMPROBANTE DE PAGO PARCIAL ***');
        $resReceipt->assertSee('COBRO PARCIAL DE COMANDA: ORD-RECEIPT-05');
        $resReceipt->assertSee('MESA: Mesa 5');
        $resReceipt->assertDontSee('(SPLIT)');
        $resReceipt->assertDontSee('Split Bill');
    }

    /**
     * Test 6: Validación estricta que exige seleccionar al menos un plato para liquidar.
     */
    public function test_split_settle_validates_required_item_selection(): void
    {
        $this->actingAs($this->cajero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-VAL-06',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000.00,
            'total' => 30000.00,
        ]);

        $res = $this->postJson(route('restaurant.orders.split-settle', $order), [
            'split_type' => 'items',
            'item_ids' => [],
            'amount' => 30000.00,
            'method' => 'cash',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['item_ids']);
    }

    /**
     * Test 7: REQUERIMIENTO 4 - Liquidación de excedente restante tras división de cuenta por platos.
     * Crear comanda con 2 platos ($48.000 y $50.000 = $98.000), liquidar primer plato ($48.000) por splitAndSettle,
     * y luego liquidar el excedente restante ($50.000) mediante settle ("Cobrar Total").
     * Certifica que no lanza error de descuadre de $98.000, cierra la comanda y libera la mesa.
     */
    public function test_settle_remaining_balance_after_partial_split_settles_successfully_and_releases_table(): void
    {
        $this->actingAs($this->cajero);

        $initialStockCarne = (float) $this->insumoCarne->stock;

        // Crear productos con precio de catálogo exacto para $48.000 y $50.000
        $plato48k = Product::create([
            'sku' => 'PLA-TEST-48K',
            'nombre' => 'Plato Fit Salmón 48k',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->platoFit->category_id,
            'precio_venta' => 48000.00,
            'activo' => true,
        ]);
        $receta48 = Recipe::create([
            'producto_plato_id' => $plato48k->id,
            'nombre' => 'Receta Salmón 48k',
            'activa' => true,
        ]);
        RecipeItem::create([
            'receta_id' => $receta48->id,
            'ingrediente_id' => $this->insumoCarne->id,
            'cantidad_por_porcion' => 200.000,
            'unidad' => 'GRAMO',
        ]);

        $plato50k = Product::create([
            'sku' => 'PLA-TEST-50K',
            'nombre' => 'Plato Fit Lomo 50k',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->platoFit->category_id,
            'precio_venta' => 50000.00,
            'activo' => true,
        ]);
        $receta50 = Recipe::create([
            'producto_plato_id' => $plato50k->id,
            'nombre' => 'Receta Lomo 50k',
            'activa' => true,
        ]);
        RecipeItem::create([
            'receta_id' => $receta50->id,
            'ingrediente_id' => $this->insumoCarne->id,
            'cantidad_por_porcion' => 200.000,
            'unidad' => 'GRAMO',
        ]);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000036',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 98000.00,
            'total' => 98000.00,
        ]);

        $this->mesa->update(['status' => 'occupied']);

        // Plato 1: $48.000
        $item1 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $plato48k->id,
            'quantity' => 1,
            'unit_price' => 48000.00,
            'subtotal' => 48000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        // Plato 2: $50.000
        $item2 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $plato50k->id,
            'quantity' => 1,
            'unit_price' => 50000.00,
            'subtotal' => 50000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        // 1. Liquidar Plato 1 ($48.000) por "Dividir Cuenta por Platos"
        $splitRes = $this->postJson(route('restaurant.orders.split-settle', $order), [
            'split_type' => 'items',
            'item_ids' => [$item1->id],
            'amount' => 48000.00,
            'method' => 'cash',
            'cash_received' => 50000.00,
        ]);

        $splitRes->assertStatus(200);
        $splitRes->assertJson([
            'success' => true,
            'is_order_closed' => false,
        ]);

        // Verificar estado intermedio: comanda billed, mesa billed, plato 1 is_paid=true, plato 2 is_paid=false
        $order->refresh();
        $this->assertEquals('billed', $order->status);
        $this->assertEquals('billed', $this->mesa->fresh()->status);
        $this->assertTrue($item1->fresh()->is_paid);
        $this->assertFalse($item2->fresh()->is_paid);

        // 2. Liquidar el saldo restante ($50.000) mediante "Cobrar Total" (endpoint settle)
        $settleRes = $this->postJson(route('restaurant.orders.settle', $order), [
            'customer_name' => 'Consumidor Final',
            'customer_document' => '222222222222',
            'delivery_fee' => 0.00,
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 50000.00,
                    'cash_received' => 50000.00,
                    'reference' => null,
                ]
            ],
            'items' => [
                [
                    'product_id' => $plato50k->id,
                    'quantity' => 1,
                    'additions' => [],
                ]
            ],
        ]);

        $settleRes->assertStatus(200);
        $settleRes->assertJson([
            'success' => true,
            'message' => 'Comanda liquidada y cobrada con éxito',
        ]);

        // Verificar estado final: comanda closed, mesa available, ambos platos is_paid=true
        $order->refresh();
        $this->assertEquals('closed', $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertEquals('available', $this->mesa->fresh()->status);
        $this->assertTrue($item2->fresh()->is_paid);

        // Verificar descuento simétrico exacto de inventario (200g por plato 1 + 200g por plato 2 = 400g)
        $this->assertEquals(
            $initialStockCarne - 400.000,
            (float) $this->insumoCarne->fresh()->stock,
            'El stock de carne debe descontarse exactamente una vez por cada plato liquidado.'
        );
    }

    /**
     * Test 8: REQUERIMIENTO 2 - Cobro con Propina Voluntaria (10%) en settle y splitAndSettle.
     */
    public function test_settle_with_voluntary_tip_persists_in_sale_and_order(): void
    {
        $this->actingAs($this->cajero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-TIP-01',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 60000.00,
            'total' => 60000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 2,
            'unit_price' => 30000.00,
            'subtotal' => 60000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        // Liquidar comanda con $6.000 de propina voluntaria (10%) -> Total $66.000
        $res = $this->postJson(route('restaurant.orders.settle', $order), [
            'customer_name' => 'Comensal Feliz',
            'customer_document' => '222222222222',
            'delivery_fee' => 0.00,
            'tip' => 6000.00,
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 66000.00,
                    'cash_received' => 70000.00,
                    'reference' => null,
                ]
            ],
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 2,
                    'additions' => [],
                ]
            ],
        ]);

        $res->assertStatus(200);
        $saleId = $res->json('sale_id');

        $sale = Sale::findOrFail($saleId);
        $this->assertEquals(6000.00, (float) $sale->tip);
        $this->assertEquals(66000.00, (float) $sale->total);

        $order->refresh();
        $this->assertEquals(6000.00, (float) $order->tip);

        // Verificar que en la tirilla térmica aparezca explícitamente PROPINA VOLUNTARIA
        $printRes = $this->get(route('prints.sales.receipt', $sale));
        $printRes->assertStatus(200);
        $printRes->assertSee('PROPINA VOLUNTARIA:');
        $printRes->assertSee('6.000');
    }

    /**
     * Test 9: REQUERIMIENTO 1 - Liquidación parcial enriquecida con descuento, propina, cliente y pagos mixtos.
     */
    public function test_split_settle_with_discount_tip_customer_and_mixed_payments(): void
    {
        $this->actingAs($this->cajero);

        $plato40k = Product::create([
            'sku' => 'PLA-TEST-40K',
            'nombre' => 'Plato Gourmet 40k',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->platoFit->category_id,
            'precio_venta' => 40000.00,
            'activo' => true,
        ]);
        $receta40k = Recipe::create([
            'producto_plato_id' => $plato40k->id,
            'nombre' => 'Receta Gourmet 40k',
            'activa' => true,
        ]);
        RecipeItem::create([
            'receta_id' => $receta40k->id,
            'ingrediente_id' => $this->insumoCarne->id,
            'cantidad_por_porcion' => 100.000,
            'unidad' => 'GRAMO',
        ]);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-ENRICH-01',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 80000.00,
            'total' => 80000.00,
        ]);

        $item1 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $plato40k->id,
            'quantity' => 1,
            'unit_price' => 40000.00,
            'subtotal' => 40000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        $item2 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $plato40k->id,
            'quantity' => 1,
            'unit_price' => 40000.00,
            'subtotal' => 40000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        // Liquidar plato 1 ($40.000) con 10% descuento (-$4.000), 10% propina (+$4.000) -> Total = $40.000
        // Pagos mixtos: $20.000 efectivo + $20.000 Nequi (transfer)
        $res = $this->postJson(route('restaurant.orders.split-settle', $order), [
            'split_type' => 'items',
            'item_ids' => [$item1->id],
            'customer_id' => $this->cliente->id,
            'customer_name' => $this->cliente->name,
            'customer_document' => $this->cliente->document,
            'discount_percentage' => 10,
            'discount' => 4000.00,
            'tip' => 4000.00,
            'notes' => 'Comensal 1 pagó con Nequi y efectivo',
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 20000.00,
                    'cash_received' => 20000.00,
                    'reference' => null,
                ],
                [
                    'method' => 'transfer',
                    'amount' => 20000.00,
                    'cash_received' => null,
                    'reference' => 'Nequi #12345',
                ],
            ],
        ]);

        $res->assertStatus(200);
        $saleId = $res->json('sale_id');
        $sale = Sale::findOrFail($saleId);

        $this->assertEquals($this->cliente->id, $sale->customer_id);
        $this->assertEquals(4000.00, (float) $sale->discount);
        $this->assertEquals(4000.00, (float) $sale->tip);
        $this->assertEquals(40000.00, (float) $sale->total);
        $this->assertEquals('Comensal 1 pagó con Nequi y efectivo', $sale->notes);

        // Verificar 2 registros de pago en sale_payments
        $this->assertCount(2, $sale->payments);
    }

    /**
     * Verifica que en RestaurantOrderController::show() y printPartialPreBill()
     * el subtotal de ítems con adiciones no se cobre/sume dos veces.
     */
    public function test_order_show_and_partial_pre_bill_do_not_duplicate_additions_subtotal(): void
    {
        // 1. Abrir orden con 1 Bowl ($30.000) y adición de Queso ($5.000) -> subtotal esperado = $35.000
        $this->mesa->update(['status' => 'occupied']);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa->id,
            'user_id' => $this->mesero->id,
            'customer_id' => $this->cliente->id,
            'order_number' => 'ORD-TEST-ADD-01',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 1,
            'subtotal' => 35000.00,
            'total' => 35000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 30000.00,
            'subtotal' => 35000.00,
            'batch_number' => 1,
            'status' => 'delivered',
            'is_paid' => false,
        ]);

        RestaurantOrderItemAddition::create([
            'order_item_id' => $item->id,
            'adicion_id' => $this->adicionQueso->id,
            'quantity' => 1,
            'unit_price' => 5000.00,
            'subtotal' => 5000.00,
        ]);

        $this->assertEquals(35000.00, (float) $item->subtotal);

        // 2. Verificar que en show() el pendingSubtotal sea exactamente $35.000 (y NO $40.000)
        $showResponse = $this->actingAs($this->mesero)->get(route('restaurant.orders.show', $order));
        $showResponse->assertOk();
        $showResponse->assertViewHas('pendingSubtotal', 35000.00);

        // 3. Verificar que en printPartialPreBill() el subtotal sea exactamente $35.000 (y NO $40.000)
        $preBillResponse = $this->actingAs($this->cajero)->post(route('restaurant.orders.pre-bill-partial', $order), [
            'item_ids' => [$item->id],
        ]);

        $preBillResponse->assertOk();
        $preBillResponse->assertViewHas('subtotal', 35000.00);
        $preBillResponse->assertViewHas('total', 35000.00);
        $this->assertStringNotContainsString('40.000', $preBillResponse->getContent());
    }
}
