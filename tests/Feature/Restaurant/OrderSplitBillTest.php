<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderSplitBillTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $employeeA;
    protected User $adminB;
    protected RestaurantTable $tableA;
    protected RestaurantTable $tableB;
    protected Product $productPizza;
    protected Product $productBeer;
    protected Product $productBurger;
    protected Product $productSoda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->businessA = Business::create([
            'name' => 'Restaurante Cali Sabor A',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->businessB = Business::create([
            'name' => 'Restaurante Cali Sabor B',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create(['name' => 'Admin Restaurante A']);
        $this->businessA->users()->attach($this->adminA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeA = User::factory()->create(['name' => 'Mesero Restaurante A']);
        $this->businessA->users()->attach($this->employeeA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->adminB = User::factory()->create(['name' => 'Admin Restaurante B']);
        $this->businessB->users()->attach($this->adminB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->tableA = RestaurantTable::create([
            'business_id' => $this->businessA->id,
            'name' => 'Mesa 2',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $this->tableB = RestaurantTable::create([
            'business_id' => $this->businessB->id,
            'name' => 'Mesa B1',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $categoryA = Category::create([
            'business_id' => $this->businessA->id,
            'name' => 'Platos Fuertes',
        ]);

        $this->productPizza = Product::create([
            'business_id' => $this->businessA->id,
            'category_id' => $categoryA->id,
            'name' => 'Pizza Artesanal Familiar',
            'sku' => 'PLT-PIZZA-01',
            'product_type' => 'dish',
            'cost_price' => 20000,
            'sale_price' => 45000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->productBeer = Product::create([
            'business_id' => $this->businessA->id,
            'category_id' => $categoryA->id,
            'name' => 'Cerveza Club Colombia',
            'sku' => 'BEB-CERV-01',
            'product_type' => 'standard',
            'cost_price' => 4000,
            'sale_price' => 8000,
            'stock' => 24,
            'is_active' => true,
        ]);

        $this->productBurger = Product::create([
            'business_id' => $this->businessA->id,
            'category_id' => $categoryA->id,
            'name' => 'Hamburguesa Especial',
            'sku' => 'PLT-BURG-01',
            'product_type' => 'dish',
            'cost_price' => 12000,
            'sale_price' => 28000,
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->productSoda = Product::create([
            'business_id' => $this->businessA->id,
            'category_id' => $categoryA->id,
            'name' => 'Gaseosa Manzana 400ml',
            'sku' => 'BEB-SODA-01',
            'product_type' => 'standard',
            'cost_price' => 2500,
            'sale_price' => 5000,
            'stock' => 30,
            'is_active' => true,
        ]);
    }

    /**
     * Test 1: División parcial de cuenta.
     * Se seleccionan 2 de 4 ítems.
     * Los 2 ítems pasan a payment_status = paid.
     * Los otros 2 ítems permanecen pending.
     * La orden permanece en estado open.
     * La mesa permanece en estado occupied.
     */
    public function test_partial_split_bill_pays_selected_items_and_leaves_order_open_and_table_occupied(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0101',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 86000,
            'total' => 86000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productBeer->id,
            'quantity' => 1,
            'unit_price' => 8000,
            'subtotal' => 8000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $item3 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productBurger->id,
            'quantity' => 1,
            'unit_price' => 28000,
            'subtotal' => 28000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $item4 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productSoda->id,
            'quantity' => 1,
            'unit_price' => 5000,
            'subtotal' => 5000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        // Verificación inicial de helpers
        $this->assertCount(4, $order->unpaidItems());
        $this->assertEquals(86000, $order->pendingBalance());
        $this->assertFalse($order->isFullyPaid());

        // El comensal 1 paga sus consumos: Pizza ($45.000) y Cerveza ($8.000) = $53.000
        $payload = [
            'items' => [$item1->id, $item2->id],
            'payment_method' => 'cash',
            'cash_received' => 60000,
            'reference' => 'Comensal 1 - Pago en Efectivo',
        ];

        $response = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $order), $payload);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success');
        $response->assertSessionHas('print_split_ticket_id');

        // 1. Los 2 ítems pasan a payment_status = paid
        $item1->refresh();
        $item2->refresh();
        $this->assertEquals('paid', $item1->payment_status);
        $this->assertNotNull($item1->paid_at);
        $this->assertEquals('paid', $item2->payment_status);
        $this->assertNotNull($item2->paid_at);

        // 2. Los otros 2 ítems permanecen pending
        $item3->refresh();
        $item4->refresh();
        $this->assertEquals('pending', $item3->payment_status);
        $this->assertNull($item3->paid_at);
        $this->assertEquals('pending', $item4->payment_status);
        $this->assertNull($item4->paid_at);

        // 3. La orden permanece en estado open
        $order->refresh();
        $this->assertEquals('open', $order->status);
        $this->assertNull($order->closed_at);

        // 4. La mesa permanece en estado occupied
        $this->tableA->refresh();
        $this->assertEquals('occupied', $this->tableA->status);

        // 5. Helpers reflejan el nuevo estado pendiente
        $this->assertCount(2, $order->unpaidItems());
        $this->assertEquals(33000, $order->pendingBalance()); // 28000 + 5000
        $this->assertFalse($order->isFullyPaid());

        // 6. Verificar creación de la venta parcial y su registro de pago
        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(53000, $sale->total);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 53000,
            'cash_received' => 60000,
            'change_given' => 7000,
        ]);
    }

    /**
     * Test 2: División final y cierre de mesa.
     * Se paga el último ítem pendiente restante.
     * La orden pasa a billed.
     * La mesa pasa a available.
     */
    public function test_final_split_bill_settlement_closes_order_to_billed_and_frees_table(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0102',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 33000,
            'total' => 33000,
        ]);

        // Ítem previamente pagado
        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'payment_status' => 'paid',
            'paid_at' => now()->subMinutes(10),
            'status' => 'served',
        ]);

        // Último ítem pendiente restante
        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productBurger->id,
            'quantity' => 1,
            'unit_price' => 28000,
            'subtotal' => 28000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $payload = [
            'items' => [$item2->id],
            'payment_method' => 'card',
            'reference' => 'Voucher Datáfono Redeban #8921',
        ];

        $response = $this->actingAs($this->adminA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $order), $payload);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        // 1. El ítem pasa a paid
        $item2->refresh();
        $this->assertEquals('paid', $item2->payment_status);
        $this->assertNotNull($item2->paid_at);

        // 2. La orden pasa a billed
        $order->refresh();
        $this->assertEquals('billed', $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertTrue($order->isFullyPaid());
        $this->assertCount(0, $order->unpaidItems());
        $this->assertEquals(0, $order->pendingBalance());

        // 3. La mesa pasa a available
        $this->tableA->refresh();
        $this->assertEquals('available', $this->tableA->status);
    }

    /**
     * Test 3: Validación y Multi-tenancy.
     * Rechazo si se intenta pagar un ítem perteneciente a otra comanda o a otro business_id.
     * Rechazo si no se selecciona ningún plato.
     */
    public function test_validation_and_multi_tenancy_protections(): void
    {
        // Orden legítima en Business A
        $orderA = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0103',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        $itemA = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $orderA->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 20000,
            'subtotal' => 20000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        // Orden e ítem de OTRA empresa (Business B)
        $orderB = RestaurantOrder::create([
            'business_id' => $this->businessB->id,
            'table_id' => $this->tableB->id,
            'user_id' => $this->adminB->id,
            'order_number' => 'ORD-B-0001',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 50000,
            'total' => 50000,
        ]);

        $itemB = RestaurantOrderItem::create([
            'business_id' => $this->businessB->id,
            'order_id' => $orderB->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'subtotal' => 50000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        // Orden B dentro del MISMO Business A para probar ítem de otra comanda
        $orderA2 = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0104-B',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        $itemA2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $orderA2->id,
            'product_id' => $this->productSoda->id,
            'quantity' => 3,
            'unit_price' => 5000,
            'subtotal' => 15000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        // Caso A: Rechazo si no se selecciona ningún plato
        $responseEmpty = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $orderA), [
                'items' => [],
                'payment_method' => 'cash',
                'cash_received' => 20000,
            ]);

        $responseEmpty->assertSessionHasErrors('items');

        // Caso B: Rechazo si se intenta pagar un ítem de OTRA comanda del mismo negocio
        $responseOtherOrder = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $orderA), [
                'items' => [$itemA2->id],
                'payment_method' => 'cash',
                'cash_received' => 15000,
            ]);

        $responseOtherOrder->assertSessionHasErrors('items');

        // Caso C: Rechazo si se intenta pagar un ítem de otro tenant (Business B)
        $responseCrossTenant = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $orderA), [
                'items' => [$itemB->id],
                'payment_method' => 'cash',
                'cash_received' => 50000,
            ]);

        $responseCrossTenant->assertSessionHasErrors('items');

        // Caso D: Rechazo/Aislamiento si un usuario de Business B intenta cobrar comanda de Business A
        $responseUnauthorized = $this->actingAs($this->adminB)
            ->withSession(['current_business_id' => $this->businessB->id])
            ->post(route('restaurant.orders.split-payment', $orderA), [
                'items' => [$itemA->id],
                'payment_method' => 'cash',
                'cash_received' => 20000,
            ]);

        $this->assertTrue(in_array($responseUnauthorized->status(), [403, 404], true));
    }

    /**
     * Test 4: Comprobantes e impresión térmica de 80mm para pre-cuenta parcial y ticket fiscal.
     */
    public function test_print_split_precount_and_split_ticket_render_correctly(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0104',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 53000,
            'total' => 53000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productBeer->id,
            'quantity' => 1,
            'unit_price' => 8000,
            'subtotal' => 8000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        // 1. Ver Pre-cuenta parcial de los ítems seleccionados
        $precountResponse = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('restaurant.orders.print-split-precount', [
                'order' => $order,
                'items' => [$item1->id],
            ]));

        $precountResponse->assertOk();
        $precountResponse->assertSee('PRE-CUENTA PARCIAL (Mesa 2)');
        $precountResponse->assertSee('Pizza Artesanal Familiar');
        $precountResponse->assertSee('DOCUMENTO INTERNO NO VÁLIDO COMO FACTURA O COMPROBANTE DE VENTA');
        $precountResponse->assertDontSee('Cerveza Club Colombia');

        // 2. Liquidar ítem 1
        $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$item1->id],
                'payment_method' => 'cash',
                'cash_received' => 50000,
            ]);

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);

        // 3. Ver ticket comprobante térmico del pago parcial
        $ticketResponse = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('restaurant.orders.print-split-ticket', [
                'order' => $order,
                'payment' => $sale,
            ]));

        $ticketResponse->assertOk();
        $ticketResponse->assertSee('COMPROBANTE DE PAGO PARCIAL');
        $ticketResponse->assertSee($sale->invoice_number);
        $ticketResponse->assertSee('Pizza Artesanal Familiar');
        $ticketResponse->assertSee('Saldo Pendiente Comanda:');
        $ticketResponse->assertSee('Mesa:');
    }

    /**
     * Test 5: División de cuenta con cliente registrado y porcentaje de descuento.
     */
    public function test_split_bill_with_customer_and_discount(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0105',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 53000,
            'total' => 53000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productBeer->id,
            'quantity' => 1,
            'unit_price' => 8000,
            'subtotal' => 8000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $customer = Customer::create([
            'business_id' => $this->businessA->id,
            'name' => 'Carlos Rodriguez',
            'document' => '1144123456',
            'is_active' => true,
        ]);

        // Pizza: $45.000 con 10% de descuento = $4.500 desc -> Total a liquidar: $40.500
        $payload = [
            'items' => [$item1->id],
            'customer_id' => $customer->id,
            'discount_percentage' => 10,
            'payments' => [
                [
                    'method' => 'card',
                    'amount' => 40500,
                    'reference' => 'VOUCHER-CC-7788',
                ]
            ],
            'notes' => 'Cliente VIP - Descuento 10%',
        ];

        $response = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $order), $payload);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals($customer->id, $sale->customer_id);
        $this->assertEquals('Carlos Rodriguez', $sale->customer_name);
        $this->assertEquals('1144123456', $sale->customer_document);
        $this->assertEquals(45000, (float) $sale->subtotal);
        $this->assertEquals(4500, (float) $sale->discount);
        $this->assertEquals(10, (float) $sale->discount_percentage);
        $this->assertEquals(40500, (float) $sale->total);
        $this->assertEquals('Cliente VIP - Descuento 10%', $sale->notes);
        $this->assertEquals('paid', $item1->fresh()->payment_status);
        $this->assertEquals('pending', $item2->fresh()->payment_status);
    }

    /**
     * Test 6: División de cuenta con servicio/propina (10%) e Impuesto al Consumo (INC 8%).
     */
    public function test_split_bill_with_voluntary_tip_and_consumption_tax_inc(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0106',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 45000,
            'total' => 45000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        // Base = $45.000. Propina 10% = $4.500. INC 8% = $3.600. Total = $53.100.
        $payload = [
            'items' => [$item1->id],
            'include_tip' => true,
            'include_tax' => true,
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 53100,
                    'cash_received' => 60000,
                ]
            ],
        ];

        $response = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $order), $payload);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(45000, (float) $sale->subtotal);
        $this->assertEquals(4500, (float) $sale->service_fee);
        $this->assertEquals(3600, (float) $sale->tax_inc);
        $this->assertEquals(53100, (float) $sale->total);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 53100,
            'cash_received' => 60000,
            'change_given' => 6900,
        ]);
    }

    /**
     * Test 7: División de cuenta con pago combinado/mixto (Efectivo + Transferencia/Nequi).
     */
    public function test_split_bill_with_mixed_payments(): void
    {
        $order = RestaurantOrder::create([
            'business_id' => $this->businessA->id,
            'table_id' => $this->tableA->id,
            'user_id' => $this->employeeA->id,
            'order_number' => 'ORD-0107',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 53000,
            'total' => 53000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productPizza->id,
            'quantity' => 1,
            'unit_price' => 45000,
            'subtotal' => 45000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->businessA->id,
            'order_id' => $order->id,
            'product_id' => $this->productBeer->id,
            'quantity' => 1,
            'unit_price' => 8000,
            'subtotal' => 8000,
            'payment_status' => 'pending',
            'status' => 'served',
        ]);

        // Total = $53.000: 50% efectivo ($26.500) y 50% transferencia/nequi ($26.500)
        $payload = [
            'items' => [$item1->id, $item2->id],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 26500,
                    'cash_received' => 30000,
                ],
                [
                    'method' => 'nequi',
                    'amount' => 26500,
                    'reference' => 'NEQUI-M445566',
                ],
            ],
        ];

        $response = $this->actingAs($this->employeeA)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('restaurant.orders.split-payment', $order), $payload);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        $sale = Sale::where('restaurant_order_id', $order->id)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(53000, (float) $sale->total);
        $this->assertEquals('mixed', $sale->payment_method);
        $this->assertCount(2, $sale->payments);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 26500,
            'cash_received' => 30000,
            'change_given' => 3500,
        ]);

        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'method' => 'transfer',
            'amount' => 26500,
            'reference' => 'NEQUI-M445566',
        ]);
    }
}

