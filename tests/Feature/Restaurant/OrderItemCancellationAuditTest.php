<?php

namespace Tests\Feature\Restaurant;

use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderItemCancellationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected RestaurantTable $table;
    protected Product $dish1;
    protected Product $dish2;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador']);
        $employeeRole = Role::firstOrCreate(['slug' => 'employee'], ['name' => 'Empleado']);

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'role_id' => $employeeRole->id,
            'name' => 'Mesero CaliFit',
            'email' => 'mesero@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'name' => 'Mesa 5',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Alimentación Saludable',
            'is_active' => true,
        ]);

        $this->dish1 = Product::create([
            'nombre' => 'Bowl Proteico CaliFit',
            'sku' => 'BOWL-01',
            'tipo_producto' => 'PLATO',
            'precio_venta' => 25000,
            'precio_compra' => 12000,
            'stock' => 0,
            'stock_minimo' => 0,
            'unidad_base' => 'UNIDAD',
            'category_id' => $category->id,
            'activo' => true,
        ]);

        $this->dish2 = Product::create([
            'nombre' => 'Waffle Avena y Whey',
            'sku' => 'WAFFLE-01',
            'tipo_producto' => 'PLATO',
            'precio_venta' => 18000,
            'precio_compra' => 8000,
            'stock' => 0,
            'stock_minimo' => 0,
            'unidad_base' => 'UNIDAD',
            'category_id' => $category->id,
            'activo' => true,
        ]);
    }

    public function test_cancelling_order_item_without_reason_or_too_short_is_rejected(): void
    {
        $this->actingAs($this->employee);

        $order = RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-000201',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 43000,
            'total' => 43000,
        ]);

        $item = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'pending',
            'is_paid' => false,
        ]);

        // 1. Sin motivo
        $response = $this->post(route('restaurant.orders.items.cancel', ['order' => $order, 'item' => $item]), []);
        $response->assertSessionHasErrors('cancellation_reason');
        $this->assertEquals('pending', $item->fresh()->status);
        $this->assertEquals(43000, (float) $order->fresh()->total);

        // 2. Con motivo menor a 5 caracteres
        $responseShort = $this->post(route('restaurant.orders.items.cancel', ['order' => $order, 'item' => $item]), [
            'cancellation_reason' => 'No',
        ]);
        $responseShort->assertSessionHasErrors('cancellation_reason');
        $this->assertEquals('pending', $item->fresh()->status);
        $this->assertEquals(43000, (float) $order->fresh()->total);
    }

    public function test_cancelling_order_item_with_valid_reason_updates_item_and_recalculates_order_totals(): void
    {
        $this->actingAs($this->employee);

        $order = RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->employee->id,
            'order_number' => 'ORD-000202',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 43000,
            'total' => 43000,
        ]);

        $item1 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'kitchen',
            'is_paid' => false,
            'batch_number' => 1,
        ]);

        $item2 = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->dish2->id,
            'quantity' => 1,
            'unit_price' => 18000,
            'subtotal' => 18000,
            'status' => 'kitchen',
            'is_paid' => false,
            'batch_number' => 1,
        ]);

        $reason = 'Cliente canceló este producto antes de prepararlo';
        $response = $this->post(route('restaurant.orders.items.cancel', ['order' => $order, 'item' => $item1]), [
            'cancellation_reason' => $reason,
        ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));

        $freshItem1 = $item1->fresh();
        $this->assertEquals('cancelled', $freshItem1->status);
        $this->assertEquals($reason, $freshItem1->cancellation_reason);
        $this->assertEquals($this->employee->id, $freshItem1->cancelled_by);
        $this->assertNotNull($freshItem1->cancelled_at);

        // La orden debe haber recalculado el subtotal y total restando exactamente el item 1 ($25.000)
        $freshOrder = $order->fresh();
        $this->assertEquals(18000, (float) $freshOrder->subtotal);
        $this->assertEquals(18000, (float) $freshOrder->total);

        // El ítem 2 debe permanecer intacto
        $this->assertEquals('kitchen', $item2->fresh()->status);

        // Comprobar que en show.blade.php se muestra el ítem cancelado con su motivo y badge
        $showResponse = $this->get(route('restaurant.orders.show', $order));
        $showResponse->assertStatus(200)
            ->assertSee('Eliminado / Anulado')
            ->assertSee($reason)
            ->assertSee('Mesero CaliFit');
    }

    public function test_cannot_cancel_already_paid_order_item(): void
    {
        $this->actingAs($this->admin);

        $order = RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000203',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 1,
            'subtotal' => 25000,
            'total' => 25000,
        ]);

        $item = RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->dish1->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'served',
            'is_paid' => true, // Ya pagado
        ]);

        $response = $this->post(route('restaurant.orders.items.cancel', ['order' => $order, 'item' => $item]), [
            'cancellation_reason' => 'Error al cobrar',
        ]);

        $this->assertTrue($item->fresh()->is_paid);
        $this->assertNotEquals('cancelled', $item->fresh()->status);
    }
}
