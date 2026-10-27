<?php

namespace Tests\Feature\Dashboard;

use App\Models\Expense;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFinancialTest extends TestCase
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
            'name' => 'Cajero CaliFit',
            'email' => 'cajero@califit.com',
            'password' => bcrypt('password123'),
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    /**
     * Administrador visualiza el cálculo de utilidad neta: Ventas del Mes - Gastos del Mes.
     */
    public function test_admin_views_net_profit_calculation(): void
    {
        $now = Carbon::now('America/Bogota');

        // Venta del mes actual: $100.000
        Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente Mes',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-M01',
            'subtotal' => 100000,
            'total' => 100000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Gasto del mes actual: $40.000
        Expense::create([
            'created_by' => $this->admin->id,
            'category' => 'utilities',
            'amount' => 40000,
            'issue_date' => $now->toDateString(),
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('isAdmin', true);
        $response->assertViewHas('monthSalesTotal', 100000.0);
        $response->assertViewHas('monthExpensesTotal', 40000.0);
        $response->assertViewHas('netProfit', 60000.0); // 100.000 - 40.000 = 60.000
    }

    /**
     * Dashboard de empleados enmascara y omite por completo los montos monetarios de facturación y utilidades.
     */
    public function test_employee_dashboard_omits_financial_amounts(): void
    {
        $now = Carbon::now('America/Bogota');

        // Venta del mes de $500.000
        Sale::create([
            'user_id' => $this->employee->id,
            'customer_name' => 'Cliente Secreto',
            'customer_document' => '222222222222',
            'order_type' => 'retail',
            'invoice_number' => 'FAC-EMP-01',
            'subtotal' => 500000,
            'total' => 500000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Gasto de $200.000
        Expense::create([
            'created_by' => $this->admin->id,
            'category' => 'rent',
            'amount' => 200000,
            'issue_date' => $now->toDateString(),
            'status' => 'paid',
        ]);

        $response = $this->actingAs($this->employee)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('isAdmin', false);
        $response->assertViewMissing('monthSalesTotal');
        $response->assertViewMissing('monthExpensesTotal');
        $response->assertViewMissing('netProfit');
        $response->assertViewMissing('averageTicket');

        // Validar que el HTML para empleado no contenga las cifras financieras del mes ni títulos gerenciales
        $response->assertDontSee('$500.000');
        $response->assertDontSee('$200.000');
        $response->assertDontSee('$300.000');
        $response->assertDontSee('Utilidad Neta Mes');
        $response->assertDontSee('Gastos del Mes');
    }

    /**
     * REQUERIMIENTO 2: Administrador visualiza métricas de propinas recaudadas en el Dashboard.
     */
    public function test_admin_views_tips_collected_metrics(): void
    {
        $now = Carbon::now('America/Bogota');

        // Venta 1 con propina de $5.000
        Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente 1',
            'customer_document' => '222222222222',
            'order_type' => 'table',
            'invoice_number' => 'FAC-TIP-01',
            'subtotal' => 50000,
            'tip' => 5000,
            'total' => 55000,
            'payment_method' => 'cash',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Venta 2 con propina de $8.000
        Sale::create([
            'user_id' => $this->admin->id,
            'customer_name' => 'Cliente 2',
            'customer_document' => '222222222222',
            'order_type' => 'table',
            'invoice_number' => 'FAC-TIP-02',
            'subtotal' => 80000,
            'tip' => 8000,
            'total' => 88000,
            'payment_method' => 'card',
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('periodTipsTotal', 13000.0);
        $response->assertViewHas('periodTipsCount', 2);
        $response->assertSee('$13.000');
        $response->assertSee('2 ventas con propina');
    }
}
