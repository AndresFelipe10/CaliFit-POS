<?php

namespace Tests\Feature\Credit;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCreditSearchAndGroupedTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cajero;
    protected Customer $clienteA;
    protected Customer $clienteB;
    protected Product $producto;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::firstOrCreate(['slug' => Role::ROLE_ADMIN], ['name' => 'Administrador']);
        $roleEmployee = Role::firstOrCreate(['slug' => Role::ROLE_EMPLOYEE], ['name' => 'Empleado POS']);

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->cajero = User::create([
            'role_id' => $roleEmployee->id,
            'name' => 'Cajero CaliFit',
            'email' => 'cajero@califit.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->clienteA = Customer::create([
            'name' => 'Carlos Andrés Rodríguez',
            'document' => '1144123456',
            'phone' => '3157778899',
            'is_active' => true,
        ]);

        $this->clienteB = Customer::create([
            'name' => 'Pepito Pérez',
            'document' => '2222111100',
            'phone' => '3001234567',
            'is_active' => true,
        ]);

        $categoria = Category::create(['name' => 'Suplementación', 'is_active' => true]);

        $this->producto = Product::create([
            'sku' => 'SUP-TEST-01',
            'nombre' => 'Proteína Whey 2lb',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $categoria->id,
            'stock' => 50,
            'precio_venta' => 25000,
            'activo' => true,
        ]);

        // Factura 1 para Cliente A: $25.000 pendiente
        $saleA1 = Sale::create([
            'invoice_number' => 'FAC-000058',
            'customer_id' => $this->clienteA->id,
            'customer_name' => $this->clienteA->name,
            'customer_document' => $this->clienteA->document,
            'user_id' => $this->cajero->id,
            'subtotal' => 25000,
            'total' => 25000,
            'status' => 'completed',
            'is_credit' => true,
            'credit_status' => 'pending',
            'credit_due_date' => now()->addDays(15),
        ]);
        SaleDetail::create([
            'sale_id' => $saleA1->id,
            'product_id' => $this->producto->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'cost_price' => 15000,
        ]);

        // Factura 2 para Cliente A: $36.000 pendiente
        $saleA2 = Sale::create([
            'invoice_number' => 'FAC-000059',
            'customer_id' => $this->clienteA->id,
            'customer_name' => $this->clienteA->name,
            'customer_document' => $this->clienteA->document,
            'user_id' => $this->cajero->id,
            'subtotal' => 36000,
            'total' => 36000,
            'status' => 'completed',
            'is_credit' => true,
            'credit_status' => 'pending',
            'credit_due_date' => now()->addDays(15),
        ]);
        SaleDetail::create([
            'sale_id' => $saleA2->id,
            'product_id' => $this->producto->id,
            'quantity' => 1,
            'unit_price' => 36000,
            'subtotal' => 36000,
            'cost_price' => 20000,
        ]);

        // Factura 1 para Cliente B: $185.000 pendiente
        $saleB1 = Sale::create([
            'invoice_number' => 'FAC-000057',
            'customer_id' => $this->clienteB->id,
            'customer_name' => $this->clienteB->name,
            'customer_document' => $this->clienteB->document,
            'user_id' => $this->cajero->id,
            'subtotal' => 185000,
            'total' => 185000,
            'status' => 'completed',
            'is_credit' => true,
            'credit_status' => 'pending',
            'credit_due_date' => now()->addDays(15),
        ]);
        SaleDetail::create([
            'sale_id' => $saleB1->id,
            'product_id' => $this->producto->id,
            'quantity' => 1,
            'unit_price' => 185000,
            'subtotal' => 185000,
            'cost_price' => 100000,
        ]);
    }

    public function test_credit_cartera_index_renders_search_bar_customer_filter_and_tabs(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get(route('credit.index'));

        $response->assertStatus(200)
            ->assertSee('Cartera &amp; Cuentas por Cobrar (Fiados)', false)
            ->assertSee('Clientes con Deuda Activa')
            ->assertSee('bi-people-fill')
            ->assertSee('Búsqueda Rápida')
            ->assertSee('Filtrar por Cliente con Deuda')
            ->assertSee('Agrupado por Cliente')
            ->assertSee('Facturas Individuales')
            ->assertSee('Carlos Andrés Rodríguez')
            ->assertSee('Pepito Pérez')
            ->assertSee('Reunir Facturas');
    }

    public function test_filter_by_customer_id_gathers_all_pending_invoices_for_that_customer(): void
    {
        $this->actingAs($this->cajero);

        // Filtrar exclusivamente por Cliente A
        $response = $this->get(route('credit.index', ['customer_id' => $this->clienteA->id, 'tab' => 'grouped']));

        $response->assertStatus(200);

        // Debe mostrar a Carlos Andrés Rodríguez reunido con sus 2 facturas y $61.000
        $response->assertSee('Carlos Andrés Rodríguez')
            ->assertSee('FAC-000058')
            ->assertSee('FAC-000059')
            ->assertSee('2 facturas')
            ->assertSee('61.000') // $25.000 + $36.000
            ->assertSee('Mostrando facturas pendientes de:')
            // No debe mostrar a Pepito Pérez en la vista filtrada
            ->assertDontSee('FAC-000057');
    }

    public function test_search_by_invoice_number_or_text_filters_results(): void
    {
        $this->actingAs($this->admin);

        // Búsqueda por número de factura específico
        $response = $this->get(route('credit.index', ['search' => 'FAC-000057', 'tab' => 'invoices']));

        $response->assertStatus(200)
            ->assertSee('FAC-000057')
            ->assertSee('Pepito Pérez')
            ->assertDontSee('FAC-000058')
            ->assertDontSee('FAC-000059');
    }

    public function test_grouped_view_contains_accordion_with_individual_payment_buttons(): void
    {
        $this->actingAs($this->cajero);

        $response = $this->get(route('credit.index', ['tab' => 'grouped']));

        $response->assertStatus(200)
            ->assertSee('collapseCustomerInvoices' . $this->clienteA->id)
            ->assertSee('Reunir Facturas (2)')
            ->assertSee('openPaymentModal');
    }

    public function test_cancelled_credit_sale_is_excluded_from_pending_invoices_and_shows_audit_trace(): void
    {
        // 1. Crear factura 3 para cliente A que luego será anulada
        $saleA3 = Sale::create([
            'invoice_number' => 'FAC-000079',
            'customer_id' => $this->clienteA->id,
            'customer_name' => $this->clienteA->name,
            'customer_document' => $this->clienteA->document,
            'user_id' => $this->cajero->id,
            'subtotal' => 25000,
            'total' => 25000,
            'status' => 'completed',
            'is_credit' => true,
            'credit_status' => 'pending',
            'credit_due_date' => now()->addDays(15),
        ]);
        SaleDetail::create([
            'sale_id' => $saleA3->id,
            'product_id' => $this->producto->id,
            'quantity' => 1,
            'unit_price' => 25000,
            'subtotal' => 25000,
            'cost_price' => 15000,
        ]);

        // 2. Anular la venta vía SaleService
        $saleService = app(\App\Services\Sales\SaleService::class);
        $saleService->cancelSale($saleA3, $this->admin, 'Cliente solicitó anulación por error en pedido');

        $saleA3Fresh = $saleA3->fresh();
        $this->assertEquals('cancelled', $saleA3Fresh->status);
        $this->assertEquals('cancelled', $saleA3Fresh->credit_status);
        $this->assertEquals(0.00, $saleA3Fresh->credit_pending_amount);

        // 3. Consultar cartera / fiados: Cliente A debe tener solo 2 facturas pendientes y $61.000 (no 3 facturas ni $86.000)
        $this->actingAs($this->admin);
        $responseGrouped = $this->get(route('credit.index', ['tab' => 'grouped']));
        $responseGrouped->assertStatus(200);
        $responseGrouped->assertSee('Carlos Andrés Rodríguez');
        $responseGrouped->assertSee('Reunir Facturas (2)');
        $responseGrouped->assertDontSee('Reunir Facturas (3)');
        $responseGrouped->assertSee('61.000');
        $responseGrouped->assertDontSee('86.000');

        // En la lista de facturas pendientes de cobro NO debe aparecer FAC-000079
        $responseGrouped->assertDontSee('FAC-000079');

        // 4. En el historial de crédito del cliente sí debe aparecer con estado Anulada y saldo $0
        $responseHistory = $this->get(route('customers.credit', $this->clienteA));
        $responseHistory->assertStatus(200);
        $responseHistory->assertSee('FAC-000079');
        $responseHistory->assertSee('Anulada');
    }
}
