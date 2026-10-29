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
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantOrderItemAddition;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleDetailAddition;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalePrintControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $cajero;
    protected Customer $cliente;
    protected Product $carne;
    protected Product $platoFit;
    protected Addition $adicionCarne;
    protected Sale $sale;
    protected RestaurantOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'slug' => Role::ROLE_ADMIN,
            'name' => 'Administrador',
        ]);

        $this->cajero = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->cliente = Customer::create([
            'name' => 'Marcos Gómez',
            'document' => '94000111',
            'email' => 'marcos@fit.com',
            'phone' => '3120000000',
            'is_active' => true,
        ]);

        $catInsumo = Category::create(['name' => 'Insumos', 'is_active' => true]);
        $catPlato = Category::create(['name' => 'Platos', 'is_active' => true]);

        $this->carne = Product::create([
            'sku' => 'INS-001',
            'nombre' => 'Lomo Fino',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumo->id,
            'stock' => 5000.000,
            'precio_compra' => 30.00,
            'precio_venta' => 0.00,
            'activo' => true,
        ]);

        $this->platoFit = Product::create([
            'sku' => 'PLA-001',
            'nombre' => 'Lomo Fit Bochalema',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlato->id,
            'stock' => 0.000,
            'precio_compra' => 15000.00,
            'precio_venta' => 32000.00,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $this->platoFit->id,
            'nombre' => 'Receta Lomo',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad_por_porcion' => 220.000,
            'unidad' => 'GRAMO',
        ]);

        $this->adicionCarne = Addition::create([
            'nombre' => 'Carne Extra 120g',
            'precio' => 9000.00,
            'activo' => true,
        ]);

        AdditionItem::create([
            'adicion_id' => $this->adicionCarne->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad' => 120.000,
        ]);

        // Crear una Venta completa con plato, adición y pago
        $this->sale = Sale::create([
            'user_id' => $this->cajero->id,
            'customer_id' => $this->cliente->id,
            'customer_name' => $this->cliente->name,
            'customer_document' => $this->cliente->document,
            'order_type' => 'retail',
            'invoice_number' => 'FAC-000045',
            'subtotal' => 41000.00,
            'total' => 41000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $this->sale->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 32000.00,
            'subtotal' => 32000.00,
        ]);

        SaleDetailAddition::create([
            'sale_detail_id' => $detail->id,
            'adicion_id' => $this->adicionCarne->id,
            'quantity' => 1,
            'unit_price' => 9000.00,
            'subtotal' => 9000.00,
        ]);

        SalePayment::create([
            'sale_id' => $this->sale->id,
            'method' => 'cash',
            'amount' => 41000.00,
            'cash_received' => 50000.00,
            'change_given' => 9000.00,
        ]);

        // Crear una orden de restaurante para pruebas de tickets de cocina
        $this->order = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-000999',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'notes' => 'Término 3/4 bien sellado',
            'subtotal' => 41000.00,
            'total' => 41000.00,
        ]);

        $orderItem = RestaurantOrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 2,
            'unit_price' => 32000.00,
            'subtotal' => 64000.00,
            'notes' => 'Sin sal marina',
            'batch_number' => 1,
        ]);

        RestaurantOrderItemAddition::create([
            'order_item_id' => $orderItem->id,
            'adicion_id' => $this->adicionCarne->id,
            'quantity' => 2,
            'unit_price' => 9000.00,
            'subtotal' => 18000.00,
        ]);
    }

    public function test_print_receipt_renders_successfully(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->get(route('prints.sales.receipt', $this->sale));

        $response->assertStatus(200);
        $response->assertSee('SABORCALIFIT | BOCHALEMA');
        $response->assertSee('Arbolatta Mall');
        $response->assertSee('FAC-000045');
        $response->assertSee('Lomo Fit Bochalema');
        $response->assertDontSee('watermark-container');
        $response->assertSee('images/brand/logo-califit.svg');
        $response->assertSee('Comprobante interno de venta');
    }

    public function test_kitchen_ticket_omits_prices(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->get(route('prints.orders.kitchen', ['order' => $this->order, 'batch' => 1]));

        $response->assertStatus(200);
        $response->assertSee('*** COMANDA COCINA / BARRA ***');
        $response->assertSee('ORD-000999');
        $response->assertSee('TANDA #1');
        $response->assertSee('Lomo Fit Bochalema');
        $response->assertSee('Carne Extra 120g');
        $response->assertSee('Sin sal marina');
        $response->assertSee('Término 3/4 bien sellado');

        // REGLA ESTRICTA: El ticket de cocina NUNCA debe incluir el símbolo de moneda $ ni subtotales monetarios
        $content = $response->getContent();
        $this->assertStringNotContainsString('$', $content, 'El ticket de cocina no debe contener el símbolo $');
        $this->assertStringNotContainsString('Subtotal', $content, 'El ticket de cocina no debe contener subtotales');
        $this->assertStringNotContainsString('TOTAL', $content, 'El ticket de cocina no debe contener totales');
    }

    public function test_dispatch_ticket_omits_delivery_fee_for_takeout_orders(): void
    {
        $this->actingAs($this->cajero);

        $takeoutOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-000101',
            'order_type' => 'takeout',
            'status' => 'in_kitchen',
            'customer_name' => 'Carlos Recoge',
            'delivery_fee' => 0.00,
            'subtotal' => 32000.00,
            'total' => 32000.00,
        ]);

        RestaurantOrderItem::create([
            'order_id' => $takeoutOrder->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 32000.00,
            'subtotal' => 32000.00,
            'status' => 'kitchen',
            'batch_number' => 1,
        ]);

        $response = $this->get(route('prints.orders.dispatch', $takeoutOrder));

        $response->assertStatus(200);
        $response->assertSee('VALOR PRODUCTOS:');
        $response->assertSee('TOTAL A COBRAR:');
        $response->assertDontSee('FLETE DOMICILIO');
    }

    public function test_dispatch_ticket_shows_delivery_fee_for_delivery_orders_even_when_zero(): void
    {
        $this->actingAs($this->cajero);

        $deliveryOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-000102',
            'order_type' => 'delivery',
            'status' => 'in_kitchen',
            'customer_name' => 'María Domicilio',
            'delivery_fee' => 0.00,
            'subtotal' => 32000.00,
            'total' => 32000.00,
        ]);

        RestaurantOrderItem::create([
            'order_id' => $deliveryOrder->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 32000.00,
            'subtotal' => 32000.00,
            'status' => 'kitchen',
            'batch_number' => 1,
        ]);

        $response = $this->get(route('prints.orders.dispatch', $deliveryOrder));

        $response->assertStatus(200);
        $response->assertSee('VALOR PRODUCTOS:');
        $response->assertSee('FLETE DOMICILIO:');
        $response->assertSee('$0');
        $response->assertSee('TOTAL A COBRAR:');
    }

    public function test_dispatch_ticket_shows_delivery_fee_for_delivery_orders_with_positive_fee(): void
    {
        $this->actingAs($this->cajero);

        $deliveryOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-000103',
            'order_type' => 'delivery',
            'status' => 'in_kitchen',
            'customer_name' => 'Andrés Domicilio',
            'delivery_fee' => 5000.00,
            'subtotal' => 32000.00,
            'total' => 37000.00,
        ]);

        RestaurantOrderItem::create([
            'order_id' => $deliveryOrder->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 32000.00,
            'subtotal' => 32000.00,
            'status' => 'kitchen',
            'batch_number' => 1,
        ]);

        $response = $this->get(route('prints.orders.dispatch', $deliveryOrder));

        $response->assertStatus(200);
        $response->assertSee('VALOR PRODUCTOS:');
        $response->assertSee('FLETE DOMICILIO:');
        $response->assertSee('$5.000');
        $response->assertSee('TOTAL A COBRAR:');
        $response->assertSee('$37.000');
    }

    public function test_dispatch_store_forces_zero_delivery_fee_for_takeout_orders(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson(route('restaurant.dispatch.store'), [
            'order_type' => 'takeout',
            'customer_name' => 'Cliente Para Llevar',
            'phone' => '3151234567',
            'delivery_fee' => 6000.00, // Debe forzarse autoritativamente a 0
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJson(['success' => true]);

        $order = RestaurantOrder::where('customer_name', 'Cliente Para Llevar')->first();
        $this->assertNotNull($order);
        $this->assertEquals('takeout', $order->order_type);
        $this->assertEquals(0.00, (float) $order->delivery_fee);
        $this->assertEquals(32000.00, (float) $order->total);
    }

    public function test_kitchen_ticket_shows_reprint_label_when_reprint_flag_is_present(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->get(route('prints.orders.kitchen', ['order' => $this->order, 'batch' => 1]) . '?reprint=1');

        $response->assertStatus(200);
        $response->assertSee('** COMANDA REIMPRESA **');
    }

    public function test_kitchen_ticket_omits_reprint_label_on_normal_first_print(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->get(route('prints.orders.kitchen', ['order' => $this->order, 'batch' => 1]));

        $response->assertStatus(200);
        $response->assertDontSee('** COMANDA REIMPRESA **');
    }

    public function test_dispatch_store_returns_kitchen_ticket_as_print_url(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->postJson(route('restaurant.dispatch.store'), [
            'order_type' => 'delivery',
            'customer_name' => 'Cliente Despacho Cocina',
            'phone' => '3159998877',
            'address' => 'Carrera 100 # 16-20',
            'delivery_fee' => 5000.00,
            'items' => [
                [
                    'product_id' => $this->platoFit->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJson(['success' => true]);

        $order = RestaurantOrder::where('customer_name', 'Cliente Despacho Cocina')->first();
        $this->assertNotNull($order);
        $expectedPrintUrl = route('prints.orders.kitchen', ['order' => $order->id, 'batch' => 1]);
        $response->assertJson(['print_url' => $expectedPrintUrl]);
        $this->assertEquals($expectedPrintUrl, session('print_url'));
    }

    public function test_pre_bill_omits_suggested_tip_for_delivery_and_takeout_orders(): void
    {
        $this->actingAs($this->cajero);

        $deliveryOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-000555',
            'order_type' => 'delivery',
            'status' => 'in_kitchen',
            'customer_name' => 'Cliente Sin Propina',
            'delivery_fee' => 4000.00,
            'subtotal' => 32000.00,
            'total' => 36000.00,
        ]);

        RestaurantOrderItem::create([
            'order_id' => $deliveryOrder->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 32000.00,
            'subtotal' => 32000.00,
            'status' => 'kitchen',
            'batch_number' => 1,
        ]);

        $response = $this->get(route('prints.orders.pre-bill', $deliveryOrder));

        $response->assertStatus(200);
        $response->assertSee('ORD-000555');
        $response->assertDontSee('Servicio voluntario sugerido');
        $response->assertDontSee('TOTAL CON SERVICIO:');
    }

    /**
     * Certifica que todas las plantillas de impresión térmica están calibradas al estándar de 80 mm
     * (área imprimible útil 72 mm - 74 mm, contenedor max-width: 74mm, contraste #000000, logo y WhatsApp 312 770 4455).
     */
    public function test_all_thermal_print_templates_are_calibrated_for_80mm_standard(): void
    {
        $this->actingAs($this->cajero);

        // 1. Factura / Comprobante de venta (receipt)
        $resReceipt = $this->get(route('prints.sales.receipt', $this->sale));
        $resReceipt->assertOk();
        $resReceipt->assertSee('size: 80mm auto', false);
        $resReceipt->assertSee('max-width: 74mm', false);
        $resReceipt->assertSee('max-width: 62mm', false);
        $resReceipt->assertSee('max-height: 42mm', false);
        $resReceipt->assertSee('images/brand/logo-califit.svg');
        $resReceipt->assertSee('312 770 4455');
        $resReceipt->assertDontSee('+57 312 770 4455');
        $resReceipt->assertSee('width: 11%', false);
        $resReceipt->assertSee('width: 41%', false);
        $resReceipt->assertSee('width: 23%', false);
        $resReceipt->assertSee('width: 25%', false);

        // 2. Comanda de cocina (kitchen)
        $resKitchen = $this->get(route('prints.orders.kitchen', ['order' => $this->order, 'batch' => 1]));
        $resKitchen->assertOk();
        $resKitchen->assertSee('size: 80mm auto', false);
        $resKitchen->assertSee('max-width: 74mm', false);
        $resKitchen->assertSee('max-width: 62mm', false);
        $resKitchen->assertSee('images/brand/logo-califit.svg');

        // 3. Pre-cuenta general (pre-bill)
        $resPreBill = $this->get(route('prints.orders.pre-bill', $this->order));
        $resPreBill->assertOk();
        $resPreBill->assertSee('size: 80mm auto', false);
        $resPreBill->assertSee('max-width: 74mm', false);
        $resPreBill->assertSee('max-width: 62mm', false);
        $resPreBill->assertSee('images/brand/logo-califit.svg');
        $resPreBill->assertSee('312 770 4455');
        $resPreBill->assertDontSee('+57 312 770 4455');

        // 4. Pre-cuenta parcial por platos (pre-bill-partial)
        $orderItem = $this->order->items()->first();
        $resPreBillPartial = $this->get(route('restaurant.orders.pre-bill-partial', [
            'order' => $this->order,
            'items' => $orderItem->id,
        ]));
        $resPreBillPartial->assertOk();
        $resPreBillPartial->assertSee('size: 80mm auto', false);
        $resPreBillPartial->assertSee('max-width: 74mm', false);
        $resPreBillPartial->assertSee('max-width: 62mm', false);
        $resPreBillPartial->assertSee('images/brand/logo-califit.svg');
        $resPreBillPartial->assertSee('312 770 4455');
        $resPreBillPartial->assertDontSee('+57 312 770 4455');

        // 5. Tirilla de despacho (dispatch)
        $resDispatch = $this->get(route('prints.orders.dispatch', $this->order));
        $resDispatch->assertOk();
        $resDispatch->assertSee('size: 80mm auto', false);
        $resDispatch->assertSee('max-width: 74mm', false);
        $resDispatch->assertSee('max-width: 62mm', false);
        $resDispatch->assertSee('images/brand/logo-califit.svg');
        $resDispatch->assertSee('312 770 4455');
        $resDispatch->assertDontSee('+57 312 770 4455');

        // 6. Tirilla de cuadre de caja (cash-register)
        $resCash = $this->get(route('prints.cash-register'));
        $resCash->assertOk();
        $resCash->assertSee('size: 80mm auto', false);
        $resCash->assertSee('max-width: 74mm', false);
        $resCash->assertSee('max-width: 62mm', false);
        $resCash->assertSee('images/brand/logo-califit.svg');
        $resCash->assertSee('312 770 4455');
        $resCash->assertDontSee('+57 312 770 4455');
        $resCash->assertDontSee('#EEEEEE', false);
    }

    public function test_dispatch_ticket_displays_separated_customer_info_and_itemized_prices(): void
    {
        $this->actingAs($this->cajero);

        $deliveryOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-000888',
            'order_type' => 'delivery',
            'status' => 'in_kitchen',
            'customer_name' => 'Valentina Restrepo',
            'delivery_phone' => '3161234567',
            'delivery_address' => 'Cra 105 # 14-30 Apto 402',
            'notes' => 'Dejar en portería con el celador de turno',
            'delivery_fee' => 4000.00,
            'subtotal' => 41000.00,
            'total' => 45000.00,
        ]);

        $item = RestaurantOrderItem::create([
            'order_id' => $deliveryOrder->id,
            'product_id' => $this->platoFit->id,
            'quantity' => 1,
            'unit_price' => 32000.00,
            'subtotal' => 32000.00,
            'status' => 'kitchen',
            'batch_number' => 1,
        ]);

        RestaurantOrderItemAddition::create([
            'order_item_id' => $item->id,
            'adicion_id' => $this->adicionCarne->id,
            'quantity' => 1,
            'unit_price' => 9000.00,
            'subtotal' => 9000.00,
        ]);

        $response = $this->get(route('prints.orders.dispatch', $deliveryOrder));

        $response->assertStatus(200);
        $response->assertSee('CLIENTE:');
        $response->assertSee('Valentina Restrepo');
        $response->assertSee('TELÉFONO:');
        $response->assertSee('3161234567');
        $response->assertSee('DIRECCIÓN:');
        $response->assertSee('Cra 105 # 14-30 Apto 402');
        $response->assertSee('INSTRUCCIONES:');
        $response->assertSee('Dejar en portería con el celador de turno');

        // Precios discriminados de platos y adiciones
        $response->assertSee('VALOR');
        $response->assertSee('Lomo Fit Bochalema');
        $response->assertSee('$32.000');
        $response->assertSee('+ Carne Extra 120g');
        $response->assertSee('$9.000');

        // Ausencia del precinto de seguridad y texto simplificado
        $response->assertDontSee('Verificar precinto de seguridad');
        $response->assertSee('¡Que disfrutes tu comida saludable!');
    }

    public function test_receipt_for_retail_pos_omits_partial_payment_and_table_labels(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->get(route('prints.sales.receipt', $this->sale));

        $response->assertStatus(200);
        $response->assertSee('CANAL:');
        $response->assertSee('MOSTRADOR POS');
        $response->assertDontSee('*** COMPROBANTE DE PAGO PARCIAL ***');
        $response->assertDontSee('MESA: Salón');
    }

    public function test_receipt_for_delivery_and_takeout_omits_partial_payment_and_table_labels(): void
    {
        $this->actingAs($this->cajero);

        // Pedido de delivery
        $delOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-DEL-777',
            'order_type' => 'delivery',
            'status' => 'delivered',
            'customer_name' => 'Felipe Delivery',
            'delivery_phone' => '3189990000',
            'delivery_address' => 'Cl 50 # 100-20',
            'delivery_fee' => 5000.00,
            'subtotal' => 32000.00,
            'total' => 37000.00,
        ]);

        $saleDel = Sale::create([
            'user_id' => $this->cajero->id,
            'restaurant_order_id' => $delOrder->id,
            'customer_name' => 'Felipe Delivery',
            'customer_document' => 'CC-123456',
            'order_type' => 'delivery',
            'invoice_number' => 'FAC-DEL-01',
            'subtotal' => 32000.00,
            'total' => 37000.00,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $resDel = $this->get(route('prints.sales.receipt', $saleDel));
        $resDel->assertStatus(200);
        $resDel->assertSee('CANAL:');
        $resDel->assertSee('DOMICILIO');
        $resDel->assertSee('ORDEN DE DOMICILIO:');
        $resDel->assertSee('ORD-DEL-777');
        $resDel->assertDontSee('*** COMPROBANTE DE PAGO PARCIAL ***');
        $resDel->assertDontSee('MESA: Salón');

        // Pedido para llevar (takeout)
        $takeOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-TAK-888',
            'order_type' => 'takeout',
            'status' => 'delivered',
            'customer_name' => 'Diana Llevar',
            'delivery_phone' => '3171112233',
            'delivery_fee' => 0.00,
            'subtotal' => 32000.00,
            'total' => 32000.00,
        ]);

        $saleTake = Sale::create([
            'user_id' => $this->cajero->id,
            'restaurant_order_id' => $takeOrder->id,
            'customer_name' => 'Diana Llevar',
            'customer_document' => 'CC-654321',
            'order_type' => 'takeout',
            'invoice_number' => 'FAC-TAK-01',
            'subtotal' => 32000.00,
            'total' => 32000.00,
            'payment_method' => 'nequi',
            'status' => 'completed',
        ]);

        $resTake = $this->get(route('prints.sales.receipt', $saleTake));
        $resTake->assertStatus(200);
        $resTake->assertSee('CANAL:');
        $resTake->assertSee('PARA LLEVAR / RECOGER');
        $resTake->assertSee('ORDEN PARA LLEVAR:');
        $resTake->assertSee('ORD-TAK-888');
        $resTake->assertDontSee('*** COMPROBANTE DE PAGO PARCIAL ***');
        $resTake->assertDontSee('MESA: Salón');
    }

    public function test_takeout_order_can_transition_directly_from_in_kitchen_to_delivered(): void
    {
        $this->actingAs($this->cajero);

        $takeoutOrder = RestaurantOrder::create([
            'user_id' => $this->cajero->id,
            'order_number' => 'ORD-TAK-999',
            'order_type' => 'takeout',
            'status' => 'in_kitchen',
            'customer_name' => 'Andrés Llevar',
            'delivery_fee' => 0.00,
            'subtotal' => 32000.00,
            'total' => 32000.00,
        ]);

        $response = $this->patchJson(route('restaurant.dispatch.update-status', $takeoutOrder), [
            'status' => 'delivered',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'delivered',
        ]);

        $this->assertEquals('delivered', $takeoutOrder->fresh()->status);
    }
}
