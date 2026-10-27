<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;
    protected User $admin;

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
     * Empleado autenticado recibe 403 en GET /expenses y POST /expenses, mientras que Admin tiene acceso.
     */
    public function test_employee_is_forbidden_from_expenses_routes(): void
    {
        $responseGet = $this->actingAs($this->employee)->get(route('expenses.index'));
        $responseGet->assertStatus(403);

        $responsePost = $this->actingAs($this->employee)->post(route('expenses.store'), [
            'category' => 'supplies',
            'amount' => 50000,
            'issue_date' => date('Y-m-d'),
            'status' => 'pending',
        ]);
        $responsePost->assertStatus(403);

        $responseAdmin = $this->actingAs($this->admin)->get(route('expenses.index'));
        $responseAdmin->assertOk();
    }

    /**
     * Empleado recibe 403 en gestión de equipo (/users), mientras que Administrador tiene acceso total.
     */
    public function test_employee_is_forbidden_from_user_management(): void
    {
        // Empleado recibe 403
        $this->actingAs($this->employee)
            ->get(route('users.index'))
            ->assertStatus(403);

        // Administrador tiene acceso permitido
        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Equipo y Colaboradores');
    }

    /**
     * Los endpoints /superadmin devuelven estrictamente 404 Not Found para cualquier actor.
     */
    public function test_superadmin_endpoints_strictly_return_404_not_found(): void
    {
        // Petición no autenticada a /superadmin
        $this->get('/superadmin')->assertNotFound();

        // Petición como empleado a /superadmin
        $this->actingAs($this->employee)->get('/superadmin')->assertNotFound();

        // Petición como administrador a /superadmin
        $this->actingAs($this->admin)->get('/superadmin')->assertNotFound();

        // Sub-rutas antiguas de superadmin
        $this->actingAs($this->admin)->get('/superadmin/users')->assertNotFound();
        $this->actingAs($this->admin)->get('/superadmin/businesses')->assertNotFound();
    }

    /**
     * REQUERIMIENTO 3: Administrador ve atributos de confirmación modal personalizada
     * tanto al desactivar un colaborador activo como al reactivar un colaborador inactivo.
     */
    public function test_admin_views_custom_confirmation_modal_attributes_when_activating_inactive_user(): void
    {
        // 1. Empleado activo: modal de desactivar
        $responseActive = $this->actingAs($this->admin)->get(route('users.index'));
        $responseActive->assertOk();
        $responseActive->assertSee('¿Desactivar Acceso?');
        $responseActive->assertSee("¿Desactivar el acceso al sistema para el colaborador 'Cajero CaliFit'?", false);
        $responseActive->assertSee('data-confirm-btn-class="btn-danger"', false);

        // 2. Desactivar empleado
        $this->employee->update(['is_active' => false]);

        // 3. Empleado inactivo: modal de activar
        $responseInactive = $this->actingAs($this->admin)->get(route('users.index'));
        $responseInactive->assertOk();
        $responseInactive->assertSee('¿Activar Acceso?');
        $responseInactive->assertSee("¿Restablecer y activar el acceso al sistema para el colaborador 'Cajero CaliFit'?", false);
        $responseInactive->assertSee('Sí, Activar');
        $responseInactive->assertSee('data-confirm-btn-class="btn-success"', false);
    }

    /**
     * Empleado (Mesero / Operativo) tiene acceso al módulo de Clientes, pero está restringido de Proveedores.
     */
    public function test_employee_can_access_customers_directory_but_forbidden_from_suppliers(): void
    {
        $this->actingAs($this->employee)
            ->get(route('customers.index'))
            ->assertOk();

        $this->actingAs($this->employee)
            ->get(route('suppliers.index'))
            ->assertStatus(403);
    }

    /**
     * Empleado recibe 403 Forbidden al intentar acceder a rutas administrativas de Inventario Físico.
     */
    public function test_employee_is_forbidden_from_inventory_routes(): void
    {
        $inventoryRoutes = [
            'inventory.index',
            'inventory.create',
            'inventory.movements',
            'inventory.alerts',
        ];

        foreach ($inventoryRoutes as $routeName) {
            $this->actingAs($this->employee)
                ->get(route($routeName))
                ->assertStatus(403);
        }

        // Intento de POST a movimientos o ajustes de inventario
        $this->actingAs($this->employee)
            ->post(route('inventory.entries.store'), [])
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->post(route('inventory.waste.store'), [])
            ->assertStatus(403);

        $this->actingAs($this->employee)
            ->post(route('inventory.adjustment.store'), [])
            ->assertStatus(403);
    }

    /**
     * Módulos de OPERACIÓN Y ATENCIÓN son accesibles tanto para Administrador como para Empleado.
     */
    public function test_operational_modules_are_accessible_to_both_roles(): void
    {
        $operationalRoutes = [
            'pos.index',
            'restaurant.tables.index',
            'restaurant.dispatch.index',
            'cash-register.index',
            'credit.index',
            'customers.index',
        ];

        foreach ($operationalRoutes as $routeName) {
            // Empleado puede acceder
            $this->actingAs($this->employee)
                ->get(route($routeName))
                ->assertOk();

            // Administrador puede acceder
            $this->actingAs($this->admin)
                ->get(route($routeName))
                ->assertOk();
        }
    }

    /**
     * Erradicación de rutas heredadas: /terms, /privacy, /api/dashboard devuelven estrictamente 404.
     */
    public function test_legacy_and_orphan_routes_strictly_return_404(): void
    {
        $this->get('/terms')->assertNotFound();
        $this->get('/privacy')->assertNotFound();
        $this->actingAs($this->employee)->get('/terms')->assertNotFound();
        $this->actingAs($this->employee)->get('/privacy')->assertNotFound();
        $this->actingAs($this->employee)->get('/api/dashboard')->assertNotFound();
        $this->actingAs($this->admin)->get('/api/dashboard')->assertNotFound();
    }
}
