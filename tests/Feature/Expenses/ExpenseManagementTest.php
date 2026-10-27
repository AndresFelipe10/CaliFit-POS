<?php

namespace Tests\Feature\Expenses;

use App\Models\Expense;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Supplier $supplier;

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

        $this->supplier = Supplier::create([
            'name' => 'Distribuidora Avícola Cali',
            'nit' => '900123456-1',
            'contact_name' => 'Juan Vendedor',
            'phone' => '3151234567',
            'is_active' => true,
        ]);
    }

    /**
     * Administrador puede registrar gasto con comprobante adjunto en almacenamiento privado.
     */
    public function test_admin_can_create_expense_with_private_attachment(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('factura_pollo.pdf', 300, 'application/pdf');

        $response = $this->actingAs($this->admin)->post(route('expenses.store'), [
            'category' => 'merchandise',
            'amount' => 150000.50,
            'issue_date' => Carbon::today()->toDateString(),
            'due_date' => Carbon::today()->addDays(15)->toDateString(),
            'description' => 'Compra de pechuga de pollo para recetas',
            'invoice_number' => 'FAC-9876',
            'supplier_id' => $this->supplier->id,
            'status' => 'pending',
            'attachment' => $file,
        ]);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'invoice_number' => 'FAC-9876',
            'category' => 'merchandise',
            'amount' => 150000.50,
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        $expense = Expense::where('invoice_number', 'FAC-9876')->first();
        $this->assertNotNull($expense->attachment_path);
        Storage::disk('local')->assertExists($expense->attachment_path);
    }

    /**
     * Gasto pendiente con fecha de vencimiento pasada calcula dinámicamente estado overdue.
     */
    public function test_expense_dynamic_overdue_status(): void
    {
        $overdueExpense = Expense::create([
            'created_by' => $this->admin->id,
            'category' => 'utilities',
            'amount' => 320000,
            'issue_date' => Carbon::today()->subDays(20)->toDateString(),
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
            'status' => 'pending',
        ]);

        $this->assertTrue($overdueExpense->is_overdue);

        // Si se marca como pagada, no debe figurar vencida
        $overdueExpense->update(['status' => 'paid', 'paid_at' => Carbon::today()->toDateString()]);
        $this->assertFalse($overdueExpense->fresh()->is_overdue);
    }

    /**
     * Descarga de comprobante seguro emite cabecera X-Content-Type-Options: nosniff.
     */
    public function test_secure_attachment_download_with_nosniff_header(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('recibo_arriendo.pdf', 200, 'application/pdf');
        $path = $file->storeAs('private/expenses', 'recibo_arriendo.pdf', 'local');

        $expense = Expense::create([
            'created_by' => $this->admin->id,
            'category' => 'rent',
            'amount' => 1800000,
            'issue_date' => Carbon::today()->toDateString(),
            'status' => 'paid',
            'paid_at' => Carbon::today()->toDateString(),
            'payment_method' => 'transfer',
            'attachment_path' => $path,
        ]);

        $response = $this->actingAs($this->admin)->get(route('expenses.attachment', $expense));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    /**
     * Usuario no autorizado (empleado) no puede descargar el comprobante adjunto (403 Forbidden).
     */
    public function test_unauthorized_user_cannot_download_expense_attachment(): void
    {
        Storage::fake('local');

        $path = 'private/expenses/test_private.pdf';
        Storage::disk('local')->put($path, 'dummy content');

        $expense = Expense::create([
            'created_by' => $this->admin->id,
            'category' => 'supplies',
            'amount' => 50000,
            'issue_date' => Carbon::today()->toDateString(),
            'status' => 'paid',
            'attachment_path' => $path,
        ]);

        $response = $this->actingAs($this->employee)->get(route('expenses.attachment', $expense));
        $response->assertStatus(403);
    }

    /**
     * Marcar gasto como pagado actualiza status, paid_at y payment_method.
     */
    public function test_mark_expense_as_paid_updates_status(): void
    {
        $expense = Expense::create([
            'created_by' => $this->admin->id,
            'category' => 'other',
            'amount' => 75000,
            'issue_date' => Carbon::today()->toDateString(),
            'status' => 'pending',
        ]);

        $payDate = Carbon::today()->toDateString();
        $response = $this->actingAs($this->admin)->patch(route('expenses.pay', $expense), [
            'paid_at' => $payDate,
            'payment_method' => 'transfer',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHas('success');

        $expense->refresh();
        $this->assertEquals('paid', $expense->status);
        $this->assertEquals($payDate, $expense->paid_at->toDateString());
        $this->assertEquals('transfer', $expense->payment_method);
    }

    /**
     * Administrador puede registrar gasto pagado inmediatamente con monto formateado COP.
     */
    public function test_admin_can_create_expense_paid_immediately_with_cop_format_amount(): void
    {
        $response = $this->actingAs($this->admin)->post(route('expenses.store'), [
            'category' => 'supplies',
            'amount' => '150.000',
            'issue_date' => Carbon::today()->toDateString(),
            'description' => 'Servilletas y cubiertos biodegradables',
            'status' => 'paid',
        ]);

        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('expenses', [
            'category' => 'supplies',
            'amount' => 150000.00,
            'status' => 'paid',
            'payment_method' => 'cash',
            'created_by' => $this->admin->id,
        ]);

        $indexResponse = $this->actingAs($this->admin)->get(route('expenses.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('$150.000');
    }
}
