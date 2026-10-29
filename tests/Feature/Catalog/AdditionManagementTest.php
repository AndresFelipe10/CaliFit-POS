<?php

namespace Tests\Feature\Catalog;

use App\Models\Addition;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdditionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Product $dish1;
    protected Product $dish2;
    protected Product $aguacate;

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

        $this->dish1 = Product::create([
            'sku' => 'PLA-BOWL-01',
            'nombre' => 'Bowl Proteico de Pollo',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'stock' => 0,
            'stock_minimo' => 0,
            'precio_venta' => 18000,
            'activo' => true,
        ]);

        $this->dish2 = Product::create([
            'sku' => 'PLA-BOWL-02',
            'nombre' => 'Bowl Fit de Carne Desmechada',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'stock' => 0,
            'stock_minimo' => 0,
            'precio_venta' => 21000,
            'activo' => true,
        ]);

        $this->aguacate = Product::create([
            'sku' => 'INS-AGUACATE',
            'nombre' => 'Aguacate Hass Fresco',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 5000,
            'stock_minimo' => 500,
            'precio_venta' => 0,
            'activo' => true,
        ]);
    }

    public function test_employee_cannot_manage_additions(): void
    {
        $response = $this->actingAs($this->employee)->get(route('additions.index'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->employee)->post(route('additions.store'), [
            'nombre' => 'Extra Aguacate',
            'precio' => 3000,
            'ingredients' => [
                ['ingrediente_id' => $this->aguacate->id, 'cantidad' => 80],
            ],
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_create_addition_with_recipe_and_dish_pivots(): void
    {
        $response = $this->actingAs($this->admin)->post(route('additions.store'), [
            'nombre' => 'Aguacate adicional 80g',
            'precio' => 3000,
            'ingredients' => [
                [
                    'ingrediente_id' => $this->aguacate->id,
                    'cantidad' => 80.000,
                ],
            ],
            'dish_ids' => [$this->dish1->id, $this->dish2->id],
        ]);

        $response->assertRedirect(route('additions.index'));
        $response->assertSessionHas('success');

        $addition = Addition::where('nombre', 'Aguacate adicional 80g')->firstOrFail();
        $this->assertEquals(3000.00, (float) $addition->precio);
        $this->assertTrue($addition->activo);

        // Validar insumo en `adicion_detalle`
        $this->assertDatabaseHas('adicion_detalle', [
            'adicion_id' => $addition->id,
            'ingrediente_id' => $this->aguacate->id,
            'cantidad' => 80.000,
        ]);

        // Validar relación pivote en `producto_adicion` con los 2 platos
        $this->assertCount(2, $addition->products);
        $this->assertTrue($addition->products->contains($this->dish1->id));
        $this->assertTrue($addition->products->contains($this->dish2->id));
    }

    public function test_admin_can_update_and_toggle_addition(): void
    {
        $addition = Addition::create([
            'nombre' => 'Queso Campesino Extra',
            'precio' => 2500,
            'activo' => true,
        ]);

        // Actualizar precio
        $updateResponse = $this->actingAs($this->admin)->put(route('additions.update', $addition), [
            'nombre' => 'Queso Campesino Extra 50g',
            'precio' => 2800,
            'ingredients' => [
                ['ingrediente_id' => $this->aguacate->id, 'cantidad' => 50],
            ],
            'dish_ids' => [$this->dish1->id],
        ]);

        $updateResponse->assertRedirect(route('additions.index'));
        $this->assertDatabaseHas('adiciones', [
            'id' => $addition->id,
            'nombre' => 'Queso Campesino Extra 50g',
            'precio' => 2800.00,
        ]);

        // Toggle status
        $toggleResponse = $this->actingAs($this->admin)->patch(route('additions.toggle-status', $addition));
        $toggleResponse->assertRedirect(route('additions.index'));
        $this->assertFalse($addition->fresh()->activo);
    }

    public function test_additions_views_omit_internal_schema_and_technical_terms(): void
    {
        $responseIndex = $this->actingAs($this->admin)->get(route('additions.index'));
        $responseIndex->assertOk();
        $responseIndex->assertDontSee('adicion_detalle');
        $responseIndex->assertDontSee('producto_adicion');

        $responseCreate = $this->actingAs($this->admin)->get(route('additions.create'));
        $responseCreate->assertOk();
        $responseCreate->assertDontSee('adicion_detalle');
        $responseCreate->assertDontSee('producto_adicion');
    }
}
