<?php

namespace Tests\Feature\Directory;

use App\Models\Customer;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;

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
            'name' => 'Empleado CaliFit',
            'email' => 'empleado@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    public function test_employee_can_access_customers_and_create_via_ajax_quick_customer(): void
    {
        // 1. Empleado puede acceder a la lista de clientes (HTTP 200)
        $this->actingAs($this->employee)
            ->get(route('customers.index'))
            ->assertOk();

        // 2. Empleado puede crear un cliente vía AJAX rápido con respuesta JSON 201
        $response = $this->actingAs($this->employee)->postJson(route('customers.store'), [
            'nombre' => 'Cliente Operativo',
            'document' => '1005554444',
            'telefono' => '3157778899',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'customer' => [
                    'name' => 'Cliente Operativo',
                    'document_number' => '1005554444',
                    'phone' => '3157778899',
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'Cliente Operativo',
            'document' => '1005554444',
            'phone' => '3157778899',
        ]);
    }

    public function test_employee_is_forbidden_from_supplier_management(): void
    {
        // Proveedores index y store permanecen exclusivos de Administrador (HTTP 403)
        $this->actingAs($this->employee)->get(route('suppliers.index'))->assertStatus(403);
        $this->actingAs($this->employee)->post(route('suppliers.store'), [
            'name' => 'Distribuidora S.A.',
            'nit' => '900999888-1',
        ])->assertStatus(403);
    }

    public function test_backend_rejects_invalid_inputs_strict_hygiene(): void
    {
        // 1. Nombre con números (papaya1111) debe ser rechazado con 422
        $responseName = $this->actingAs($this->admin)->postJson(route('customers.store'), [
            'nombre' => 'papaya1111',
            'document' => '1099887766',
            'telefono' => '3112223344',
        ]);
        $responseName->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        // 2. Cédula con letras (111sssss) debe ser rechazada con 422
        $responseDoc = $this->actingAs($this->admin)->postJson(route('customers.store'), [
            'nombre' => 'Cliente Valido',
            'document' => '111sssss',
            'telefono' => '3112223344',
        ]);
        $responseDoc->assertStatus(422)
            ->assertJsonValidationErrors(['document']);

        // 3. Teléfono con letras (111s) debe ser rechazado con 422
        $responsePhone = $this->actingAs($this->admin)->postJson(route('customers.store'), [
            'nombre' => 'Cliente Valido',
            'document' => '1099887766',
            'telefono' => '111s',
        ]);
        $responsePhone->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);
    }

    public function test_cannot_create_customer_with_dian_final_consumer_document(): void
    {
        // La DIAN prohíbe registrar cliente individual con el NIT 222222222222 (reservado para Consumidor Final genérico)
        $response = $this->actingAs($this->admin)->post(route('customers.store'), [
            'nombre' => 'Consumidor Final Manual',
            'document' => '222222222222',
            'telefono' => '3001234567',
        ]);

        $response->assertSessionHasErrors(['document']);
        $this->assertEquals(0, Customer::where('document', '222222222222')->count());
    }

    public function test_admin_can_manage_customers_and_suppliers(): void
    {
        // 1. Gestión de Clientes
        $customerResponse = $this->actingAs($this->admin)->post(route('customers.store'), [
            'nombre' => 'Carlos Andrés Morales',
            'document' => '1144098765',
            'telefono' => '3128901234',
            'email' => 'carlos.morales@correo.com',
            'direccion' => 'Conjunto Arbolatta Torre 2 Apto 304',
        ]);

        $customerResponse->assertRedirect(route('customers.index'));
        $customerResponse->assertSessionHas('success', "Cliente 'Carlos Andrés Morales' registrado exitosamente.");
        $this->assertDatabaseHas('customers', [
            'name' => 'Carlos Andrés Morales',
            'document' => '1144098765',
            'phone' => '3128901234',
            'email' => 'carlos.morales@correo.com',
            'is_active' => true,
        ]);

        $customer = Customer::where('document', '1144098765')->firstOrFail();

        // Actualización de cliente
        $this->actingAs($this->admin)->put(route('customers.update', $customer), [
            'nombre' => 'Carlos Andrés Morales Ortiz',
            'document' => '1144098765',
            'telefono' => '3128909999',
            'email' => 'carlos.nuevo@correo.com',
            'direccion' => 'Bochalema Manzana 4',
        ])->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Carlos Andrés Morales Ortiz',
            'phone' => '3128909999',
        ]);

        // Ver detalle de cliente
        $this->actingAs($this->admin)->get(route('customers.show', $customer))->assertStatus(200);

        // Toggle status cliente
        $this->actingAs($this->admin)->patch(route('customers.toggle-status', $customer))->assertRedirect(route('customers.index'));
        $this->assertFalse($customer->fresh()->is_active);

        // 2. Gestión de Proveedores
        $supplierResponse = $this->actingAs($this->admin)->post(route('suppliers.store'), [
            'name' => 'Nutrición Total Colombia S.A.S',
            'nit' => '901234567-8',
            'contact_name' => 'Mauricio Londoño',
            'phone' => '3167778899',
            'email' => 'pedidos@nutriciontotal.co',
            'address' => 'Zona Industrial Acopi, Yumbo',
        ]);

        $supplierResponse->assertRedirect(route('suppliers.index'));
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Nutrición Total Colombia S.A.S',
            'nit' => '901234567-8',
            'contact_name' => 'Mauricio Londoño',
            'phone' => '3167778899',
            'is_active' => true,
        ]);

        $supplier = Supplier::where('nit', '901234567-8')->firstOrFail();

        // Actualización de proveedor
        $this->actingAs($this->admin)->put(route('suppliers.update', $supplier), [
            'name' => 'Nutrición Total Colombia S.A.S',
            'nit' => '901234567-8',
            'contact_name' => 'Mauricio Londoño Gerente Comercial',
            'phone' => '3167770000',
            'email' => 'mauricio@nutriciontotal.co',
            'address' => 'Zona Industrial Acopi, Yumbo',
        ])->assertRedirect(route('suppliers.index'));

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'contact_name' => 'Mauricio Londoño Gerente Comercial',
            'phone' => '3167770000',
        ]);

        // Ver detalle de proveedor
        $this->actingAs($this->admin)->get(route('suppliers.show', $supplier))->assertStatus(200);

        // Toggle status proveedor
        $this->actingAs($this->admin)->patch(route('suppliers.toggle-status', $supplier))->assertRedirect(route('suppliers.index'));
        $this->assertFalse($supplier->fresh()->is_active);
    }

    public function test_customer_directory_displays_verified_purchases_count_badge(): void
    {
        $customer = Customer::create([
            'name' => 'Mariana Fitness',
            'document' => '1199887766',
            'phone' => '3123456789',
            'email' => 'mariana@califit.com',
            'is_active' => true,
        ]);

        // 1. Venta completada
        Sale::create([
            'invoice_number' => 'FAC-TEST-001',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'user_id' => $this->admin->id,
            'subtotal' => 50000,
            'total' => 50000,
            'status' => 'completed',
        ]);

        // 2. Venta cancelada/anulada (no debe contarse en trazabilidad de compras efectivas)
        Sale::create([
            'invoice_number' => 'FAC-TEST-002',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'user_id' => $this->admin->id,
            'subtotal' => 30000,
            'total' => 30000,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($this->admin)->get(route('customers.index'));

        $response->assertOk()
            ->assertSee('bi-receipt')
            ->assertSee('1 compra')
            ->assertSee('margin-right: 6px', false)
            ->assertSee('background-color: #EFF6FF', false)
            ->assertSee('color: #2563EB', false);
    }

    public function test_supplier_directory_displays_verified_invoices_count_badge(): void
    {
        $supplier = Supplier::create([
            'name' => 'Distribuidora Carnes y Granos del Valle SAS',
            'nit' => '900555444-1',
            'contact_name' => 'Mateo Gómez',
            'phone' => '3128889900',
            'email' => 'ventas@carnesygranos.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('suppliers.index'));

        $response->assertOk()
            ->assertSee('bi-receipt-cutoff')
            ->assertSee('0 facturas')
            ->assertSee('margin-right: 6px', false)
            ->assertSee('background-color: #EFF6FF', false)
            ->assertSee('color: #2563EB', false);
    }
}
