<?php

namespace Tests\Feature\AI;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Providers\FakeAiProvider;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantContextAndSpanishTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected FakeAiProvider $fakeProvider;

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

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        // Crear clientes de prueba
        Customer::create([
            'name' => 'Carlos Andrés Pérez',
            'document' => '1144123456',
            'phone' => '3157894561',
            'address' => 'Ciudad Bochalema Manzana 4',
            'is_active' => true,
        ]);

        Customer::create([
            'name' => 'María Camila Gómez',
            'document' => '1112456789',
            'phone' => '3189998877',
            'address' => 'Arbolatta Mall Local 12',
            'is_active' => true,
        ]);
    }

    public function test_conversational_memory_preserves_context_when_asking_cuales_son_after_customer_count(): void
    {
        $this->actingAs($this->admin);

        // Turno 1: Preguntar cuántos clientes activos hay
        $response1 = $this->postJson(route('ai.ask'), [
            'query' => '¿Cuántos clientes activos tenemos registrados?',
        ]);

        $response1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'count_customers',
            ]);

        $json1 = $response1->json();
        $this->assertStringContainsString('clientes activos', $json1['summary']);
        // Las llaves deben estar en español
        $this->assertArrayHasKey('Clientes Activos Registrados', $json1['data']);

        // Turno 2: Preguntar "cuales son" inmediatamente después
        $response2 = $this->postJson(route('ai.ask'), [
            'query' => 'cuales son',
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'list_customers',
            ]);

        $json2 = $response2->json();
        $this->assertStringContainsString('clientes activos', $json2['summary']);
        $this->assertIsArray($json2['data']);
        $this->assertNotEmpty($json2['data']);

        // Verificar que devolvió los clientes y NO productos
        $firstItem = $json2['data'][0];
        $this->assertArrayHasKey('Nombre', $firstItem);
        $this->assertArrayHasKey('Documento', $firstItem);
        $this->assertArrayHasKey('Teléfono', $firstItem);
        $this->assertArrayHasKey('Dirección', $firstItem);
        $this->assertArrayHasKey('Compras Realizadas', $firstItem);
        $this->assertArrayHasKey('Saldo en Cartera', $firstItem);

        $this->assertEquals('Carlos Andrés Pérez', $firstItem['Nombre']);
    }

    public function test_ai_tools_return_spanish_keys_and_values_for_sales_summary(): void
    {
        $this->actingAs($this->admin);

        // Crear una venta completada hoy
        Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Mostrador',
            'customer_document' => '222222222222',
            'invoice_number' => 'FAC-AI-99',
            'sale_type' => 'retail',
            'order_type' => 'retail',
            'status' => 'completed',
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cash',
        ]);

        $response = $this->postJson(route('ai.ask'), [
            'query' => 'Resumen de ventas de hoy',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'sales_summary',
            ]);

        $data = $response->json('data');
        // Debe tener llaves en español
        $this->assertArrayHasKey('Período', $data);
        $this->assertArrayHasKey('Ventas / Órdenes', $data);
        $this->assertArrayHasKey('Ingresos Totales', $data);
        $this->assertArrayHasKey('Ticket Promedio', $data);
        // El valor de período debe ser 'Hoy'
        $this->assertEquals('Hoy', $data['Período']);
    }

    public function test_clear_session_route_forgets_conversation_history(): void
    {
        $this->actingAs($this->admin);

        // Establecer un turno
        $this->postJson(route('ai.ask'), [
            'query' => '¿Cuántos clientes activos tenemos registrados?',
        ]);

        $this->assertTrue(session()->has('ai_conversation_history'));
        $this->assertTrue(session()->has('ai_last_intent'));

        // Limpiar sesión
        $response = $this->postJson(route('ai.clear'));
        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertFalse(session()->has('ai_conversation_history'));
        $this->assertFalse(session()->has('ai_last_intent'));
    }

    public function test_ai_assistant_index_view_loads_successfully_with_dom_and_js_elements(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('ai.index'));
        $response->assertStatus(200)
            ->assertSee('Asistente de Consultas en Lenguaje Natural')
            ->assertSee('queryForm')
            ->assertSee('userInput')
            ->assertSee('sendBtn')
            ->assertSee('clearChatBtn')
            ->assertSee('suggestion-btn')
            ->assertSee('sendQuestion');
    }

    public function test_ai_ask_endpoint_accepts_question_and_message_aliases(): void
    {
        $this->actingAs($this->admin);

        // Alias 'question'
        $responseQuestion = $this->postJson(route('ai.ask'), [
            'question' => '¿Cuántos clientes activos tenemos registrados?',
        ]);
        $responseQuestion->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'count_customers',
            ]);

        // Alias 'message'
        $responseMessage = $this->postJson(route('ai.ask'), [
            'message' => '¿Cuántos clientes activos tenemos registrados?',
        ]);
        $responseMessage->assertStatus(200)
            ->assertJson([
                'success' => true,
                'intent' => 'count_customers',
            ]);
    }
}
