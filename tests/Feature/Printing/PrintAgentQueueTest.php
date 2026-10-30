<?php

namespace Tests\Feature\Printing;

use App\Models\Category;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\Sales\SaleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PrintAgentQueueTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $mesero;
    protected string $validToken = 'test_secret_agent_token_12345';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.print.enabled', true);
        Config::set('services.print.token', $this->validToken);

        $adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Dueño']
        );

        $employeeRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Mesero', 'description' => 'Operativo']
        );

        $this->admin = User::create([
            'role_id' => $adminRole->id,
            'name' => 'Admin Test',
            'email' => 'admin_test_print@saborcalifit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->mesero = User::create([
            'role_id' => $employeeRole->id,
            'name' => 'Mesero Test',
            'email' => 'mesero_test_print@saborcalifit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Seguridad: Endpoints de agente rechazan peticiones sin token o con token inválido con 401.
     */
    public function test_print_agent_endpoints_require_valid_token(): void
    {
        $job = PrintJob::create([
            'tipo'          => PrintJob::TIPO_COMANDA,
            'contenido'     => base64_encode('ESC/POS test data'),
            'estado'        => PrintJob::ESTADO_PENDIENTE,
        ]);

        // Sin token -> 401
        $this->postJson('/api/print/siguiente')->assertStatus(401);
        $this->postJson("/api/print/listo/{$job->id}")->assertStatus(401);
        $this->postJson("/api/print/error/{$job->id}")->assertStatus(401);

        // Token inválido -> 401
        $badHeaders = ['X-Print-Token' => 'token_invalido_hacker'];
        $this->postJson('/api/print/siguiente', [], $badHeaders)->assertStatus(401);
        $this->postJson("/api/print/listo/{$job->id}", [], $badHeaders)->assertStatus(401);
        $this->postJson("/api/print/error/{$job->id}", [], $badHeaders)->assertStatus(401);

        // Con token válido -> 200
        $goodHeaders = ['X-Print-Token' => $this->validToken];
        $this->postJson('/api/print/siguiente', [], $goodHeaders)->assertStatus(200);
    }

    /**
     * 2. Orden Estricto FIFO y Exclusión Mutua (lockForUpdate).
     */
    public function test_print_queue_delivers_jobs_in_fifo_order_and_handles_empty_queue(): void
    {
        $job1 = PrintJob::create([
            'tipo'      => PrintJob::TIPO_COMANDA,
            'contenido' => base64_encode('JOB 1 COMANDA'),
            'estado'    => PrintJob::ESTADO_PENDIENTE,
        ]);

        $job2 = PrintJob::create([
            'tipo'      => PrintJob::TIPO_FACTURA,
            'contenido' => base64_encode('JOB 2 FACTURA'),
            'estado'    => PrintJob::ESTADO_PENDIENTE,
        ]);

        $job3 = PrintJob::create([
            'tipo'      => PrintJob::TIPO_DESPACHO,
            'contenido' => base64_encode('JOB 3 DESPACHO'),
            'estado'    => PrintJob::ESTADO_PENDIENTE,
        ]);

        $headers = ['X-Print-Token' => $this->validToken];

        // 1ra llamada: debe entregar Job 1
        $resp1 = $this->postJson('/api/print/siguiente', [], $headers);
        $resp1->assertStatus(200);
        $this->assertEquals($job1->id, $resp1->json('id'));
        $this->assertEquals(PrintJob::TIPO_COMANDA, $resp1->json('tipo'));
        $this->assertEquals(PrintJob::ESTADO_IMPRIMIENDO, $job1->fresh()->estado);
        $this->assertEquals(1, $job1->fresh()->intentos);

        // 2da llamada: debe entregar Job 2 (Job 1 está imprimiendo y no se repite)
        $resp2 = $this->postJson('/api/print/siguiente', [], $headers);
        $resp2->assertStatus(200);
        $this->assertEquals($job2->id, $resp2->json('id'));
        $this->assertEquals(PrintJob::TIPO_FACTURA, $resp2->json('tipo'));

        // 3ra llamada: debe entregar Job 3
        $resp3 = $this->postJson('/api/print/siguiente', [], $headers);
        $resp3->assertStatus(200);
        $this->assertEquals($job3->id, $resp3->json('id'));

        // 4ta llamada: no hay pendientes -> retorna status 200 con vacío o null
        $respEmpty = $this->postJson('/api/print/siguiente', [], $headers);
        $respEmpty->assertStatus(200);
        $this->assertEmpty($respEmpty->json());
    }

    /**
     * 3. Recuperación Automática de Trabajos Colgados (> 60 s).
     */
    public function test_hung_jobs_are_automatically_recovered_and_redelivered(): void
    {
        $headers = ['X-Print-Token' => $this->validToken];

        $hungJob = PrintJob::create([
            'tipo'       => PrintJob::TIPO_COMANDA,
            'contenido'  => base64_encode('JOB COLGADO'),
            'estado'     => PrintJob::ESTADO_IMPRIMIENDO,
            'intentos'   => 1,
            'tomado_at'  => Carbon::now()->subSeconds(75),
        ]);

        // Al consultar siguiente, debe recuperar el trabajo colgado y entregarlo
        $resp = $this->postJson('/api/print/siguiente', [], $headers);
        $resp->assertStatus(200);
        $this->assertEquals($hungJob->id, $resp->json('id'));

        $fresh = $hungJob->fresh();
        $this->assertEquals(PrintJob::ESTADO_IMPRIMIENDO, $fresh->estado);
        $this->assertEquals(2, $fresh->intentos); // segundo intento
    }

    /**
     * 4. Ciclo de Éxito y Límite de 5 Reintentos.
     */
    public function test_completion_and_error_retry_cycle(): void
    {
        $headers = ['X-Print-Token' => $this->validToken];

        $job = PrintJob::create([
            'tipo'       => PrintJob::TIPO_FACTURA,
            'contenido'  => base64_encode('JOB PRUEBA'),
            'estado'     => PrintJob::ESTADO_IMPRIMIENDO,
            'intentos'   => 1,
        ]);

        // Reportar error cuando intentos < 5: debe regresar a pendiente
        $this->postJson("/api/print/error/{$job->id}", [], $headers)->assertOk();
        $this->assertEquals(PrintJob::ESTADO_PENDIENTE, $job->fresh()->estado);

        // Simular 5 intentos y reportar error: debe marcar estado error definitivo
        $job->update(['intentos' => 5, 'estado' => PrintJob::ESTADO_IMPRIMIENDO]);
        $this->postJson("/api/print/error/{$job->id}", [], $headers)->assertOk();
        $this->assertEquals(PrintJob::ESTADO_ERROR, $job->fresh()->estado);

        // Reportar listo: debe marcar impreso con timestamp
        $job->update(['estado' => PrintJob::ESTADO_IMPRIMIENDO]);
        $this->postJson("/api/print/listo/{$job->id}", [], $headers)->assertOk();
        $fresh = $job->fresh();
        $this->assertEquals(PrintJob::ESTADO_IMPRESO, $fresh->estado);
        $this->assertNotNull($fresh->impreso_at);
    }

    /**
     * 5. Encolado Automático en Operaciones Reales:
     * Al registrar venta en POS, se encola automáticamente un PrintJob de factura.
     */
    public function test_sale_processing_automatically_enqueues_escpos_print_job(): void
    {
        $category = Category::create([
            'name' => 'Suplementos',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'SUP-WHEY-001',
            'category_id' => $category->id,
            'nombre' => 'Proteina Whey 2lb',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'precio_venta' => 120000,
            'precio_compra' => 85000,
            'stock' => 10,
            'stock_minimo' => 2,
            'activo' => true,
        ]);

        $saleData = [
            'customer_name' => 'Juan Perez',
            'customer_document' => '1144123456',
            'customer_phone' => '3151234567',
            'order_type' => 'retail',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'additions' => [],
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => 120000,
                    'cash_received' => 120000,
                    'change_given' => 0,
                ],
            ],
        ];

        /** @var SaleService $saleService */
        $saleService = app(SaleService::class);
        $sale = $saleService->processSale($saleData, $this->admin);

        // Verificar que se haya encolado el PrintJob de factura
        $printJob = PrintJob::where('tipo', PrintJob::TIPO_FACTURA)
            ->where('referencia_id', $sale->id)
            ->first();

        $this->assertNotNull($printJob);
        $this->assertEquals(PrintJob::ESTADO_PENDIENTE, $printJob->estado);

        // Decodificar Base64 y verificar que contenga datos de la venta
        $decoded = base64_decode($printJob->contenido);
        $this->assertStringContainsString($sale->invoice_number, $decoded);
        $this->assertStringContainsString('SABORCALIFIT', $decoded);
    }

    /**
     * 6. Encolado Automático al Registrar Pedido de Despacho (Domicilio / Llevar).
     */
    public function test_dispatch_order_creation_automatically_enqueues_kitchen_print_job(): void
    {
        $this->actingAs($this->admin);

        $category = Category::create([
            'name' => 'Almuerzos Fit',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'PLATO-ALM-001',
            'category_id' => $category->id,
            'nombre' => 'Almuerzo Proteico Fit',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'precio_venta' => 25000,
            'precio_compra' => 15000,
            'stock' => 20,
            'stock_minimo' => 1,
            'activo' => true,
        ]);

        $payload = [
            'order_type' => 'delivery',
            'customer_name' => 'Carlos Domicilio',
            'phone' => '3125559988',
            'address' => 'Cl. 48 #106-86 Torre 3 Apto 402',
            'delivery_fee' => 5000,
            'notes' => 'Sin ensalada',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'notes' => 'Poco picante',
                ],
            ],
        ];

        $response = $this->postJson('/restaurant/dispatch', $payload);
        $response->assertStatus(201);

        $orderId = $response->json('order_id');

        // Debe haberse encolado automáticamente una comanda para cocina
        $printJob = PrintJob::where('tipo', PrintJob::TIPO_COMANDA)
            ->where('referencia_id', $orderId)
            ->first();

        $this->assertNotNull($printJob);
        $this->assertEquals(PrintJob::ESTADO_PENDIENTE, $printJob->estado);

        $decoded = base64_decode($printJob->contenido);
        $this->assertStringContainsString('COMANDA COCINA', $decoded);
        $this->assertStringContainsString('Almuerzo Proteico Fit', $decoded);
    }

    /**
     * 7. Comando Artisan print:requeue reactiva trabajos para soporte.
     */
    public function test_artisan_command_requeues_job_successfully(): void
    {
        $job = PrintJob::create([
            'tipo'       => PrintJob::TIPO_FACTURA,
            'contenido'  => base64_encode('TEST REQUEUE'),
            'estado'     => PrintJob::ESTADO_ERROR,
            'intentos'   => 5,
            'tomado_at'  => Carbon::now()->subMinutes(10),
            'impreso_at' => null,
        ]);

        $this->artisan("print:requeue {$job->id}")
            ->expectsOutputToContain("reencolado exitosamente como 'pendiente'")
            ->assertSuccessful();

        $fresh = $job->fresh();
        $this->assertEquals(PrintJob::ESTADO_PENDIENTE, $fresh->estado);
        $this->assertEquals(0, $fresh->intentos);
        $this->assertNull($fresh->tomado_at);
    }

    /**
     * 8. Endpoint Web POST /print-jobs/enqueue para el frontend.
     */
    public function test_authenticated_user_can_enqueue_print_job_via_web_endpoint(): void
    {
        // Invitado no puede -> redirige a login
        $this->postJson('/print-jobs/enqueue', ['tipo' => 'cuadre'])->assertStatus(401);

        $this->actingAs($this->admin);

        $response = $this->postJson('/print-jobs/enqueue', [
            'tipo' => 'cuadre',
            'date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);

        $jobId = $response->json('job_id');
        $this->assertNotNull($jobId);

        $job = PrintJob::find($jobId);
        $this->assertNotNull($job);
        $this->assertEquals(PrintJob::TIPO_CUADRE, $job->tipo);
        $this->assertEquals(PrintJob::ESTADO_PENDIENTE, $job->estado);
    }
}
