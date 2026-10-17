<?php

namespace Tests\Feature\Security;

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUserProvisioningRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $business;
    protected User $tenantAdmin;
    protected User $collaborator;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Acceso y administración total']
        );

        $this->employeeRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado', 'description' => 'Operación de ventas y atención']
        );

        $this->business = Business::create([
            'name' => 'Restaurante Cali Sabor',
            'nit' => '900987654-1',
            'business_type' => 'restaurant',
        ]);

        $this->tenantAdmin = User::factory()->create([
            'name' => 'Admin Negocio',
            'email' => 'admin@negocio.com',
            'is_superadmin' => false,
        ]);
        $this->business->users()->attach($this->tenantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->collaborator = User::factory()->create([
            'name' => 'Mesero Juan',
            'email' => 'juan@negocio.com',
            'is_superadmin' => false,
        ]);
        $this->business->users()->attach($this->collaborator->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@puntostock.com',
            'is_superadmin' => true,
        ]);
    }

    /**
     * Test 1 (UI de Equipo sin botón de creación):
     * Un administrador de negocio accede a la vista de Equipo, visualiza a sus colaboradores
     * y botones Editar / Desactivar, pero NO se renderiza el botón "Vincular Colaborador".
     */
    public function test_tenant_admin_team_index_displays_members_and_actions_without_create_button(): void
    {
        $response = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('users.index'));

        $response->assertStatus(200);

        // Header and description
        $response->assertSee('Equipo de Trabajo');
        $response->assertSee('Visualiza los colaboradores asignados');
        $response->assertSee($this->business->name);

        // Members list and action buttons
        $response->assertSee($this->tenantAdmin->name);
        $response->assertSee($this->collaborator->name);
        $response->assertSee('Editar');
        $response->assertSee('Desactivar');

        // Modal triggers and elements
        $response->assertSee('btn-toggle-collaborator-status');
        $response->assertSee('id="toggleCollaboratorStatusModal"', false);
        $response->assertSee('id="toggleCollaboratorStatusForm"', false);
        $response->assertSee('data-target-action="deactivate"', false);
        $response->assertSee('data-action-url="' . route('users.toggleActive', $this->collaborator) . '"', false);

        // STRICT ASSERTIONS: No native confirm() dialogs and no "Vincular Colaborador" button
        $response->assertDontSee('confirm(');
        $response->assertDontSee('window.confirm(');
        $response->assertDontSee('Vincular Colaborador');
        $response->assertDontSee(route('users.create'));
    }

    /**
     * Test 2 (Bloqueo de creación en Backend para Tenant Admin):
     * Si un administrador de negocio intenta enviar una petición POST para crear un colaborador
     * por fuera del panel de Superadmin, recibe 403 Forbidden y no se inserta ningún registro en users.
     */
    public function test_tenant_admin_cannot_create_collaborators_in_backend_receiving_403(): void
    {
        // 1. GET /users/invite (route users.create) is blocked
        $inviteResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->get(route('users.create'));

        $inviteResponse->assertStatus(403);

        // 2. POST /users (route users.store) is blocked
        $storeResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.store'), [
                'name' => 'Nuevo Intento Ilegal',
                'email' => 'ilegal@negocio.com',
                'password' => 'password1234',
                'role_id' => $this->employeeRole->id,
            ]);

        $storeResponse->assertStatus(403);

        // Assert database is not mutated
        $this->assertDatabaseMissing('users', [
            'email' => 'ilegal@negocio.com',
        ]);
    }

    /**
     * Test 3 (Permiso de cambio de rol y estado en Tenant):
     * Un administrador de negocio puede cambiar el rol de un miembro de su equipo de Empleado a Administrador
     * (y viceversa) y activar/desactivar su cuenta dentro de su propio business_id.
     */
    public function test_tenant_admin_can_update_roles_and_toggle_active_status_within_tenant(): void
    {
        // 1. Promote collaborator to Admin
        $promoteResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->put(route('users.update', $this->collaborator), [
                'role_id' => $this->adminRole->id,
            ]);

        $promoteResponse->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $this->collaborator->id,
            'role_id' => $this->adminRole->id,
        ]);

        // 2. Demote collaborator back to Employee
        $demoteResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->put(route('users.update', $this->collaborator), [
                'role_id' => $this->employeeRole->id,
            ]);

        $demoteResponse->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $this->collaborator->id,
            'role_id' => $this->employeeRole->id,
        ]);

        // 3. Deactivate collaborator
        $deactivateResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.toggleActive', $this->collaborator));

        $deactivateResponse->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $this->collaborator->id,
            'is_active' => 0,
        ]);

        // 4. Reactivate collaborator
        $reactivateResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.toggleActive', $this->collaborator));

        $reactivateResponse->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $this->collaborator->id,
            'is_active' => 1,
        ]);

        // 5. Self-deactivation is blocked
        $selfDeactivateResponse = $this->actingAs($this->tenantAdmin)
            ->withSession(['current_business_id' => $this->business->id])
            ->post(route('users.toggleActive', $this->tenantAdmin));

        $selfDeactivateResponse->assertStatus(403);
    }

    /**
     * Test 4 (Exclusividad Superadmin intacta):
     * El Superadministrador sigue creando colaboradores exitosamente desde /superadmin/businesses/{business}/users.
     */
    public function test_superadmin_can_successfully_provision_new_collaborators_for_any_business(): void
    {
        // 1. Superadmin can view create form
        $createFormResponse = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.businesses.users.create', $this->business));

        $createFormResponse->assertStatus(200);
        $createFormResponse->assertSee('Agregar Colaborador');
        $createFormResponse->assertSee($this->business->name);

        // 2. Superadmin stores new collaborator with employee role
        $storeResponse = $this->actingAs($this->superAdmin)
            ->post(route('superadmin.businesses.users.store', $this->business), [
                'name' => 'Nuevo Cajero Creado Por Superadmin',
                'email' => 'cajero.super@puntostock.com',
                'role' => 'employee',
                'password' => 'Temporal1234!',
            ]);

        $storeResponse->assertRedirect(route('superadmin.businesses.users', $this->business));
        $this->assertDatabaseHas('users', [
            'email' => 'cajero.super@puntostock.com',
            'is_superadmin' => false,
            'must_change_password' => true,
        ]);

        $createdUser = User::where('email', 'cajero.super@puntostock.com')->firstOrFail();
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $createdUser->id,
            'role_id' => $this->employeeRole->id,
            'is_active' => 1,
        ]);

        // Verify Superadmin users view renders modal and has no native confirm()
        $usersListResponse = $this->actingAs($this->superAdmin)
            ->get(route('superadmin.businesses.users', $this->business));
        $usersListResponse->assertStatus(200);
        $usersListResponse->assertSee('id="toggleSuperadminUserModal"', false);
        $usersListResponse->assertDontSee('confirm(');
    }
}
