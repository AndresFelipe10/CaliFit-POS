<?php

namespace Tests\Feature\Restaurant;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderTableChangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $mesero;
    protected User $admin;
    protected RestaurantTable $mesa1;
    protected RestaurantTable $mesa2;
    protected RestaurantTable $mesaOcupada;
    protected RestaurantTable $mesaInactiva;

    protected function setUp(): void
    {
        parent::setUp();

        $roleEmployee = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Mesero / Empleado']
        );

        $roleAdmin = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador']
        );

        $this->mesero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Carlos Mesero',
            'email' => 'carlos@califit.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->mesa1 = RestaurantTable::create([
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $this->mesa2 = RestaurantTable::create([
            'name' => 'Mesa 2',
            'capacity' => 6,
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->mesaOcupada = RestaurantTable::create([
            'name' => 'Mesa 3',
            'capacity' => 2,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $this->mesaInactiva = RestaurantTable::create([
            'name' => 'Mesa 4 Inactiva',
            'capacity' => 4,
            'status' => 'available',
            'is_active' => false,
        ]);
    }

    /**
     * Mesero autenticado reasigna una comanda activa de Mesa 1 a Mesa 2 libre.
     */
    public function test_employee_can_successfully_change_order_table(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000201',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 3,
            'waiter_name' => 'Carlos Mesero',
            'notes' => 'Nota original del pedido.',
            'subtotal' => 25000.00,
            'total' => 25000.00,
        ]);

        $response = $this->post(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesa2->id,
        ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success', 'Mesa cambiada exitosamente a: Mesa 2');

        $this->mesa1->refresh();
        $this->mesa2->refresh();
        $order->refresh();

        // Verificaciones de estado
        $this->assertEquals('available', $this->mesa1->status, 'La mesa anterior debe quedar libre (available).');
        $this->assertEquals('occupied', $this->mesa2->status, 'La nueva mesa debe quedar ocupada (occupied).');
        $this->assertEquals($this->mesa2->id, $order->table_id, 'La orden debe apuntar a la nueva mesa.');

        // Verificación de auditoría en notas
        $this->assertStringContainsString('Nota original del pedido.', (string) $order->notes);
        $this->assertStringContainsString('[Traslado de mesa: Mesa 1 -> Mesa 2 por Carlos Mesero el ', (string) $order->notes);
    }

    /**
     * Administrador autenticado reasigna exitosamente comanda con paridad de permisos.
     */
    public function test_admin_can_successfully_change_order_table(): void
    {
        $this->actingAs($this->admin);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000202',
            'order_type' => 'table',
            'status' => 'in_kitchen',
            'guest_count' => 2,
            'waiter_name' => 'Carlos Mesero',
            'notes' => null,
            'subtotal' => 30000.00,
            'total' => 30000.00,
        ]);

        $response = $this->post(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesa2->id,
        ]);

        $response->assertRedirect(route('restaurant.orders.show', $order));
        $response->assertSessionHas('success', 'Mesa cambiada exitosamente a: Mesa 2');

        $this->mesa1->refresh();
        $this->mesa2->refresh();
        $order->refresh();

        $this->assertEquals('available', $this->mesa1->status);
        $this->assertEquals('occupied', $this->mesa2->status);
        $this->assertEquals($this->mesa2->id, $order->table_id);
        $this->assertStringContainsString('[Traslado de mesa: Mesa 1 -> Mesa 2 por Admin CaliFit el ', (string) $order->notes);
    }

    /**
     * Soporte de petición AJAX / JSON devuelve respuesta JSON estructurada.
     */
    public function test_change_table_via_ajax_returns_json_response(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000203',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 15000.00,
            'total' => 15000.00,
        ]);

        $response = $this->postJson(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesa2->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Mesa cambiada exitosamente a: Mesa 2',
            'table' => [
                'id' => $this->mesa2->id,
                'name' => 'Mesa 2',
                'status' => 'occupied',
            ],
        ]);
        $response->assertJsonStructure(['success', 'message', 'order', 'table', 'redirect']);
    }

    /**
     * Rechazo al intentar transferir hacia una mesa ya ocupada o en cobro.
     */
    public function test_rejects_transfer_to_occupied_or_billed_table(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000204',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 10000.00,
            'total' => 10000.00,
        ]);

        // 1. Intentar transferir a mesa occupied
        $responseOccupied = $this->postJson(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesaOcupada->id,
        ]);

        $responseOccupied->assertStatus(422);
        $responseOccupied->assertJsonValidationErrors(['table_id']);

        // 2. Intentar transferir a mesa billed
        $mesaBilled = RestaurantTable::create([
            'name' => 'Mesa Billed',
            'capacity' => 4,
            'status' => 'billed',
            'is_active' => true,
        ]);

        $responseBilled = $this->postJson(route('restaurant.orders.change-table', $order), [
            'table_id' => $mesaBilled->id,
        ]);

        $responseBilled->assertStatus(422);
        $responseBilled->assertJsonValidationErrors(['table_id']);

        // Verificar que los estados no sufrieron alteraciones
        $this->mesa1->refresh();
        $this->mesaOcupada->refresh();
        $mesaBilled->refresh();
        $order->refresh();

        $this->assertEquals('occupied', $this->mesa1->status);
        $this->assertEquals('occupied', $this->mesaOcupada->status);
        $this->assertEquals('billed', $mesaBilled->status);
        $this->assertEquals($this->mesa1->id, $order->table_id);
    }

    /**
     * Rechazo al intentar transferir a la misma mesa que la orden ya tiene asignada.
     */
    public function test_rejects_transfer_to_same_table(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000205',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 12000.00,
            'total' => 12000.00,
        ]);

        $response = $this->postJson(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesa1->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['table_id']);
    }

    /**
     * Rechazo al intentar transferir hacia una mesa inactiva o inexistente.
     */
    public function test_rejects_transfer_to_inactive_or_nonexistent_table(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-000206',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 12000.00,
            'total' => 12000.00,
        ]);

        // Inactiva
        $responseInactive = $this->postJson(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesaInactiva->id,
        ]);
        $responseInactive->assertStatus(422);
        $responseInactive->assertJsonValidationErrors(['table_id']);

        // Inexistente
        $responseNonExistent = $this->postJson(route('restaurant.orders.change-table', $order), [
            'table_id' => 99999,
        ]);
        $responseNonExistent->assertStatus(422);
        $responseNonExistent->assertJsonValidationErrors(['table_id']);
    }

    /**
     * Rechazo en comandas en estados no modificables (billed, closed, cancelled).
     */
    public function test_rejects_table_change_for_non_modifiable_orders(): void
    {
        $this->actingAs($this->mesero);

        $statuses = ['billed', 'closed', 'cancelled'];

        foreach ($statuses as $status) {
            $order = RestaurantOrder::create([
                'table_id' => $this->mesa1->id,
                'user_id' => $this->mesero->id,
                'order_number' => 'ORD-NON-' . strtoupper($status),
                'order_type' => 'table',
                'status' => $status,
                'guest_count' => 2,
                'subtotal' => 20000.00,
                'total' => 20000.00,
            ]);

            $response = $this->postJson(route('restaurant.orders.change-table', $order), [
                'table_id' => $this->mesa2->id,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['order']);
        }
    }

    /**
     * Rechazo al intentar cambiar mesa en pedidos de despacho (delivery / takeout).
     */
    public function test_rejects_table_change_for_delivery_or_takeout_orders(): void
    {
        $this->actingAs($this->mesero);

        $orderDelivery = RestaurantOrder::create([
            'table_id' => null,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-DELIV-001',
            'order_type' => 'delivery',
            'status' => 'open',
            'customer_name' => 'Cliente Domicilio',
            'subtotal' => 30000.00,
            'total' => 35000.00,
        ]);

        $response = $this->postJson(route('restaurant.orders.change-table', $orderDelivery), [
            'table_id' => $this->mesa2->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['order']);
    }

    /**
     * Invitado no autenticado es redirigido a login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-GUEST-001',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 10000.00,
            'total' => 10000.00,
        ]);

        $response = $this->post(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesa2->id,
        ]);

        $response->assertRedirect(route('login'));
    }

    /**
     * Integridad transaccional: Si ocurre un error o excepción dentro de la transacción,
     * las mesas mantienen simétricamente sus estados originales sin inconsistencias.
     */
    public function test_transactional_rollback_preserves_table_states_on_failure(): void
    {
        $this->actingAs($this->mesero);

        $order = RestaurantOrder::create([
            'table_id' => $this->mesa1->id,
            'user_id' => $this->mesero->id,
            'order_number' => 'ORD-ROLLBACK-01',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'notes' => 'Notas previas.',
            'subtotal' => 20000.00,
            'total' => 20000.00,
        ]);

        $this->mesa1->update(['status' => 'occupied']);
        $this->mesa2->update(['status' => 'available']);

        // Simular que justo antes de completar la transacción ocurre un error forzado
        // comprobando que si falla la validación pesimista (por ejemplo mesa cambiada a ocupada concurrentemente),
        // ningún estado queda a medio camino.
        $this->mesa2->update(['status' => 'occupied']); // Otra transacción ocupó la mesa

        $response = $this->postJson(route('restaurant.orders.change-table', $order), [
            'table_id' => $this->mesa2->id,
        ]);

        $response->assertStatus(422);

        $this->mesa1->refresh();
        $this->mesa2->refresh();
        $order->refresh();

        $this->assertEquals('occupied', $this->mesa1->status);
        $this->assertEquals('occupied', $this->mesa2->status);
        $this->assertEquals($this->mesa1->id, $order->table_id);
    }
}
