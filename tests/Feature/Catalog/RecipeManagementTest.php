<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Product $dish;
    protected Product $pechuga;
    protected Product $arroz;
    protected Product $frijoles;
    protected Product $lentejas;

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

        $this->dish = Product::create([
            'sku' => 'PLA-FIT-01',
            'nombre' => 'Almuerzo Fitness Estándar',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'stock' => 0,
            'stock_minimo' => 0,
            'precio_venta' => 20000,
            'activo' => true,
        ]);

        $this->pechuga = Product::create([
            'sku' => 'INS-PECHUGA',
            'nombre' => 'Pechuga de Pollo',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 10000,
            'stock_minimo' => 2000,
            'precio_venta' => 0,
            'activo' => true,
        ]);

        $this->arroz = Product::create([
            'sku' => 'INS-ARROZ',
            'nombre' => 'Arroz Integral',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 10000,
            'stock_minimo' => 2000,
            'precio_venta' => 0,
            'activo' => true,
        ]);

        $this->frijoles = Product::create([
            'sku' => 'INS-FRIJOL',
            'nombre' => 'Frijoles Rojos Cocidos',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 5000,
            'stock_minimo' => 1000,
            'precio_venta' => 0,
            'activo' => true,
        ]);

        $this->lentejas = Product::create([
            'sku' => 'INS-LENTEJA',
            'nombre' => 'Lentejas Pardinas',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 5000,
            'stock_minimo' => 1000,
            'precio_venta' => 0,
            'activo' => true,
        ]);
    }

    public function test_employee_cannot_manage_recipes(): void
    {
        $response = $this->actingAs($this->employee)->get(route('recipes.index'));
        $response->assertStatus(403);

        $response = $this->actingAs($this->employee)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $this->pechuga->id,
                    'cantidad_por_porcion' => 150,
                ],
            ],
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_update_dish_recipe_items(): void
    {
        $response = $this->actingAs($this->admin)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $this->pechuga->id,
                    'cantidad_por_porcion' => 180.500,
                ],
                [
                    'ingrediente_id' => $this->arroz->id,
                    'cantidad_por_porcion' => 120.000,
                ],
                [
                    'ingrediente_id' => $this->frijoles->id,
                    'cantidad_por_porcion' => 100.000,
                ],
            ],
        ]);

        $response->assertRedirect(route('recipes.index'));
        $response->assertSessionHas('success');

        $recipe = Recipe::where('producto_plato_id', $this->dish->id)->firstOrFail();
        $this->assertEquals(3, $recipe->items()->count());

        $this->assertDatabaseHas('receta_detalle', [
            'receta_id' => $recipe->id,
            'ingrediente_id' => $this->pechuga->id,
            'cantidad_por_porcion' => 180.500,
            'unidad' => 'GRAMO',
        ]);

        $this->assertDatabaseHas('receta_detalle', [
            'receta_id' => $recipe->id,
            'ingrediente_id' => $this->arroz->id,
            'cantidad_por_porcion' => 120.000,
            'unidad' => 'GRAMO',
        ]);
    }

    public function test_recipe_rejects_simultaneous_beans_and_lentils(): void
    {
        $response = $this->actingAs($this->admin)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $this->pechuga->id,
                    'cantidad_por_porcion' => 150,
                ],
                [
                    'ingrediente_id' => $this->frijoles->id, // Frijoles > 0
                    'cantidad_por_porcion' => 80,
                ],
                [
                    'ingrediente_id' => $this->lentejas->id, // Lentejas > 0 (Conflicto de exclusión mutua)
                    'cantidad_por_porcion' => 80,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items']);

        // Verificar que no se guardaron los detalles conflictivos
        $recipe = Recipe::where('producto_plato_id', $this->dish->id)->first();
        if ($recipe) {
            $this->assertEquals(0, $recipe->items()->count());
        }
    }

    public function test_recipes_index_view_omits_internal_service_references(): void
    {
        $response = $this->actingAs($this->admin)->get(route('recipes.index'));
        $response->assertOk();
        $response->assertDontSee('RecipeService');
    }
}
