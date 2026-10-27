<?php

namespace Tests\Feature\Restaurant;

use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected RestaurantTable $table;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'slug' => Role::ROLE_ADMIN,
            'name' => 'Administrador',
        ]);

        $roleEmployee = Role::create([
            'slug' => Role::ROLE_EMPLOYEE,
            'name' => 'Empleado POS',
        ]);

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Mesero CaliFit',
            'email' => 'mesero@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->table = RestaurantTable::create([
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'available',
        ]);

        $this->product = Product::create([
            'nombre' => 'Bowl Proteico',
            'sku' => 'BWL-01',
            'tipo_producto' => 'PLATO',
            'precio_venta' => 25000,
            'precio_compra' => 12000,
            'stock' => 10,
            'stock_minimo' => 2,
            'unidad_base' => 'UNIDAD',
            'activo' => true,
        ]);
    }

    public function test_cancelling_order_without_reason_or_too_short_is_rejected_with_422(): void
    {
        $this->actingAs($this->admin);

        $order = RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000101',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 0,
            'total' => 0,
        ]);
        $this->table->update(['status' => 'occupied']);

        // 1. Sin motivo
        $response1 = $this->postJson(route('restaurant.orders.cancel', $order), []);
        $response1->assertStatus(422)
            ->assertJsonValidationErrors(['cancellation_reason']);

        $this->assertEquals('open', $order->fresh()->status);
        $this->assertEquals('occupied', $this->table->fresh()->status);

        // 2. Con motivo menor a 5 caracteres
        $response2 = $this->postJson(route('restaurant.orders.cancel', $order), [
            'cancellation_reason' => 'abc',
        ]);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['cancellation_reason']);

        $this->assertEquals('open', $order->fresh()->status);
    }

    public function test_cancelling_order_with_valid_reason_persists_audit_and_releases_table(): void
    {
        $this->actingAs($this->admin);

        $order = RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000102',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 25000,
            'total' => 25000,
        ]);
        $this->table->update(['status' => 'occupied']);

        RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'status' => 'kitchen',
            'printed_to_kitchen' => true,
        ]);

        $reason = 'Cliente se retiró antes de preparar';
        $response = $this->post(route('restaurant.orders.cancel', $order), [
            'cancellation_reason' => $reason,
        ]);

        $response->assertRedirect(route('restaurant.tables.index'));

        $freshOrder = $order->fresh();
        $this->assertEquals('cancelled', $freshOrder->status);
        $this->assertEquals($reason, $freshOrder->cancellation_reason);
        $this->assertEquals($this->admin->id, $freshOrder->cancelled_by);
        $this->assertNotNull($freshOrder->cancelled_at);

        // La mesa física debe haber sido liberada
        $this->assertEquals('available', $this->table->fresh()->status);

        // Los ítems pendientes deben quedar en status cancelled
        $this->assertEquals('cancelled', $freshOrder->items()->first()->status);

        // Comprobar que en show.blade.php se renderiza el banner de auditoría
        $showResponse = $this->get(route('restaurant.orders.show', $order));
        $showResponse->assertStatus(200)
            ->assertSee('Comanda Cancelada (Registro de Auditoría)')
            ->assertSee($reason)
            ->assertSee('Admin CaliFit');
    }

    public function test_both_admin_and_employee_can_access_orders_index_with_tabs(): void
    {
        // 1. Acceso con Admin
        $this->actingAs($this->admin);
        $resAdmin = $this->get(route('restaurant.orders.index'));
        $resAdmin->assertStatus(200)
            ->assertSee('Comandas')
            ->assertSee('Comandas Hoy')
            ->assertSee('Historial Anterior')
            ->assertSee('Mapa de Mesas')
            ->assertDontSee('Abrir Nueva Comanda');

        // 2. Acceso con Employee
        $this->actingAs($this->employee);
        $resEmp = $this->get(route('restaurant.orders.index'));
        $resEmp->assertStatus(200)
            ->assertSee('Comandas')
            ->assertSee('Comandas Hoy')
            ->assertSee('Historial Anterior')
            ->assertDontSee('Abrir Nueva Comanda');
    }

    public function test_orders_index_with_history_tab_renders_history_active_and_no_english_pagination_showing(): void
    {
        $this->actingAs($this->admin);

        // Crear una comanda anterior
        RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000099',
            'order_type' => 'table',
            'status' => 'closed',
            'guest_count' => 1,
            'subtotal' => 30000,
            'total' => 30000,
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->get(route('restaurant.orders.index', ['tab' => 'history']));
        $response->assertStatus(200)
            ->assertSee('Historial Anterior')
            ->assertDontSee('Abrir Nueva Comanda')
            ->assertDontSee('Showing');
    }

    public function test_orders_index_displays_cancelled_orders_with_audit_reason(): void
    {
        $this->actingAs($this->admin);

        $order = RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000103',
            'order_type' => 'table',
            'status' => 'cancelled',
            'guest_count' => 1,
            'subtotal' => 0,
            'total' => 0,
            'cancellation_reason' => 'Error de digitación en apertura de mesa',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        $response = $this->get(route('restaurant.orders.index'));
        $response->assertStatus(200)
            ->assertSee('ORD-000103')
            ->assertSee('Anulada')
            ->assertSee('Cancelada (Auditoría)')
            ->assertSee('Error de digitación en apertura de mesa');
    }

    public function test_orders_index_renders_custom_status_badges_with_califit_colors(): void
    {
        $this->actingAs($this->admin);

        // 1. En Cocina (Open)
        RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000201',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 1,
            'subtotal' => 20000,
            'total' => 20000,
        ]);

        // 2. En Cobro (Billed)
        RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000202',
            'order_type' => 'table',
            'status' => 'billed',
            'guest_count' => 2,
            'subtotal' => 35000,
            'total' => 35000,
        ]);

        // 3. Cerrada / Pagada (Closed)
        RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000203',
            'order_type' => 'table',
            'status' => 'closed',
            'guest_count' => 1,
            'subtotal' => 15000,
            'total' => 15000,
        ]);

        // 4. Anulada (Cancelled)
        RestaurantOrder::create([
            'table_id' => $this->table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-000204',
            'order_type' => 'table',
            'status' => 'cancelled',
            'guest_count' => 1,
            'subtotal' => 0,
            'total' => 0,
            'cancellation_reason' => 'Cliente canceló el pedido',
            'cancelled_by' => $this->admin->id,
            'cancelled_at' => now(),
        ]);

        $response = $this->get(route('restaurant.orders.index'));
        $response->assertStatus(200);

        // Validar badges amarillos
        $response->assertSee('En Cocina');
        $response->assertSee('En Cobro / Pre-cuenta emitida');
        $response->assertSee('#F59E0B');

        // Validar badge verde
        $response->assertSee('Cerrada / Pagada');
        $response->assertSee('#059669');

        // Validar badge rojo
        $response->assertSee('Anulada');
        $response->assertSee('#DC2626');
    }

    public function test_empty_state_is_displayed_when_no_orders_exist_today(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('restaurant.orders.index'));
        $response->assertStatus(200)
            ->assertSee('No hay comandas registradas hoy')
            ->assertSee('Ir al Salón')
            ->assertSee('Nuevo Domicilio');
    }
}
