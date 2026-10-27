<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $employee;
    protected Category $category;

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

        $this->category = Category::create([
            'name' => 'Suplementación Deportiva',
            'slug' => 'suplementacion-deportiva',
            'is_active' => true,
        ]);
    }

    public function test_employee_cannot_manage_products(): void
    {
        // Empleado intenta ver catálogo administrativo
        $response = $this->actingAs($this->employee)->get(route('products.index'));
        $response->assertStatus(403);

        // Empleado intenta crear producto
        $response = $this->actingAs($this->employee)->post(route('products.store'), [
            'nombre' => 'Proteína Whey 2lb',
            'sku' => 'SUP-WHEY-01',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock_minimo' => 2,
            'precio_venta' => 150000,
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_create_supplement_and_base_dish(): void
    {
        // 1. Crear Suplemento Deportivo
        $supplementResponse = $this->actingAs($this->admin)->post(route('products.store'), [
            'nombre' => 'Proteína Whey Isolate 2lb',
            'sku' => 'SUP-WHEY-ISO',
            'descripcion' => 'Aislado de suero de leche de rápida absorción',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->category->id,
            'stock_minimo' => 5,
            'precio_compra' => 110000,
            'precio_venta' => 165000,
        ]);

        $supplementResponse->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'sku' => 'SUP-WHEY-ISO',
            'nombre' => 'Proteína Whey Isolate 2lb',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'precio_venta' => 165000.00,
            'stock' => 0.000,
            'activo' => true,
        ]);

        // 2. Crear Plato Fitness (Carta de Cocina)
        $dishResponse = $this->actingAs($this->admin)->post(route('products.store'), [
            'nombre' => 'Bowl Proteico de Pollo 150g',
            'sku' => 'PLA-BOWL-POLLO',
            'descripcion' => 'Pechuga a la plancha, arroz integral y vegetales',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->category->id,
            'stock_minimo' => 10,
            'precio_compra' => 8500,
            'precio_venta' => 18900,
        ]);

        $dishResponse->assertRedirect(route('products.index'));
        $dish = Product::where('sku', 'PLA-BOWL-POLLO')->firstOrFail();

        $this->assertEquals('PLATO', $dish->tipo_producto);
        $this->assertEquals(18900.00, (float) $dish->precio_venta);

        // Validar que se creó automáticamente la cabecera en `recetas`
        $this->assertDatabaseHas('recetas', [
            'producto_plato_id' => $dish->id,
            'activa' => true,
        ]);
    }

    public function test_sku_must_be_unique(): void
    {
        Product::create([
            'sku' => 'SUP-CREA-100',
            'nombre' => 'Creatina Creapure 300g',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock' => 0,
            'stock_minimo' => 2,
            'precio_compra' => 70000,
            'precio_venta' => 120000,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('products.store'), [
            'sku' => 'SUP-CREA-100', // Repetido
            'nombre' => 'Otra Creatina 300g',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock_minimo' => 2,
            'precio_venta' => 120000,
        ]);

        $response->assertSessionHasErrors(['sku']);
        $this->assertEquals(1, Product::where('sku', 'SUP-CREA-100')->count());
    }

    public function test_admin_can_update_product_and_toggle_status(): void
    {
        $product = Product::create([
            'sku' => 'INS-AVENA-01',
            'nombre' => 'Avena en Hojuelas',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock' => 5000,
            'stock_minimo' => 1000,
            'precio_compra' => 4.50,
            'precio_venta' => 0.00,
            'activo' => true,
        ]);

        // Actualizar datos
        $updateResponse = $this->actingAs($this->admin)->put(route('products.update', $product), [
            'sku' => 'INS-AVENA-01',
            'nombre' => 'Avena Integral en Hojuelas',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'stock_minimo' => 1200,
            'precio_compra' => 5.00,
            'precio_venta' => 0.00,
        ]);

        $updateResponse->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'nombre' => 'Avena Integral en Hojuelas',
            'stock' => 5000.000, // Stock directo inalterado
        ]);

        // Toggle Status
        $toggleResponse = $this->actingAs($this->admin)->patch(route('products.toggle-status', $product));
        $toggleResponse->assertRedirect(route('products.index'));

        $this->assertFalse($product->fresh()->activo);
    }

    public function test_admin_can_create_product_with_formatted_cop_currency_input(): void
    {
        // Enviar precios con formato COP (puntos de miles y decimales opcionales)
        $response = $this->actingAs($this->admin)->post(route('products.store'), [
            'nombre' => 'Creatina Creapure 300g',
            'sku' => 'SUP-CREA-300',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $this->category->id,
            'stock_minimo' => 5,
            'precio_compra' => '95.500',
            'precio_venta' => '140.000',
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseHas('products', [
            'sku' => 'SUP-CREA-300',
            'precio_compra' => 95500.00,
            'precio_venta' => 140000.00,
        ]);
    }
}

