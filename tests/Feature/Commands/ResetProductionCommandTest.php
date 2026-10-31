<?php

namespace Tests\Feature\Commands;

use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetProductionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_production_cleans_operational_data_and_preserves_users(): void
    {
        $role = Role::create(['slug' => 'admin', 'name' => 'Administrador']);
        $user = User::create([
            'role_id' => $role->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $finalConsumer = Customer::create([
            'name' => 'Consumidor Final',
            'document' => '222222222222',
            'phone' => '3000000000',
        ]);

        $otherCustomer = Customer::create([
            'name' => 'Cliente Prueba',
            'document' => '1144000111',
            'phone' => '3123456789',
        ]);

        $table = RestaurantTable::create([
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'occupied',
        ]);

        $product = Product::create([
            'sku' => 'TEST-01',
            'nombre' => 'Producto Prueba',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'stock' => 10,
            'stock_minimo' => 2,
            'precio_compra' => 5000,
            'precio_venta' => 15000,
            'activo' => true,
        ]);

        $order = RestaurantOrder::create([
            'order_number' => 'ORD-TEST01',
            'user_id' => $user->id,
            'table_id' => $table->id,
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'customer_name' => 'Cliente Prueba',
            'customer_document' => '1144000111',
            'invoice_number' => 'FAC-TEST01',
            'subtotal' => 15000,
            'total' => 15000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Ejecutar comando con --force
        $this->artisan('pos:reset-production --force')
            ->assertSuccessful();

        // 1. Usuarios y roles preservados
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // 2. Consumidor Final preservado, otros clientes eliminados
        $this->assertDatabaseHas('customers', ['document' => '222222222222']);
        $this->assertDatabaseMissing('customers', ['document' => '1144000111']);

        // 3. Mesas restablecidas a available
        $this->assertEquals('available', $table->fresh()->status);

        // 4. Tablas operativas vaciadas
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('restaurant_orders', 0);
        $this->assertDatabaseCount('products', 0);
    }
}
