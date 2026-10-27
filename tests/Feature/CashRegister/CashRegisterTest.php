<?php

namespace Tests\Feature\CashRegister;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdmin = Role::create([
            'slug' => Role::ROLE_ADMIN,
            'name' => 'Administrador',
        ]);

        $this->admin = User::create([
            'role_id' => $roleAdmin->id,
            'name' => 'Admin CaliFit',
            'email' => 'admin@califit.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->today = now()->timezone('America/Bogota')->format('Y-m-d');
    }

    /**
     * Test de consolidación diaria de ventas, canales (Salón, Domicilios, Mostrador) y fletes.
     */
    public function test_cash_register_daily_consolidation(): void
    {
        $now = Carbon::createFromFormat('Y-m-d H:i:s', "{$this->today} 13:00:00", 'America/Bogota');

        // Venta 1: Salón (Mesas) - $30.000
        $saleTable = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Mesa 1 Comensal',
            'customer_document' => '222222222222',
            'order_type' => 'table',
            'table_id' => 1,
            'invoice_number' => 'FAC-001',
            'subtotal' => 30000,
            'service_fee' => 3000,
            'tax_inc' => 2222,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => 33000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        SalePayment::create([
            'sale_id' => $saleTable->id,
            'method' => 'cash',
            'amount' => 33000,
            'cash_received' => 40000,
            'change_given' => 7000,
        ]);

        // Venta 2: Domicilio con Flete de $5.000 - Total $25.000
        $saleDelivery = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Bochalema Domicilio',
            'customer_document' => '1005112233',
            'order_type' => 'delivery',
            'invoice_number' => 'FAC-002',
            'subtotal' => 20000,
            'service_fee' => 0,
            'tax_inc' => 1481,
            'discount' => 0,
            'delivery_fee' => 5000,
            'total' => 25000,
            'payment_method' => 'transfer',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        SalePayment::create([
            'sale_id' => $saleDelivery->id,
            'method' => 'transfer',
            'amount' => 25000,
        ]);

        // Venta 3: Mostrador / Retail (Suplementos) - $15.000
        $saleRetail = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Mostrador',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-003',
            'subtotal' => 15000,
            'service_fee' => 0,
            'tax_inc' => 0,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => 15000,
            'payment_method' => 'card',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        SalePayment::create([
            'sale_id' => $saleRetail->id,
            'method' => 'card',
            'amount' => 15000,
        ]);

        // Petición al módulo de cuadre de caja
        $response = $this->actingAs($this->admin)->get(route('cash-register.index', ['date' => $this->today]));

        $response->assertOk();
        $response->assertViewIs('cash-register.index');

        // Verificación de datos consolidados
        $response->assertViewHas('salesCount', 3);
        $response->assertViewHas('grossSales', 65000.0);
        $response->assertViewHas('serviceTips', 3000.0);
        $response->assertViewHas('deliveryFees', 5000.0);
        $response->assertViewHas('netTotalBilled', 73000.0);

        // Canales
        $response->assertViewHas('tableTotal', 33000.0);
        $response->assertViewHas('deliveryTotal', 25000.0);
        $response->assertViewHas('deliveryFeesTotal', 5000.0);
        $response->assertViewHas('retailTotal', 15000.0);

        // Arqueo
        $response->assertViewHas('cashAmount', 33000.0);
        $response->assertViewHas('transferAmount', 25000.0);
        $response->assertViewHas('cardAmount', 15000.0);
        $response->assertViewHas('totalCollected', 73000.0);
    }

    /**
     * Test de arqueo de caja con desglose de efectivo recibido y vueltos entregados.
     */
    public function test_cash_register_drawer_balance_with_change(): void
    {
        $now = Carbon::createFromFormat('Y-m-d H:i:s', "{$this->today} 14:30:00", 'America/Bogota');

        // Venta de $25.000 pagada con billete de $50.000 -> cambio de $25.000
        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Pago Efectivo Test',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-EFE-01',
            'subtotal' => 25000,
            'service_fee' => 0,
            'tax_inc' => 0,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 25000,
            'cash_received' => 50000,
            'change_given' => 25000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('cash-register.index', ['date' => $this->today]));

        $response->assertOk();
        $response->assertViewHas('cashAmount', 25000.0);
        $response->assertViewHas('cashReceived', 50000.0);
        $response->assertViewHas('changeGiven', 25000.0);
        $response->assertViewHas('cashInDrawer', 25000.0);
    }

    /**
     * Test de renderizado de la tirilla térmica de 58 mm para cuadre de caja.
     */
    public function test_print_cash_register_renders_successfully(): void
    {
        $now = Carbon::createFromFormat('Y-m-d H:i:s', "{$this->today} 16:00:00", 'America/Bogota');

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Venta Impresión Test',
            'customer_document' => '222222222222',
            'order_type' => 'table',
            'invoice_number' => 'FAC-PRINT-01',
            'subtotal' => 45000,
            'service_fee' => 0,
            'tax_inc' => 0,
            'discount' => 0,
            'delivery_fee' => 0,
            'total' => 45000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 45000,
            'cash_received' => 50000,
            'change_given' => 5000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('prints.cash-register', ['date' => $this->today]));

        $response->assertOk();
        $response->assertViewIs('prints.cash-register');

        // Validar textos requeridos del ticket térmico
        $response->assertSee('*** CUADRE DE CAJA DIARIO ***');
        $response->assertSee('SABORCALIFIT | BOCHALEMA');
        $response->assertSee('Arbolatta Mall - Cali, Colombia');
        $response->assertSee('TOTAL FACTURADO:');
        $response->assertSee('45.000');
        $response->assertSee('EFECTIVO NETO EN GAVETA:');
        $response->assertSee('TOTAL RECAUDADO:');
        $response->assertSee('Firma Cajero:');
        $response->assertSee('Firma Administrador:');
        $response->assertSee('window.print()', false);
    }

    /**
     * Test que verifica que CashRegisterController agregue correctamente los pagos directos
     * cuando method es una instancia de BackedEnum (PaymentMethod::Cash, Transfer, Card, Other).
     */
    public function test_cash_register_aggregates_payments_with_backed_enum_methods(): void
    {
        $now = Carbon::createFromFormat('Y-m-d H:i:s', "{$this->today} 15:00:00", 'America/Bogota');

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Enums Test',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-ENUM-01',
            'subtotal' => 100000,
            'total' => 100000,
            'payment_method' => 'mixed',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Crear 4 pagos usando instancias del enum PaymentMethod
        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => PaymentMethod::Cash,
            'amount' => 40000,
            'cash_received' => 40000,
            'change_given' => 0,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => PaymentMethod::Transfer,
            'amount' => 30000,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => PaymentMethod::Card,
            'amount' => 20000,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => PaymentMethod::Other,
            'amount' => 10000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('cash-register.index', ['date' => $this->today]));

        $response->assertOk();
        $response->assertViewHas('cashAmount', 40000.0);
        $response->assertViewHas('transferAmount', 30000.0);
        $response->assertViewHas('cardAmount', 20000.0);
        $response->assertViewHas('otherAmount', 10000.0);
        $response->assertViewHas('totalCollected', 100000.0);
    }

    /**
     * Test de trazabilidad contable integral de Gastos y Facturas en Cuadre de Caja:
     * Venta en efectivo $25.000 + Gasto pagado en efectivo $10.000 -> Efectivo Neto Gaveta $15.000.
     */
    public function test_cash_register_traces_expenses_and_calculates_net_drawer_and_balance(): void
    {
        $now = Carbon::createFromFormat('Y-m-d H:i:s', "{$this->today} 11:00:00", 'America/Bogota');

        // 1. Venta de $25.000 pagada en efectivo
        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Mostrador Prueba',
            'customer_document' => '1111111111',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-EXP-01',
            'subtotal' => 25000,
            'total' => 25000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 25000,
            'cash_received' => 25000,
            'change_given' => 0,
        ]);

        // 2. Gasto pagado inmediatamente en efectivo por $10.000 hoy
        $paidExpense = Expense::create([
            'created_by' => $this->admin->id,
            'invoice_number' => 'FAC-PROV-101',
            'issue_date' => $this->today,
            'paid_at' => $this->today,
            'category' => 'merchandise',
            'description' => 'Insumos Verduras y Frutas',
            'amount' => 10000,
            'status' => 'paid',
            'payment_method' => 'cash',
        ]);

        // 3. Gasto pendiente registrado hoy por $5.000
        $pendingExpense = Expense::create([
            'created_by' => $this->admin->id,
            'invoice_number' => 'FAC-PROV-102',
            'issue_date' => $this->today,
            'category' => 'utilities',
            'description' => 'Factura de Energía Eléctrica',
            'amount' => 5000,
            'status' => 'pending',
            'payment_method' => null,
        ]);

        // Petición al cuadre de caja
        $response = $this->actingAs($this->admin)->get(route('cash-register.index', ['date' => $this->today]));

        $response->assertOk();
        $response->assertViewHas('grossCashInDrawer', 25000.0);
        $response->assertViewHas('expensesCashTotal', 10000.0);
        $response->assertViewHas('netCashInDrawer', 15000.0);
        $response->assertViewHas('totalExpensesPaidToday', 10000.0);
        $response->assertViewHas('totalExpensesPendingToday', 5000.0);
        $response->assertViewHas('netCashBalance', 15000.0);

        // Validación visual en el HTML:
        // No debe haber 'cash:' en inglés y debe mostrarse 'Efectivo: $25.000'
        $response->assertSee('Efectivo: $25.000');
        $response->assertDontSee('cash: $25.000');
        $response->assertDontSee('cash:');

        // Trazabilidad de gastos en la tabla inferior
        $response->assertSee('Detalle de Gastos y Facturas Registrados en la Fecha');
        $response->assertSee('Insumos Verduras y Frutas');
        $response->assertSee('Factura de Energía Eléctrica');
        $response->assertSee('FAC-PROV-101');
    }

    /**
     * Test de impresión térmica de 58 mm con sección de gastos y balance neto.
     */
    public function test_cash_register_thermal_print_includes_expenses_breakdown(): void
    {
        $now = Carbon::createFromFormat('Y-m-d H:i:s', "{$this->today} 12:00:00", 'America/Bogota');

        $sale = Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Comensal Salón',
            'customer_document' => '1111111111',
            'order_type' => 'table',
            'invoice_number' => 'FAC-TICKET-01',
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 50000,
            'cash_received' => 50000,
            'change_given' => 0,
        ]);

        Expense::create([
            'created_by' => $this->admin->id,
            'invoice_number' => 'G-9988',
            'issue_date' => $this->today,
            'paid_at' => $this->today,
            'category' => 'merchandise',
            'description' => 'Hielo y Aguacates',
            'amount' => 12000,
            'status' => 'paid',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->admin)->get(route('prints.cash-register', ['date' => $this->today]));

        $response->assertOk();
        $response->assertSee('--- EGRESOS / GASTOS DEL DÍA ---');
        $response->assertSee('GASTOS PAGADOS EN EFECTIVO:');
        $response->assertSee('12.000');
        $response->assertSee('TOTAL EGRESOS PAGADOS HOY:');
        $response->assertSee('EFECTIVO NETO EN GAVETA:');
        $response->assertSee('38.000'); // 50.000 - 12.000
        $response->assertSee('BALANCE NETO DEL TURNO:');
        $response->assertSee('Hielo y Aguacates');
    }

    /**
     * Test de visualización ergonómica en la ficha del cliente:
     * Botón píldora 'Volver al Directorio de Clientes' y título 'Ficha del Cliente y Cartera'.
     */
    public function test_customer_show_renders_ergonomic_pill_back_button_and_spanish_title(): void
    {
        $customer = Customer::create([
            'name' => 'Carlos Valderrama',
            'document' => '1122334455',
            'phone' => '3001234567',
            'email' => 'pibe@califit.com',
            'address' => 'Bochalema, Cali',
            'credit_limit' => 200000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('customers.show', $customer));

        $response->assertOk();
        $response->assertSee('Volver al Directorio de Clientes');
        $response->assertSee('Ficha del Cliente y Cartera');
        $response->assertDontSee('Ficha del Cliente &amp; Cartera');
        $response->assertDontSee('Ficha del Cliente & Cartera');
    }

    public function test_cash_register_header_does_not_contain_america_bogota_visual_noise(): void
    {
        $response = $this->actingAs($this->admin)->get(route('cash-register.index'));

        $response->assertOk();
        $response->assertDontSee('Zona: America/Bogota');
    }
}
