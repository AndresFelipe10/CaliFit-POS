<?php

namespace Tests\Unit\Services;

use App\Models\Addition;
use App\Models\AdditionItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Services\Inventory\InventoryService;
use App\Services\Kitchen\RecipeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecipeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected RecipeService $recipeService;
    protected InventoryService $inventoryService;

    protected Product $carne;
    protected Product $arroz;
    protected Product $frijoles;
    protected Product $lentejas;
    protected Product $verduras;
    protected Product $papa;
    protected Product $platoEjecutivo200gFrijoles;
    protected Addition $adicionCarne120g;
    protected Addition $adicionPapa100g;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recipeService = new RecipeService();
        $this->inventoryService = new InventoryService();

        $catInsumos = Category::create(['name' => 'Insumos Cocina', 'is_active' => true]);
        $catPlatos = Category::create(['name' => 'Platos Fit', 'is_active' => true]);

        // Insumos base
        $this->carne = Product::create([
            'sku' => 'INS-001',
            'nombre' => 'Carne Magra',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 50000.0,
            'precio_compra' => 35.0,
            'activo' => true,
        ]);

        $this->arroz = Product::create([
            'sku' => 'INS-002',
            'nombre' => 'Arroz Cocido',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 40000.0,
            'precio_compra' => 6.0,
            'activo' => true,
        ]);

        $this->frijoles = Product::create([
            'sku' => 'INS-003',
            'nombre' => 'Frijoles Cocidos',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 30000.0,
            'precio_compra' => 10.0,
            'activo' => true,
        ]);

        $this->lentejas = Product::create([
            'sku' => 'INS-004',
            'nombre' => 'Lentejas Cocidas',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 30000.0,
            'precio_compra' => 10.0,
            'activo' => true,
        ]);

        $this->verduras = Product::create([
            'sku' => 'INS-005',
            'nombre' => 'Verduras Salteadas',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 40000.0,
            'precio_compra' => 8.0,
            'activo' => true,
        ]);

        $this->papa = Product::create([
            'sku' => 'INS-006',
            'nombre' => 'Papa Cocida',
            'tipo_producto' => 'INSUMO',
            'unidad_base' => 'GRAMO',
            'category_id' => $catInsumos->id,
            'stock' => 40000.0,
            'precio_compra' => 6.0,
            'activo' => true,
        ]);

        // Plato Fit: Ejecutivo 200g con frijoles
        $this->platoEjecutivo200gFrijoles = Product::create([
            'sku' => 'PLA-001',
            'nombre' => 'Ejecutivo 200g con frijoles',
            'tipo_producto' => 'PLATO',
            'unidad_base' => 'UNIDAD',
            'category_id' => $catPlatos->id,
            'stock' => 0.0,
            'precio_venta' => 25000.0,
            'activo' => true,
        ]);

        $receta = Recipe::create([
            'producto_plato_id' => $this->platoEjecutivo200gFrijoles->id,
            'nombre' => 'Fórmula Ejecutivo 200g con frijoles',
            'activa' => true,
        ]);

        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad_por_porcion' => 200.000,
            'unidad' => 'GRAMO',
        ]);
        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->arroz->id,
            'cantidad_por_porcion' => 62.500,
            'unidad' => 'GRAMO',
        ]);
        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->frijoles->id,
            'cantidad_por_porcion' => 23.810,
            'unidad' => 'GRAMO',
        ]);
        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->verduras->id,
            'cantidad_por_porcion' => 100.000,
            'unidad' => 'GRAMO',
        ]);
        RecipeItem::create([
            'receta_id' => $receta->id,
            'ingrediente_id' => $this->papa->id,
            'cantidad_por_porcion' => 100.000,
            'unidad' => 'GRAMO',
        ]);

        // Adición 1: Carne adicional 120g
        $this->adicionCarne120g = Addition::create([
            'nombre' => 'Carne adicional 120g',
            'precio' => 8000.0,
            'activo' => true,
        ]);
        AdditionItem::create([
            'adicion_id' => $this->adicionCarne120g->id,
            'ingrediente_id' => $this->carne->id,
            'cantidad' => 120.000,
        ]);

        // Adición 2: Papa adicional 100g
        $this->adicionPapa100g = Addition::create([
            'nombre' => 'Porción adicional de Papa 100g',
            'precio' => 3000.0,
            'activo' => true,
        ]);
        AdditionItem::create([
            'adicion_id' => $this->adicionPapa100g->id,
            'ingrediente_id' => $this->papa->id,
            'cantidad' => 100.000,
        ]);
    }

    public function test_calculate_consumption_for_single_base_dish(): void
    {
        $consumption = $this->recipeService->calculateItemConsumption(
            $this->platoEjecutivo200gFrijoles,
            1.0
        );

        $ingredients = $consumption['consolidated_ingredients'];

        $this->assertEquals(200.000, $ingredients[$this->carne->id]);
        $this->assertEquals(62.500, $ingredients[$this->arroz->id]);
        $this->assertEquals(23.810, $ingredients[$this->frijoles->id]);
        $this->assertEquals(100.000, $ingredients[$this->verduras->id]);
        $this->assertEquals(100.000, $ingredients[$this->papa->id]);
        $this->assertArrayNotHasKey($this->lentejas->id, $ingredients);
    }

    public function test_calculate_consumption_for_multiple_portions_of_base_dish(): void
    {
        // 3 platos de Ejecutivo 200g con frijoles
        $consumption = $this->recipeService->calculateItemConsumption(
            $this->platoEjecutivo200gFrijoles,
            3.0
        );

        $ingredients = $consumption['consolidated_ingredients'];

        $this->assertEquals(600.000, $ingredients[$this->carne->id]);
        $this->assertEquals(187.500, $ingredients[$this->arroz->id]);
        $this->assertEquals(71.430, $ingredients[$this->frijoles->id]);
        $this->assertEquals(300.000, $ingredients[$this->verduras->id]);
        $this->assertEquals(300.000, $ingredients[$this->papa->id]);
        $this->assertArrayNotHasKey($this->lentejas->id, $ingredients);
    }

    public function test_calculate_consumption_for_dish_with_additions(): void
    {
        // 1 plato + Carne adicional 120g + Papa adicional 100g
        $consumption = $this->recipeService->calculateItemConsumption(
            $this->platoEjecutivo200gFrijoles,
            1.0,
            [
                $this->adicionCarne120g->id => 1,
                $this->adicionPapa100g->id => 1,
            ]
        );

        $ingredients = $consumption['consolidated_ingredients'];

        // Carne: 200g base + 120g adición = 320g
        $this->assertEquals(320.000, $ingredients[$this->carne->id]);
        // Arroz: 62.5g base
        $this->assertEquals(62.500, $ingredients[$this->arroz->id]);
        // Frijoles: 23.81g base
        $this->assertEquals(23.810, $ingredients[$this->frijoles->id]);
        // Papa: 100g base + 100g adición = 200g
        $this->assertEquals(200.000, $ingredients[$this->papa->id]);
        // Verduras: 100g base
        $this->assertEquals(100.000, $ingredients[$this->verduras->id]);
    }

    public function test_calculate_consumption_for_direct_supplement(): void
    {
        $creatina = Product::create([
            'sku' => 'SUP-001',
            'nombre' => 'Creatina 300g',
            'tipo_producto' => 'SUPLEMENTO',
            'unidad_base' => 'UNIDAD',
            'stock' => 10.0,
            'precio_venta' => 140000.0,
            'activo' => true,
        ]);

        $consumption = $this->recipeService->calculateItemConsumption($creatina, 2.0);

        $this->assertEquals([$creatina->id => 2.0], $consumption['consolidated_ingredients']);
        $this->assertEquals('DIRECT', $consumption['breakdown'][0]['source']);
    }

    public function test_purchase_unit_conversions(): void
    {
        // 1 libra = 500 g (regla CaliFit)
        $this->assertEquals(500.0, $this->inventoryService->convertPurchaseUnit(1.0, 'libra'));
        $this->assertEquals(1250.0, $this->inventoryService->convertPurchaseUnit(2.5, 'lb'));

        // 1 kilo = 1000 g
        $this->assertEquals(1000.0, $this->inventoryService->convertPurchaseUnit(1.0, 'kg'));
        $this->assertEquals(2500.0, $this->inventoryService->convertPurchaseUnit(2.5, 'kilo'));

        // 1 litro = 1000 ml
        $this->assertEquals(1000.0, $this->inventoryService->convertPurchaseUnit(1.0, 'litro'));
        $this->assertEquals(1500.0, $this->inventoryService->convertPurchaseUnit(1.5, 'lt'));

        // Nominal
        $this->assertEquals(10.0, $this->inventoryService->convertPurchaseUnit(10.0, 'UNIDAD'));
    }
}
