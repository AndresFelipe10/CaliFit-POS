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
        $response->assertSee('Crear Receta');
    }

    public function test_admin_can_view_create_recipe_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('recipes.create'));
        $response->assertOk();
        $response->assertSee('Nueva Fórmula de Receta por Porción');
        $response->assertSee('Almuerzo Fitness Estándar');
    }

    public function test_admin_can_create_recipe_for_existing_dish(): void
    {
        $response = $this->actingAs($this->admin)->post(route('recipes.store'), [
            'dish_mode' => 'existing',
            'product_id' => $this->dish->id,
            'items' => [
                [
                    'ingrediente_id' => $this->pechuga->id,
                    'cantidad_por_porcion' => 180,
                ],
                [
                    'ingrediente_id' => $this->arroz->id,
                    'cantidad_por_porcion' => 120,
                ],
            ],
        ]);

        $response->assertRedirect(route('recipes.index'));
        $this->assertDatabaseHas('recetas', ['producto_plato_id' => $this->dish->id]);
        $recipe = Recipe::where('producto_plato_id', $this->dish->id)->first();
        $this->assertCount(2, $recipe->items);
    }

    public function test_admin_can_create_recipe_with_new_dish(): void
    {
        $category = \App\Models\Category::create([
            'name' => 'Platos Proteicos',
            'slug' => 'platos-proteicos',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('recipes.store'), [
            'dish_mode' => 'new',
            'nombre' => 'Bowl Fit Teriyaki',
            'sku' => 'PLATO-TERI01',
            'precio_venta' => 25000,
            'category_id' => $category->id,
            'items' => [
                [
                    'ingrediente_id' => $this->pechuga->id,
                    'cantidad_por_porcion' => 200,
                ],
            ],
        ]);

        $response->assertRedirect(route('recipes.index'));
        $newDish = Product::where('sku', 'PLATO-TERI01')->first();
        $this->assertNotNull($newDish);
        $this->assertEquals('Bowl Fit Teriyaki', $newDish->nombre);
        $this->assertDatabaseHas('recetas', ['producto_plato_id' => $newDish->id]);
    }

    /**
     * 1. Crear receta con ingrediente en UNIDAD + insumos en GRAMO.
     */
    public function test_admin_can_create_recipe_with_unit_ingredient_and_gram_supplies(): void
    {
        $banano = Product::create([
            'sku' => 'SUP-BANANO-01',
            'nombre' => 'Banano',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock' => 50,
            'stock_minimo' => 10,
            'precio_venta' => 1500,
            'activo' => true,
        ]);

        $proteina = Product::create([
            'sku' => 'INS-WHEY-01',
            'nombre' => 'Proteína Whey Vainilla',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 5000,
            'stock_minimo' => 1000,
            'precio_venta' => 0,
            'activo' => true,
        ]);

        $category = \App\Models\Category::create([
            'name' => 'Batidos y Bebidas',
            'slug' => 'batidos-bebidas',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('recipes.store'), [
            'dish_mode' => 'new',
            'nombre' => 'Batido de Banano Proteico',
            'sku' => 'PLA-BAT-BAN01',
            'precio_venta' => 12000,
            'category_id' => $category->id,
            'items' => [
                [
                    'ingrediente_id' => $banano->id,
                    'cantidad_por_porcion' => 1,
                ],
                [
                    'ingrediente_id' => $proteina->id,
                    'cantidad_por_porcion' => 30.5,
                ],
            ],
        ]);

        $response->assertRedirect(route('recipes.index'));
        $response->assertSessionHas('success');

        $batido = Product::where('sku', 'PLA-BAT-BAN01')->firstOrFail();
        $recipe = Recipe::where('producto_plato_id', $batido->id)->firstOrFail();

        $this->assertDatabaseHas('receta_detalle', [
            'receta_id' => $recipe->id,
            'ingrediente_id' => $banano->id,
            'cantidad_por_porcion' => 1,
            'unidad' => 'UNIDAD',
        ]);

        $this->assertDatabaseHas('receta_detalle', [
            'receta_id' => $recipe->id,
            'ingrediente_id' => $proteina->id,
            'cantidad_por_porcion' => 30.5,
            'unidad' => 'GRAMO',
        ]);
    }

    /**
     * 2. Editar receta existente agregando ingrediente en UNIDAD.
     */
    public function test_admin_can_update_recipe_adding_unit_ingredient(): void
    {
        $huevo = Product::create([
            'sku' => 'INS-HUEVO-01',
            'nombre' => 'Huevo Cocido',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'UNIDAD',
            'stock' => 100,
            'stock_minimo' => 20,
            'precio_venta' => 0,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $this->pechuga->id,
                    'cantidad_por_porcion' => 150,
                ],
                [
                    'ingrediente_id' => $huevo->id,
                    'cantidad_por_porcion' => 2,
                ],
            ],
        ]);

        $response->assertRedirect(route('recipes.index'));
        $response->assertSessionHas('success');

        $recipe = Recipe::where('producto_plato_id', $this->dish->id)->firstOrFail();

        $this->assertDatabaseHas('receta_detalle', [
            'receta_id' => $recipe->id,
            'ingrediente_id' => $huevo->id,
            'cantidad_por_porcion' => 2,
            'unidad' => 'UNIDAD',
        ]);
    }

    /**
     * 3. Rechazo de UNIDAD con decimales o menor a 1.
     */
    public function test_recipe_rejects_unit_ingredient_with_decimals_or_less_than_one(): void
    {
        $manzana = Product::create([
            'sku' => 'INS-MANZANA-01',
            'nombre' => 'Manzana Verde',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'UNIDAD',
            'stock' => 40,
            'stock_minimo' => 5,
            'precio_venta' => 0,
            'activo' => true,
        ]);

        // Intento con 0.5 unidades
        $responseDecimal = $this->actingAs($this->admin)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $manzana->id,
                    'cantidad_por_porcion' => 0.5,
                ],
            ],
        ]);

        $responseDecimal->assertSessionHasErrors(['items.0.cantidad_por_porcion']);
        $this->assertStringContainsString(
            "se maneja por UNIDAD y requiere una cantidad entera mínima de 1",
            session('errors')->first('items.0.cantidad_por_porcion')
        );

        // Intento con 1.5 unidades
        $responseDecimal2 = $this->actingAs($this->admin)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $manzana->id,
                    'cantidad_por_porcion' => 1.5,
                ],
            ],
        ]);

        $responseDecimal2->assertSessionHasErrors(['items.0.cantidad_por_porcion']);

        // Intento con 0 unidades
        $responseZero = $this->actingAs($this->admin)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $manzana->id,
                    'cantidad_por_porcion' => 0,
                ],
            ],
        ]);

        $responseZero->assertSessionHasErrors(['items.0.cantidad_por_porcion']);
    }

    /**
     * 4. Rechazo estricto de un PLATO como ingrediente (en store y en update).
     */
    public function test_recipe_rejects_dish_as_ingredient_in_store_and_update(): void
    {
        $platoIngrediente = Product::create([
            'sku' => 'PLA-ENSALADA-01',
            'nombre' => 'Ensalada César',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'stock' => 0,
            'stock_minimo' => 0,
            'precio_venta' => 18000,
            'activo' => true,
        ]);

        // Rechazo en StoreRecipeRequest
        $responseStore = $this->actingAs($this->admin)->post(route('recipes.store'), [
            'dish_mode' => 'existing',
            'product_id' => $this->dish->id,
            'items' => [
                [
                    'ingrediente_id' => $platoIngrediente->id,
                    'cantidad_por_porcion' => 1,
                ],
            ],
        ]);

        $responseStore->assertSessionHasErrors(['items.0.ingrediente_id']);
        $this->assertStringContainsString(
            "es un plato de carta y no puede ser utilizado como ingrediente de una receta",
            session('errors')->first('items.0.ingrediente_id')
        );

        // Rechazo en UpdateRecipeRequest
        $responseUpdate = $this->actingAs($this->admin)->put(route('recipes.update', $this->dish), [
            'items' => [
                [
                    'ingrediente_id' => $platoIngrediente->id,
                    'cantidad_por_porcion' => 1,
                ],
            ],
        ]);

        $responseUpdate->assertSessionHasErrors(['items.0.ingrediente_id']);
        $this->assertStringContainsString(
            "es un plato de carta y no puede ser utilizado como ingrediente de una receta",
            session('errors')->first('items.0.ingrediente_id')
        );
    }
}
