<?php

namespace Tests\Feature\Security;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OwaspHardeningAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador']
        );

        $roleEmployee = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado POS']
        );

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin Audit',
            'email' => 'admin.audit@test.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->employee = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Empleado Audit',
            'email' => 'empleado.audit@test.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Mass Assignment: Comprueba que ningún modelo en app/Models/ tenga $guarded = []
     * y que todos declaren $fillable explícito.
     */
    public function test_all_eloquent_models_enforce_explicit_fillable_protection(): void
    {
        $modelsPath = app_path('Models');
        $files = File::files($modelsPath);

        $this->assertNotEmpty($files, 'Debe haber modelos Eloquent en app/Models');

        foreach ($files as $file) {
            $className = 'App\\Models\\' . $file->getFilenameWithoutExtension();

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            if ($reflection->isAbstract() || !$reflection->isSubclassOf(\Illuminate\Database\Eloquent\Model::class)) {
                continue;
            }

            /** @var \Illuminate\Database\Eloquent\Model $instance */
            $instance = new $className;

            $guarded = $instance->getGuarded();
            $fillable = $instance->getFillable();

            $this->assertNotEquals([], $guarded, "El modelo {$className} no debe tener \$guarded = [] vacío");
            $this->assertNotEmpty($fillable, "El modelo {$className} debe declarar un arreglo \$fillable explícito");
        }

        // Verificación explícita de modelos clave de crédito y comanda
        $creditPayment = new \App\Models\CustomerCreditPayment();
        $this->assertContains('amount', $creditPayment->getFillable());
        $this->assertContains('customer_id', $creditPayment->getFillable());
        $this->assertContains('sale_id', $creditPayment->getFillable());

        $split = new \App\Models\RestaurantOrderSplit();
        $this->assertContains('restaurant_order_id', $split->getFillable());
        $this->assertContains('amount', $split->getFillable());
        $this->assertContains('status', $split->getFillable());
    }

    /**
     * 2. Control de Acceso (RBAC):
     * Rutas administrativas retornan 403 Forbidden a usuarios con rol employee.
     */
    public function test_administrative_routes_strictly_return_403_to_employee(): void
    {
        $adminRoutes = [
            'products.index',
            'products.create',
            'recipes.index',
            'additions.index',
            'expenses.index',
            'expenses.create',
            'reports.index',
            'reports.sales',
            'reports.inventory',
            'reports.top-products',
            'ai.index',
            'users.index',
            'suppliers.index',
            'suppliers.create',
            'inventory.index',
            'inventory.create',
            'inventory.movements',
            'inventory.alerts',
        ];

        foreach ($adminRoutes as $routeName) {
            $response = $this->actingAs($this->employee)->get(route($routeName));
            $this->assertEquals(403, $response->getStatusCode(), "Ruta {$routeName} debió responder 403 al empleado");
        }
    }

    /**
     * 3. Protección DIAN:
     * El documento 222222222222 (Consumidor Final) está estrictamente prohibido al crear o actualizar clientes,
     * tanto en el campo 'document' como en su alias 'document_number'.
     */
    public function test_dian_final_consumer_document_is_strictly_forbidden_in_customer_endpoints(): void
    {
        // Intento de crear cliente con 222222222222 en 'document'
        $responseCreate = $this->actingAs($this->admin)->post(route('customers.store'), [
            'name' => 'Intento Consumidor Final',
            'document' => '222222222222',
            'email' => 'cf@test.com',
            'phone' => '3001234567',
        ]);

        $responseCreate->assertSessionHasErrors('document');
        $this->assertEquals(0, Customer::where('name', 'Intento Consumidor Final')->count());

        // Intento de crear cliente con 222222222222 en alias 'document_number'
        $responseCreateAlias = $this->actingAs($this->admin)->post(route('customers.store'), [
            'name' => 'Intento Consumidor Final Alias',
            'document_number' => '222222222222',
            'email' => 'cf2@test.com',
            'phone' => '3001234567',
        ]);

        $responseCreateAlias->assertSessionHasErrors('document_number');

        // Crear cliente legítimo primero
        $customer = Customer::create([
            'name' => 'Cliente Legítimo',
            'document' => '1098765432',
            'email' => 'legitimo@test.com',
            'phone' => '3001234567',
        ]);

        // Intento de actualizar cliente con documento 222222222222
        $responseUpdate = $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Cliente Modificado Ilegal',
            'document' => '222222222222',
            'email' => 'legitimo@test.com',
            'phone' => '3001234567',
        ]);

        $responseUpdate->assertSessionHasErrors('document');
        $this->assertEquals('1098765432', $customer->fresh()->document);

        // Intento de actualizar cliente con document_number 222222222222
        $responseUpdateAlias = $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'name' => 'Cliente Modificado Ilegal Alias',
            'document_number' => '222222222222',
            'email' => 'legitimo@test.com',
            'phone' => '3001234567',
        ]);

        $responseUpdateAlias->assertSessionHasErrors('document_number');
    }

    /**
     * 4. Almacenamiento Seguro de Adjuntos:
     * Descarga de archivos de gastos vía controlador protegido, fuera de carpeta pública y con header nosniff.
     */
    public function test_expense_attachment_download_is_protected_and_serves_nosniff(): void
    {
        Storage::fake('local');

        $fakeFile = UploadedFile::fake()->create('factura_auditoria.pdf', 500, 'application/pdf');
        $storedPath = $fakeFile->storeAs('private/expenses', 'factura_auditoria.pdf', 'local');

        $expense = Expense::create([
            'created_by' => $this->admin->id,
            'invoice_number' => 'FAC-AUDIT-999',
            'issue_date' => date('Y-m-d'),
            'category' => 'supplies',
            'description' => 'Compra de prueba auditoría',
            'amount' => 150000.0,
            'status' => 'pending',
            'attachment_path' => $storedPath,
        ]);

        // Empleado recibe 403 al intentar descargar adjunto de gasto
        $this->actingAs($this->employee)
            ->get(route('expenses.attachment', $expense))
            ->assertStatus(403);

        // Administrador puede descargar y recibe header X-Content-Type-Options: nosniff
        $responseAdmin = $this->actingAs($this->admin)
            ->get(route('expenses.attachment', $expense));

        $responseAdmin->assertOk();
        $responseAdmin->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * 5. Sanitización de Vistas Blade:
     * Verifica que no existan directivas de escape raw {!! $var !!} que puedan introducir XSS.
     */
    public function test_blade_templates_do_not_contain_unescaped_variable_output(): void
    {
        $viewsPath = resource_path('views');
        $bladeFiles = File::allFiles($viewsPath);

        $this->assertNotEmpty($bladeFiles, 'Debe haber archivos de vista Blade');

        foreach ($bladeFiles as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = File::get($file->getRealPath());

            // Buscar patrón {!! $... !!}
            $hasUnescapedBlade = preg_match('/\{!!\s*\$.*?!!\}/s', $content);

            $this->assertSame(
                0,
                $hasUnescapedBlade,
                "La vista {$file->getRelativePathname()} contiene directivas {!! \$... !!} no sanitizadas susceptibles a XSS"
            );
        }
    }

    /**
     * 6. Remoción de Módulo Superadmin:
     * Comprueba que la consola y endpoints de /superadmin retornen 404 Not Found.
     */
    public function test_superadmin_endpoints_strictly_return_404_not_found(): void
    {
        $this->get('/superadmin')->assertNotFound();
        $this->actingAs($this->employee)->get('/superadmin')->assertNotFound();
        $this->actingAs($this->admin)->get('/superadmin')->assertNotFound();
        $this->actingAs($this->admin)->get('/superadmin/users')->assertNotFound();
    }

    /**
     * 7. Rechazo de inyección XSS y caracteres inválidos en creación rápida de clientes.
     */
    public function test_quick_customer_creation_rejects_xss_and_invalid_characters(): void
    {
        // Petición AJAX / JSON desde modal rápido con payload XSS
        $responseXss = $this->actingAs($this->employee)->postJson(route('customers.store'), [
            'name' => '<script>alert("XSS")</script>',
            'document' => '1020304050',
            'phone' => '3001234567',
        ]);

        $responseXss->assertStatus(422);
        $responseXss->assertJsonValidationErrors('name');
        $this->assertEquals(0, Customer::where('document', '1020304050')->count());

        // Petición con caracteres no alfabéticos
        $responseInvalid = $this->actingAs($this->employee)->postJson(route('customers.store'), [
            'name' => "Robert'); DROP TABLE users;--",
            'document' => '1020304051',
            'phone' => '3001234567',
        ]);

        $responseInvalid->assertStatus(422);
        $responseInvalid->assertJsonValidationErrors('name');
    }

    /**
     * 8. Integridad financiera atómica: Rechazo de sobrepago en Cartera / Fiados.
     */
    public function test_atomic_integrity_against_double_charging_and_overpayment(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Deudor Audit',
            'document' => '1099887766',
            'phone' => '3009998877',
        ]);

        $sale = \App\Models\Sale::create([
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'order_type' => 'retail',
            'invoice_number' => 'FAC-CRED-AUDIT-01',
            'subtotal' => 50000,
            'total' => 50000,
            'is_credit' => true,
            'credit_status' => 'pending',
            'status' => 'completed',
        ]);

        $creditService = app(\App\Services\Credit\CustomerCreditService::class);

        // Intento de abonar $60.000 cuando el saldo pendiente es $50.000
        $this->expectException(\InvalidArgumentException::class);
        $creditService->registerPayment($sale, [
            'amount' => 60000,
            'method' => 'cash',
            'cash_received' => 60000,
        ], $this->admin);
    }

    /**
     * 9. Integridad atómica en Comandas: un plato ya marcado como pagado no puede volver a liquidarse.
     */
    public function test_order_item_cannot_be_settled_twice_atomic_rejection(): void
    {
        $table = \App\Models\RestaurantTable::create([
            'name' => 'Mesa Audit 1',
            'capacity' => 4,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $order = \App\Models\RestaurantOrder::create([
            'table_id' => $table->id,
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-AUDIT-999',
            'order_type' => 'table',
            'status' => 'open',
            'subtotal' => 30000,
            'total' => 30000,
        ]);

        $category = \App\Models\Category::firstOrCreate(
            ['slug' => 'platos-audit'],
            ['name' => 'Platos Audit', 'is_active' => true]
        );

        $product = \App\Models\Product::create([
            'sku' => 'PLATO-AUDIT-01',
            'nombre' => 'Bowl Fitness Audit',
            'category_id' => $category->id,
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'precio_compra' => 10000,
            'precio_venta' => 30000,
            'stock' => 10,
            'stock_minimo' => 2,
            'activo' => true,
        ]);

        $orderItem = \App\Models\RestaurantOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 30000,
            'subtotal' => 30000,
            'status' => 'served',
            'is_paid' => true, // Ya pagado previamente
            'batch_number' => 1,
        ]);

        $orderService = app(\App\Services\Restaurant\RestaurantOrderService::class);

        $this->expectException(\InvalidArgumentException::class);
        $orderService->settlePartialItems($order, [$orderItem->id], [
            'payments' => [
                ['method' => 'cash', 'amount' => 30000, 'cash_received' => 30000],
            ],
        ], $this->admin);
    }
}

