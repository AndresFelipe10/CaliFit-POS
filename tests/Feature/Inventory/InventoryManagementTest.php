<?php

namespace Tests\Feature\Inventory;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

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

        $this->category = Category::create([
            'name' => 'Insumos Cocina',
            'slug' => 'insumos-cocina',
            'is_active' => true,
        ]);
    }

    /**
     * Test de conversión de libras a gramos: 1 lb = 500 g oficiales en SaborCaliFit.
     */
    public function test_entry_with_pound_conversion(): void
    {
        $arroz = Product::create([
            'sku' => 'INS-ARROZ',
            'nombre' => 'Arroz Integral',
            'category_id' => $this->category->id,
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'precio_compra' => 2000,
            'precio_venta' => 0,
            'stock' => 500,
            'stock_minimo' => 200,
            'activo' => true,
        ]);

        // Registrar entrada de 3 libras
        $response = $this->actingAs($this->admin)->post(route('inventory.entries.store'), [
            'product_id' => $arroz->id,
            'purchase_unit' => 'libra',
            'purchase_quantity' => 3,
            'cost_price' => 2200,
            'notes' => 'Compra semanal de arroz',
            'invoice_number' => 'FAC-9988',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $response->assertSessionHas('success');

        // 3 libras * 500 g = 1500 g. Stock anterior: 500 g -> Nuevo stock: 2000 g
        $arroz->refresh();
        $this->assertEquals(2000.0, (float) $arroz->stock);
        $this->assertEquals(2200.0, (float) $arroz->precio_compra);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $arroz->id,
            'type' => InventoryMovement::TYPE_ENTRADA_COMPRA,
            'quantity' => 1500.0,
            'previous_stock' => 500.0,
            'new_stock' => 2000.0,
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * Test de registro de merma que reduce stock bajo tipo MERMA.
     */
    public function test_waste_movement_reduces_stock(): void
    {
        $carne = Product::create([
            'sku' => 'INS-CARNE',
            'nombre' => 'Pechuga de Pollo',
            'category_id' => $this->category->id,
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'precio_compra' => 8000,
            'precio_venta' => 0,
            'stock' => 1000,
            'stock_minimo' => 300,
            'activo' => true,
        ]);

        // Registrar merma de 250 g
        $response = $this->actingAs($this->admin)->post(route('inventory.waste.store'), [
            'product_id' => $carne->id,
            'quantity' => 250,
            'reason' => 'Producto dañado en refrigeración',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $response->assertSessionHas('success');

        $carne->refresh();
        $this->assertEquals(750.0, (float) $carne->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $carne->id,
            'type' => InventoryMovement::TYPE_MERMA,
            'quantity' => 250.0,
            'previous_stock' => 1000.0,
            'new_stock' => 750.0,
            'reason' => 'Producto dañado en refrigeración',
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * Test de ajuste de inventario por conteo físico real.
     */
    public function test_physical_adjustment_sets_exact_stock(): void
    {
        $avena = Product::create([
            'sku' => 'INS-AVENA',
            'nombre' => 'Avena en Hojuelas',
            'category_id' => $this->category->id,
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'precio_compra' => 1500,
            'precio_venta' => 0,
            'stock' => 1200,
            'stock_minimo' => 400,
            'activo' => true,
        ]);

        // Conteo real arroja 1000 g (diferencia de -200 g)
        $response = $this->actingAs($this->admin)->post(route('inventory.adjustment.store'), [
            'product_id' => $avena->id,
            'real_stock' => 1000,
            'reason' => 'Conteo físico semanal de cierre',
        ]);

        $response->assertRedirect(route('inventory.index'));
        $response->assertSessionHas('success');

        $avena->refresh();
        $this->assertEquals(1000.0, (float) $avena->stock);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $avena->id,
            'type' => InventoryMovement::TYPE_AJUSTE,
            'quantity' => -200.0,
            'previous_stock' => 1200.0,
            'new_stock' => 1000.0,
            'reason' => 'Conteo físico semanal de cierre',
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * Test de clasificación determinística de alertas de inventario (Normal, Bajo Stock, Agotado).
     */
    public function test_stock_alerts_classification(): void
    {
        // 1. Producto Agotado (stock <= 0)
        Product::create([
            'sku' => 'INS-AGOTADO',
            'nombre' => 'Aguacate Hass',
            'category_id' => $this->category->id,
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'precio_compra' => 3000,
            'precio_venta' => 0,
            'stock' => 0,
            'stock_minimo' => 500,
            'activo' => true,
        ]);

        // 2. Producto Bajo Stock (stock > 0 && stock <= stock_minimo)
        Product::create([
            'sku' => 'INS-BAJO',
            'nombre' => 'Whey Protein Vainilla',
            'category_id' => $this->category->id,
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'precio_compra' => 90000,
            'precio_venta' => 130000,
            'stock' => 2,
            'stock_minimo' => 5,
            'activo' => true,
        ]);

        // 3. Producto Normal (stock > stock_minimo)
        Product::create([
            'sku' => 'INS-NORMAL',
            'nombre' => 'Aceite de Oliva Extra Virgen',
            'category_id' => $this->category->id,
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'MILILITRO',
            'precio_compra' => 25000,
            'precio_venta' => 0,
            'stock' => 2000,
            'stock_minimo' => 500,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('inventory.index'));
        $response->assertOk();

        // Verificar que la vista reciba los conteos correctos de alertas
        $response->assertViewHas('outOfStockCount', 1);
        $response->assertViewHas('lowStockCount', 1);

        $products = $response->viewData('products');
        $this->assertEquals(3, $products->count());

        $agotado = $products->firstWhere('sku', 'INS-AGOTADO');
        $this->assertEquals('agotado', $agotado->status_stock);
        $this->assertEquals(750.0, $agotado->sugerido_reposicion); // (500 * 1.5) - 0 = 750

        $bajo = $products->firstWhere('sku', 'INS-BAJO');
        $this->assertEquals('bajo', $bajo->status_stock);
        $this->assertEquals(5.5, $bajo->sugerido_reposicion); // (5 * 1.5) - 2 = 5.5

        $normal = $products->firstWhere('sku', 'INS-NORMAL');
        $this->assertEquals('normal', $normal->status_stock);
        $this->assertEquals(0, $normal->sugerido_reposicion); // max(0, (500 * 1.5) - 2000) = 0
    }
}
