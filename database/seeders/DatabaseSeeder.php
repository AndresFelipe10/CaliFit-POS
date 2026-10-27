<?php

namespace Database\Seeders;

use App\Models\Addition;
use App\Models\AdditionItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with demonstration data for SaborCaliFit Bochalema.
     */
    public function run(): void
    {
        // 1. Roles del sistema CaliFit POS (Arquitectura Bipartita: Administrador y Mesero)
        $roleAdmin = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            [
                'name' => 'Administrador',
                'description' => 'Dueño / Administrador con control total del negocio, equipo, catálogo, compras y reportes',
            ]
        );

        $roleEmployee = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            [
                'name' => 'Mesero',
                'description' => 'Operación del punto de venta, salón de mesas, comandas, despachos y cuadre de turno',
            ]
        );

        // 2. Usuarios del sistema SaborCaliFit (Estrictamente 3 usuarios: 2 Administradores y 1 Mesero)
        // Administrador 1: Dueño / Administrador Principal
        User::firstOrCreate(
            ['email' => 'admin@saborcalifit.com'],
            [
                'role_id' => $roleAdmin->id,
                'name' => 'Administrador 1',
                'password' => Hash::make('Admin1234#'),
                'must_change_password' => false,
                'is_active' => true,
                'terms_accepted_at' => now(),
                'terms_accepted_ip' => '127.0.0.1',
            ]
        );

        // Administrador 2: Administrador Secundario / Gerente con control total
        User::firstOrCreate(
            ['email' => 'gerente@saborcalifit.com'],
            [
                'role_id' => $roleAdmin->id,
                'name' => 'Administrador 2',
                'password' => Hash::make('Admin1234#'),
                'must_change_password' => false,
                'is_active' => true,
                'terms_accepted_at' => now(),
                'terms_accepted_ip' => '127.0.0.1',
            ]
        );

        // Usuario Operativo: Mesero
        User::firstOrCreate(
            ['email' => 'mesero@saborcalifit.com'],
            [
                'role_id' => $roleEmployee->id,
                'name' => 'Mesero',
                'password' => Hash::make('Admin1234#'),
                'must_change_password' => false,
                'is_active' => true,
                'terms_accepted_at' => now(),
                'terms_accepted_ip' => '127.0.0.1',
            ]
        );

        // 3. Categorías de producto
        $catInsumos = Category::firstOrCreate(
            ['name' => 'Insumos de Cocina'],
            [
                'description' => 'Materia prima e ingredientes pesados en gramos para preparación',
                'is_active' => true,
            ]
        );

        $catPlatos = Category::firstOrCreate(
            ['name' => 'Almuerzos y Platos Fit'],
            [
                'description' => 'Platos saludables preparados con recetas y porciones exactas',
                'is_active' => true,
            ]
        );

        $catSuplementos = Category::firstOrCreate(
            ['name' => 'Suplementación Deportiva'],
            [
                'description' => 'Proteínas, creatinas, aminoácidos y accesorios deportivos de venta directa',
                'is_active' => true,
            ]
        );

        // 4. Insumos Base en gramos (SaborCaliFit)
        $insumoCarne = Product::firstOrCreate(
            ['sku' => 'INS-001'],
            [
                'nombre' => 'Carne de Res / Pollo Magra',
                'descripcion' => 'Proteína magra para almuerzos fit pesada en gramos',
                'tipo_producto' => 'INSUMO',
                'unidad_base' => 'GRAMO',
                'category_id' => $catInsumos->id,
                'stock' => 50000.000, // 50 kg
                'stock_minimo' => 5000.000,
                'precio_compra' => 35.00, // $35 por gramo ($17,500/libra)
                'precio_venta' => 0.00,
                'activo' => true,
            ]
        );

        $insumoArroz = Product::firstOrCreate(
            ['sku' => 'INS-002'],
            [
                'nombre' => 'Arroz Blanco / Integral Cocido',
                'descripcion' => 'Carbohidrato complejo pesado en gramos',
                'tipo_producto' => 'INSUMO',
                'unidad_base' => 'GRAMO',
                'category_id' => $catInsumos->id,
                'stock' => 40000.000, // 40 kg
                'stock_minimo' => 4000.000,
                'precio_compra' => 6.00,
                'precio_venta' => 0.00,
                'activo' => true,
            ]
        );

        $insumoFrijoles = Product::firstOrCreate(
            ['sku' => 'INS-003'],
            [
                'nombre' => 'Frijoles Cocidos',
                'descripcion' => 'Grano cocido tradicional fit',
                'tipo_producto' => 'INSUMO',
                'unidad_base' => 'GRAMO',
                'category_id' => $catInsumos->id,
                'stock' => 30000.000, // 30 kg
                'stock_minimo' => 3000.000,
                'precio_compra' => 10.00,
                'precio_venta' => 0.00,
                'activo' => true,
            ]
        );

        $insumoLentejas = Product::firstOrCreate(
            ['sku' => 'INS-004'],
            [
                'nombre' => 'Lentejas Cocidas',
                'descripcion' => 'Grano cocido rico en proteína y hierro',
                'tipo_producto' => 'INSUMO',
                'unidad_base' => 'GRAMO',
                'category_id' => $catInsumos->id,
                'stock' => 30000.000, // 30 kg
                'stock_minimo' => 3000.000,
                'precio_compra' => 10.00,
                'precio_venta' => 0.00,
                'activo' => true,
            ]
        );

        $insumoVerduras = Product::firstOrCreate(
            ['sku' => 'INS-005'],
            [
                'nombre' => 'Verduras Salteadas',
                'descripcion' => 'Mix de brócoli, zanahoria, calabacín y habichuela',
                'tipo_producto' => 'INSUMO',
                'unidad_base' => 'GRAMO',
                'category_id' => $catInsumos->id,
                'stock' => 40000.000, // 40 kg
                'stock_minimo' => 4000.000,
                'precio_compra' => 8.00,
                'precio_venta' => 0.00,
                'activo' => true,
            ]
        );

        $insumoPapa = Product::firstOrCreate(
            ['sku' => 'INS-006'],
            [
                'nombre' => 'Papa Cocida / Horneada',
                'descripcion' => 'Papa al vapor o al horno sin grasa añadida',
                'tipo_producto' => 'INSUMO',
                'unidad_base' => 'GRAMO',
                'category_id' => $catInsumos->id,
                'stock' => 40000.000, // 40 kg
                'stock_minimo' => 4000.000,
                'precio_compra' => 6.00,
                'precio_venta' => 0.00,
                'activo' => true,
            ]
        );

        $insumoProteina = Product::firstOrCreate(
            ['sku' => 'INS-007'],
            [
                'nombre' => 'Proteína Whey en Polvo (Insumo Cocina)',
                'descripcion' => 'Aislado de suero para batidos y adiciones proteicas',
                'tipo_producto' => 'INSUMO',
                'unidad_base' => 'GRAMO',
                'category_id' => $catInsumos->id,
                'stock' => 15000.000, // 15 kg
                'stock_minimo' => 2000.000,
                'precio_compra' => 120.00,
                'precio_venta' => 0.00,
                'activo' => true,
            ]
        );

        // Suplementos para venta directa
        Product::firstOrCreate(
            ['sku' => 'SUP-001'],
            [
                'nombre' => 'Creatina Monohidratada Creapure 300g',
                'descripcion' => 'Creatina de máxima pureza para rendimiento de fuerza',
                'tipo_producto' => 'SUPLEMENTO',
                'unidad_base' => 'UNIDAD',
                'category_id' => $catSuplementos->id,
                'stock' => 25.000,
                'stock_minimo' => 5.000,
                'precio_compra' => 95000.00,
                'precio_venta' => 140000.00,
                'activo' => true,
            ]
        );

        Product::firstOrCreate(
            ['sku' => 'SUP-002'],
            [
                'nombre' => 'Proteína 100% Whey Isolate 2lb',
                'descripcion' => 'Proteína de suero microfiltrada baja en carbohidratos',
                'tipo_producto' => 'SUPLEMENTO',
                'unidad_base' => 'UNIDAD',
                'category_id' => $catSuplementos->id,
                'stock' => 20.000,
                'stock_minimo' => 4.000,
                'precio_compra' => 130000.00,
                'precio_venta' => 185000.00,
                'activo' => true,
            ]
        );

        // 5. Los 4 Platos Principales Fit
        // 5.1 Ejercicio 200g con frijoles
        $plato1 = Product::firstOrCreate(
            ['sku' => 'PLA-001'],
            [
                'nombre' => 'Ejercicio 200g con frijoles',
                'descripcion' => 'Almuerzo fit alto en proteína: 200g carne, 62.5g arroz, 23.81g frijoles, verduras y papa',
                'tipo_producto' => 'PLATO',
                'unidad_base' => 'UNIDAD',
                'category_id' => $catPlatos->id,
                'stock' => 0.000,
                'stock_minimo' => 0.000,
                'precio_compra' => 11000.00,
                'precio_venta' => 25000.00,
                'activo' => true,
            ]
        );

        $receta1 = Recipe::firstOrCreate(
            ['producto_plato_id' => $plato1->id],
            [
                'nombre' => 'Fórmula Ejercicio 200g con frijoles',
                'activa' => true,
            ]
        );

        $this->syncRecipeItems($receta1, [
            $insumoCarne->id => 200.000,
            $insumoArroz->id => 62.500,
            $insumoFrijoles->id => 23.810,
            $insumoVerduras->id => 100.000,
            $insumoPapa->id => 100.000,
        ]);

        // 5.2 Ejercicio 120g con frijoles
        $plato2 = Product::firstOrCreate(
            ['sku' => 'PLA-002'],
            [
                'nombre' => 'Ejercicio 120g con frijoles',
                'descripcion' => 'Almuerzo fit porción estándar: 120g carne, 62.5g arroz, 23.81g frijoles, verduras y papa',
                'tipo_producto' => 'PLATO',
                'unidad_base' => 'UNIDAD',
                'category_id' => $catPlatos->id,
                'stock' => 0.000,
                'stock_minimo' => 0.000,
                'precio_compra' => 8500.00,
                'precio_venta' => 20000.00,
                'activo' => true,
            ]
        );

        $receta2 = Recipe::firstOrCreate(
            ['producto_plato_id' => $plato2->id],
            [
                'nombre' => 'Fórmula Ejercicio 120g con frijoles',
                'activa' => true,
            ]
        );

        $this->syncRecipeItems($receta2, [
            $insumoCarne->id => 120.000,
            $insumoArroz->id => 62.500,
            $insumoFrijoles->id => 23.810,
            $insumoVerduras->id => 100.000,
            $insumoPapa->id => 100.000,
        ]);

        // 5.3 Ejercicio 200g con lentejas
        $plato3 = Product::firstOrCreate(
            ['sku' => 'PLA-003'],
            [
                'nombre' => 'Ejercicio 200g con lentejas',
                'descripcion' => 'Almuerzo fit alto en proteína: 200g carne, 62.5g arroz, 14.29g lentejas, verduras y papa',
                'tipo_producto' => 'PLATO',
                'unidad_base' => 'UNIDAD',
                'category_id' => $catPlatos->id,
                'stock' => 0.000,
                'stock_minimo' => 0.000,
                'precio_compra' => 11000.00,
                'precio_venta' => 25000.00,
                'activo' => true,
            ]
        );

        $receta3 = Recipe::firstOrCreate(
            ['producto_plato_id' => $plato3->id],
            [
                'nombre' => 'Fórmula Ejercicio 200g con lentejas',
                'activa' => true,
            ]
        );

        $this->syncRecipeItems($receta3, [
            $insumoCarne->id => 200.000,
            $insumoArroz->id => 62.500,
            $insumoLentejas->id => 14.290,
            $insumoVerduras->id => 100.000,
            $insumoPapa->id => 100.000,
        ]);

        // 5.4 Ejercicio 120g con lentejas
        $plato4 = Product::firstOrCreate(
            ['sku' => 'PLA-004'],
            [
                'nombre' => 'Ejercicio 120g con lentejas',
                'descripcion' => 'Almuerzo fit porción estándar: 120g carne, 62.5g arroz, 14.29g lentejas, verduras y papa',
                'tipo_producto' => 'PLATO',
                'unidad_base' => 'UNIDAD',
                'category_id' => $catPlatos->id,
                'stock' => 0.000,
                'stock_minimo' => 0.000,
                'precio_compra' => 8500.00,
                'precio_venta' => 20000.00,
                'activo' => true,
            ]
        );

        $receta4 = Recipe::firstOrCreate(
            ['producto_plato_id' => $plato4->id],
            [
                'nombre' => 'Fórmula Ejercicio 120g con lentejas',
                'activa' => true,
            ]
        );

        $this->syncRecipeItems($receta4, [
            $insumoCarne->id => 120.000,
            $insumoArroz->id => 62.500,
            $insumoLentejas->id => 14.290,
            $insumoVerduras->id => 100.000,
            $insumoPapa->id => 100.000,
        ]);

        // 6. Las 7 Adiciones Reutilizables con Precio y Receta de Insumos
        $adicionesData = [
            [
                'nombre' => 'Carne adicional 120g',
                'precio' => 8000.00,
                'ingrediente_id' => $insumoCarne->id,
                'cantidad' => 120.000,
            ],
            [
                'nombre' => 'Carne adicional 200g',
                'precio' => 12000.00,
                'ingrediente_id' => $insumoCarne->id,
                'cantidad' => 200.000,
            ],
            [
                'nombre' => 'Porción adicional de Arroz 62.5g',
                'precio' => 3000.00,
                'ingrediente_id' => $insumoArroz->id,
                'cantidad' => 62.500,
            ],
            [
                'nombre' => 'Porción adicional de Frijoles 23.81g',
                'precio' => 3500.00,
                'ingrediente_id' => $insumoFrijoles->id,
                'cantidad' => 23.810,
            ],
            [
                'nombre' => 'Porción adicional de Lentejas 14.29g',
                'precio' => 3500.00,
                'ingrediente_id' => $insumoLentejas->id,
                'cantidad' => 14.290,
            ],
            [
                'nombre' => 'Porción adicional de Papa 100g',
                'precio' => 3000.00,
                'ingrediente_id' => $insumoPapa->id,
                'cantidad' => 100.000,
            ],
            [
                'nombre' => 'Scoop adicional de Proteína Whey 30g',
                'precio' => 6000.00,
                'ingrediente_id' => $insumoProteina->id,
                'cantidad' => 30.000,
            ],
        ];

        $additionIds = [];
        foreach ($adicionesData as $item) {
            $adicion = Addition::firstOrCreate(
                ['nombre' => $item['nombre']],
                [
                    'precio' => $item['precio'],
                    'activo' => true,
                ]
            );

            AdditionItem::firstOrCreate(
                [
                    'adicion_id' => $adicion->id,
                    'ingrediente_id' => $item['ingrediente_id'],
                ],
                [
                    'cantidad' => $item['cantidad'],
                ]
            );

            $additionIds[] = $adicion->id;
        }

        // Vincular las 7 adiciones a los 4 platos principales fit
        $platos = [$plato1, $plato2, $plato3, $plato4];
        foreach ($platos as $plato) {
            $plato->adiciones()->syncWithoutDetaching($additionIds);
        }

        // 7. Mesas de Salón (Mesa 1 a Mesa 8)
        $mesasData = [
            ['name' => 'Mesa 1', 'capacity' => 4],
            ['name' => 'Mesa 2', 'capacity' => 4],
            ['name' => 'Mesa 3', 'capacity' => 2],
            ['name' => 'Mesa 4', 'capacity' => 2],
            ['name' => 'Mesa 5', 'capacity' => 6],
            ['name' => 'Mesa 6', 'capacity' => 6],
            ['name' => 'Mesa 7', 'capacity' => 4],
            ['name' => 'Mesa 8', 'capacity' => 4],
        ];

        foreach ($mesasData as $mesa) {
            RestaurantTable::firstOrCreate(
                ['name' => $mesa['name']],
                [
                    'capacity' => $mesa['capacity'],
                    'status' => 'available',
                    'is_active' => true,
                ]
            );
        }

        // 8. Clientes demo
        Customer::firstOrCreate(
            ['document' => '222222222222'],
            [
                'name' => 'Consumidor Final',
                'email' => 'consumidor@saborcalifit.com',
                'phone' => '3000000000',
                'address' => 'Punto de Venta Bochalema',
                'is_active' => true,
            ]
        );

        Customer::firstOrCreate(
            ['document' => '1144123456'],
            [
                'name' => 'Carlos Andrés Rodríguez',
                'email' => 'carlos.atleta@gmail.com',
                'phone' => '3157778899',
                'address' => 'Cra 109 # 45-12, Bochalema, Cali',
                'is_active' => true,
            ]
        );

        // 9. Proveedores demo
        Supplier::firstOrCreate(
            ['nit' => '900555444-1'],
            [
                'name' => 'Distribuidora Carnes y Granos del Valle SAS',
                'contact_name' => 'Mateo Gómez',
                'email' => 'ventas@carnesygranos.com',
                'phone' => '3128889900',
                'address' => 'Zona Industrial Acopi, Yumbo',
                'is_active' => true,
            ]
        );
    }

    /**
     * Sincroniza ingredientes en una receta.
     */
    private function syncRecipeItems(Recipe $recipe, array $ingredientes): void
    {
        foreach ($ingredientes as $ingredienteId => $cantidad) {
            RecipeItem::firstOrCreate(
                [
                    'receta_id' => $recipe->id,
                    'ingrediente_id' => $ingredienteId,
                ],
                [
                    'cantidad_por_porcion' => $cantidad,
                    'unidad' => 'GRAMO',
                ]
            );
        }
    }
}
