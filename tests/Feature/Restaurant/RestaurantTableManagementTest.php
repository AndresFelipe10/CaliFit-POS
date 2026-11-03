<?php

namespace Tests\Feature\Restaurant;

use App\Exceptions\InsufficientInventoryException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTableManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $mesero;
    protected Category $categoria;
    protected Product $productoSuplemento;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador']
        );

        $roleEmployee = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Mesero / Empleado']
        );

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin_test@califit.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->mesero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Mesero CaliFit',
            'email' => 'mesero_test@califit.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->categoria = Category::create([
            'name' => 'Suplementos',
            'is_active' => true,
        ]);

        $this->productoSuplemento = Product::create([
            'nombre' => 'Proteina Whey 2lb',
            'sku' => 'SUP-TEST-WHEY',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->categoria->id,
            'precio_venta' => 120000.00,
            'precio_compra' => 80000.00,
            'stock' => 0, // Cero stock intencional para probar InsufficientInventoryException
            'stock_minimo' => 5,
            'activo' => true,
        ]);
    }

    public function test_admin_can_create_new_table_with_alphanumeric_name(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa 9 VIP',
            'capacity' => 6,
        ]);

        $response->assertRedirect(route('restaurant.tables.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('restaurant_tables', [
            'name' => 'Mesa 9 VIP',
            'capacity' => 6,
            'status' => 'available',
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_create_table_with_duplicate_name(): void
    {
        $this->actingAs($this->admin);

        RestaurantTable::create([
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa 1',
            'capacity' => 4,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_employee_cannot_create_table(): void
    {
        $this->actingAs($this->mesero);

        $response = $this->post(route('restaurant.tables.store'), [
            'name' => 'Mesa Infiltrada',
            'capacity' => 4,
        ]);

        $response->assertStatus(403);
    }

    public function test_order_settle_with_insufficient_inventory_returns_422_with_exact_message(): void
    {
        $this->actingAs($this->admin);

        $table = RestaurantTable::create([
            'name' => 'Mesa 5',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $order = RestaurantOrder::create([
            'order_number' => 'ORD-TEST-99',
            'order_type' => 'table',
            'table_id' => $table->id,
            'user_id' => $this->admin->id,
            'status' => 'open',
            'total' => 120000.00,
        ]);

        RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productoSuplemento->id,
            'quantity' => 1,
            'unit_price' => 120000.00,
            'subtotal' => 120000.00,
            'status' => 'served',
        ]);

        $customer = Customer::create([
            'name' => 'Cliente Test',
            'document' => '12345678',
            'phone' => '3001234567',
            'is_active' => true,
        ]);

        $settlePayload = [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'discount_percentage' => 0,
            'delivery_fee' => 0,
            'items' => [
                [
                    'product_id' => $this->productoSuplemento->id,
                    'quantity' => 1,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 120000.00,
                    'cash_received' => 120000.00,
                ],
            ],
        ];

        $response = $this->postJson(route('restaurant.orders.settle', $order), $settlePayload);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('Inventario insuficiente', $response->json('message'));
        $this->assertStringContainsString('Proteina Whey 2lb', $response->json('message'));
    }

    public function test_pos_store_with_insufficient_inventory_returns_422(): void
    {
        $this->actingAs($this->admin);

        $customer = Customer::create([
            'name' => 'Cliente POS',
            'document' => '98765432',
            'phone' => '3119876543',
            'is_active' => true,
        ]);

        $payload = [
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'items' => [
                [
                    'product_id' => $this->productoSuplemento->id,
                    'quantity' => 2,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 240000.00,
                    'cash_received' => 240000.00,
                ],
            ],
        ];

        $response = $this->postJson(route('pos.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('Inventario insuficiente', $response->json('message'));
    }
}
