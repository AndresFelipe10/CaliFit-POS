<?php

namespace Tests\Feature\Restaurant;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use App\Services\Reports\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTipAccountingTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $restaurant;
    protected User $admin;
    protected User $waiter;
    protected RestaurantTable $table;
    protected Category $category;
    protected Product $dish1;
    protected Product $dish2;

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

        $this->restaurant = Business::create([
            'name' => 'Restaurante Piqueteadero Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Admin Restaurante']);
        $this->restaurant->users()->attach($this->admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->waiter = User::factory()->create(['name' => 'Pedrito Mesero']);
        $this->restaurant->users()->attach($this->waiter->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'occupied',
        ]);

        $this->category = Category::create([
            'business_id' => $this->restaurant->id,
            'name' => 'Asados',
        ]);

        $this->dish1 = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Pollo Asado 1',
            'sku' => 'ASADO-001',
            'sale_price' => 25000.00,
            'stock' => 20,
            'product_type' => 'dish',
            'is_active' => true,
        ]);

        $this->dish2 = Product::create([
            'business_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Pollo Asado 2',
            'sku' => 'ASADO-002',
            'sale_price' => 25000.00,
            'stock' => 20,
            'product_type' => 'dish',
            'is_active' => true,
        ]);
    }

    public function test_tip_accounting_in_split_bill_and_cash_register_reporting(): void
    {
        // 1. Crear orden con 2 platos de $25.000 ($50.000 total consumo)
        $order = RestaurantOrder::create([
            'business_id' => $this->restaurant->id,
            'user_id' => $this->waiter->id,
            'table_id' => $this->table->id,
            'order_number' => 'ORD-0033',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 50000.00,
            'total' => 50000.00,
        ]);

        $item1 = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 25000.00,
            'subtotal' => 25000.00,
            'status' => 'pending',
            'payment_status' => 'pending',
            'batch_number' => 1,
        ]);

        $item2 = RestaurantOrderItem::create([
            'business_id' => $this->restaurant->id,
            'order_id' => $order->id,
            'product_id' => $this->dish2->id,
            'quantity' => 1,
            'unit_price' => 25000.00,
            'subtotal' => 25000.00,
            'status' => 'pending',
            'payment_status' => 'pending',
            'batch_number' => 2,
        ]);

        // 2. Pagar el plato 1 con split bill incluyendo 10% de propina ($2.500)
        // Subtotal = $25.000, Propina = $2.500, Total a pagar = $27.500
        $splitPayment1Response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$item1->id],
                'include_tip' => true,
                'include_tax' => false,
                'discount_percentage' => 0,
                'payments' => [
                    [
                        'method' => 'card',
                        'amount' => 27500.00,
                    ],
                ],
            ]);

        $splitPayment1Response->assertRedirect();

        // Verificar estado tras primer pago parcial
        $orderAfterP1 = $order->fresh(['sales', 'items']);
        $this->assertEquals(2500.00, $orderAfterP1->totalTips());
        $this->assertEquals(27500.00, $orderAfterP1->totalCollected());
        $this->assertEquals(25000.00, $orderAfterP1->paidAmount());
        $this->assertEquals(25000.00, $orderAfterP1->pendingBalance());
        $this->assertEquals('open', $orderAfterP1->status);

        // 3. Pagar el plato 2 con split bill sin propina ($25.000 en efectivo)
        $splitPayment2Response = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->post(route('restaurant.orders.split-payment', $order), [
                'items' => [$item2->id],
                'include_tip' => false,
                'include_tax' => false,
                'discount_percentage' => 0,
                'payments' => [
                    [
                        'method' => 'cash',
                        'amount' => 25000.00,
                        'cash_received' => 25000.00,
                    ],
                ],
            ]);

        $splitPayment2Response->assertRedirect();

        // 4. Validar métodos helpers del modelo RestaurantOrder
        $orderFinal = $order->fresh(['sales', 'items']);
        $this->assertEquals('billed', $orderFinal->status);
        $this->assertEquals(2500.00, $orderFinal->totalTips());
        $this->assertEquals(52500.00, $orderFinal->totalCollected());
        $this->assertEquals(50000.00, $orderFinal->totalAmount());
        $this->assertEquals(50000.00, $orderFinal->paidAmount());
        $this->assertEquals(0.00, $orderFinal->pendingBalance());
        $this->assertEquals(2, $orderFinal->sales()->count());

        // 5. Validar que la vista de detalle de la comanda renderiza el desglose financiero
        $showResponse = $this->actingAs($this->waiter)
            ->withSession(['current_business_id' => $this->restaurant->id])
            ->get(route('restaurant.orders.show', $order));

        $showResponse->assertOk();
        $showResponse->assertSee('Subtotal Consumo:');
        $showResponse->assertSee('$50,000.00');
        $showResponse->assertSee('Propinas Pagadas:');
        $showResponse->assertSee('+$2,500.00');
        $showResponse->assertSee('Total Recaudado:');
        $showResponse->assertSee('$52,500.00');

        // 6. Verificar que en el reporte de cuadre de caja (ReportService) las propinas figuren por $2.500
        $reportService = app(ReportService::class);
        $todayStr = Carbon::today()->format('Y-m-d');
        $cashReport = $reportService->getCashRegisterReport($this->restaurant->id, [
            'period_type' => 'daily',
            'date' => $todayStr,
        ]);

        $this->assertEquals(2500.00, $cashReport['total_tips']);
        $this->assertEquals(2500.00, $cashReport['digital_tips']);
        $this->assertEquals(0.00, $cashReport['cash_tips']);
        $this->assertEquals(52500.00, $cashReport['total_revenue']);
    }
}
