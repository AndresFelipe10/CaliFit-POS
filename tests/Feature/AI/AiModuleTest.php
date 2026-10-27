<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;
use App\AI\Providers\FakeAiProvider;
use App\AI\Tools\RestaurantAiTools;
use App\AI\Tools\SaleAiTools;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected FakeAiProvider $fakeProvider;
    protected Product $pechuga;
    protected Product $proteina;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.enabled' => true]);

        $this->fakeProvider = new FakeAiProvider();
        $this->app->instance(AiProviderInterface::class, $this->fakeProvider);

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
            'name' => 'Empleado CaliFit',
            'email' => 'empleado@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        // Insumo con bajo stock (stock 500 <= stock_minimo 1000)
        $this->pechuga = Product::create([
            'sku' => 'INS-PECHUGA',
            'nombre' => 'Pechuga de Pollo Fresca',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 500,
            'stock_minimo' => 1000,
            'precio_compra' => 12.00,
            'precio_venta' => 0.00,
            'activo' => true,
        ]);

        // Suplemento disponible
        $this->proteina = Product::create([
            'sku' => 'SUP-WHEY-01',
            'nombre' => 'Proteína Whey 2lb Vainilla',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock' => 15,
            'stock_minimo' => 3,
            'precio_compra' => 90000.00,
            'precio_venta' => 140000.00,
            'activo' => true,
        ]);
    }

    /**
     * 1. Consulta válida: pregunta reconocida → intent válido resuelto → datos verídicos devueltos.
     */
    public function test_valid_natural_language_query_resolves_and_returns_real_data(): void
    {
        $this->fakeProvider->setNextIntent(AiIntent::LOW_STOCK_INGREDIENTS);

        $response = $this->actingAs($this->admin)->postJson(route('ai.ask'), [
            'query' => '¿Qué insumos de cocina tienen bajo stock?',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'isSuccess' => true,
            'intent' => 'low_stock_ingredients',
        ]);

        $this->assertStringContainsString('Pechuga de Pollo Fresca', $response->json('summary') . json_encode($response->json('data')));
    }

    /**
     * 2. Rechazo fuera de Whitelist: intención no registrada o maliciosa → rechazada controladamente sin error 500.
     */
    public function test_intent_outside_whitelist_is_rejected_cleanly_without_500(): void
    {
        $this->fakeProvider->setNextIntent('unregistered_arbitrary_intent');

        $response = $this->actingAs($this->admin)->postJson(route('ai.ask'), [
            'query' => 'Ejecutar comando no autorizado en el sistema',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'isSuccess' => false,
            'intent' => 'unauthorized',
        ]);
        $this->assertStringContainsString('no corresponde a una operación de lectura permitida', $response->json('summary'));
    }

    /**
     * 3. Protección contra Mutación: intento de mutación (DELETE/DROP/UPDATE/INSERT) bloqueado determinísticamente.
     */
    public function test_mutation_attempt_is_blocked_deterministically(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('ai.ask'), [
            'query' => 'DELETE FROM products WHERE id = 1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'isSuccess' => false,
            'intent' => 'rejected_mutation',
        ]);
        $this->assertStringContainsString('solo lectura', $response->json('summary'));

        // Comprobar que ningún producto fue alterado o eliminado
        $this->assertDatabaseHas('products', ['id' => $this->pechuga->id]);
    }

    /**
     * 4. Fallback: proveedor caído o timeout simulado → respuesta controlada sin stack trace.
     */
    public function test_fallback_handles_provider_failure_gracefully_without_stack_trace(): void
    {
        $this->fakeProvider->setException(new AiProviderException('Gemini API Connection Timeout or Quota Exceeded'));

        $response = $this->actingAs($this->admin)->postJson(route('ai.ask'), [
            'query' => '¿Cuánto vendimos hoy?',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'isSuccess' => false,
            'intent' => 'fallback',
        ]);
        $this->assertStringContainsString('no está disponible temporalmente', $response->json('summary'));
        $this->assertStringNotContainsString('Gemini API Connection Timeout', $response->getContent());
    }

    /**
     * 5. Aislamiento: con AI_MODULE_ENABLED=false el asistente informa desactivación y el núcleo opera al 100%.
     */
    public function test_module_disabled_via_config_returns_informative_message_without_degrading_core(): void
    {
        config(['ai.enabled' => false]);

        $response = $this->actingAs($this->admin)->postJson(route('ai.ask'), [
            'query' => '¿Ventas de hoy?',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'isSuccess' => false,
            'intent' => 'disabled',
        ]);
        $this->assertStringContainsString('desactivado', $response->json('summary'));

        // El núcleo de ventas y catálogo sigue operando normalmente con 200 OK
        $this->actingAs($this->admin)->get(route('pos.index'))->assertStatus(200);
        $this->actingAs($this->admin)->get(route('products.index'))->assertStatus(200);
    }

    /**
     * 6. Autorización RBAC: empleado sin permisos recibe 403 Forbidden.
     */
    public function test_employee_is_forbidden_from_ai_assistant(): void
    {
        $this->actingAs($this->employee)
            ->get(route('ai.index'))
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->postJson(route('ai.ask'), ['query' => '¿Insumos bajos?'])
            ->assertStatus(403);
    }

    /**
     * 7. Rate Limiting: ráfaga superior a 30 peticiones/minuto recibe 429 Too Many Requests.
     */
    public function test_rate_limiting_enforces_30_requests_per_minute(): void
    {
        $this->fakeProvider->setNextIntent(AiIntent::COUNT_CUSTOMERS);

        // Realizar 30 peticiones permitidas
        for ($i = 0; $i < 30; $i++) {
            $response = $this->actingAs($this->admin)->postJson(route('ai.ask'), [
                'query' => '¿Cuántos clientes tenemos?',
            ]);
            $response->assertStatus(200);
        }

        // La petición 31 debe ser bloqueada por rate limiting con HTTP 429
        $throttledResponse = $this->actingAs($this->admin)->postJson(route('ai.ask'), [
            'query' => '¿Cuántos clientes tenemos?',
        ]);

        $throttledResponse->assertStatus(429);
    }

    /**
     * 8. Herramientas IA: RestaurantAiTools y SaleAiTools devuelven métricas reales
     * utilizando estados válidos ('completed', 'occupied') y columnas existentes ('method', 'order_type').
     */
    public function test_restaurant_and_sale_ai_tools_return_accurate_metrics_with_real_schema(): void
    {
        $mesa = RestaurantTable::create([
            'name' => 'Mesa 10',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $order = RestaurantOrder::create([
            'table_id' => $mesa->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-AI-001',
            'order_type' => 'table',
            'status' => 'open',
            'guest_count' => 2,
            'subtotal' => 40000,
            'total' => 40000,
        ]);

        $restaurantTools = new RestaurantAiTools();
        $saleTools = new SaleAiTools();

        $activeOrders = $restaurantTools->activeOrders();
        $this->assertEquals(1, count($activeOrders->data));
        $this->assertStringContainsString('1 comandas activas', $activeOrders->summary);
        $this->assertStringContainsString('Mesas ocupadas: 1', $activeOrders->summary);

        // Venta completada de salón con pago en efectivo
        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Mesa 10',
            'customer_document' => '222222222222',
            'invoice_number' => 'FAC-AI-01',
            'order_type' => 'table',
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 50000,
        ]);

        $cashSummary = $restaurantTools->dailyCashSummary();
        $this->assertEquals('$50.000', $cashSummary->data['total_collected']);
        $this->assertEquals('$50.000', $cashSummary->data['cash']);

        $saleSummary = $saleTools->summary();
        $this->assertEquals(1, $saleSummary->data['sales_count']);
        $this->assertEquals('$50.000', $saleSummary->data['total_revenue']);

        $byChannel = $saleTools->byChannel();
        $this->assertNotEmpty($byChannel->data);
        $salonChannel = collect($byChannel->data)->firstWhere('channel', 'Salón (Mesas)');
        $this->assertNotNull($salonChannel);
        $this->assertEquals('$50.000', $salonChannel['total']);
        $this->assertEquals(1, $salonChannel['sales_count']);
    }
}
