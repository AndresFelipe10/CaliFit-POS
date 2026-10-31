<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecipeController extends Controller
{
    /**
     * Display a listing of dishes and their recipe configuration status.
     */
    public function index(): View
    {
        $dishes = Product::where('tipo_producto', 'PLATO')
            ->with(['receta.items.ingredient'])
            ->orderBy('nombre')
            ->paginate(15);

        return view('recipes.index', compact('dishes'));
    }

    /**
     * Show the form for creating a new recipe formula.
     */
    public function create(): View
    {
        $dishes = Product::where('tipo_producto', 'PLATO')
            ->orderBy('nombre')
            ->get();

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        $insumos = Product::where('tipo_producto', 'INSUMO')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('recipes.create', compact('dishes', 'categories', 'insumos'));
    }

    /**
     * Store a newly created recipe formula atomically under DB::transaction.
     */
    public function store(StoreRecipeRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request) {
            if ($request->input('dish_mode') === 'existing') {
                $product = Product::findOrFail($request->input('product_id'));
            } else {
                $sku = $request->filled('sku')
                    ? strtoupper(trim($request->sku))
                    : 'PLATO-' . strtoupper(Str::random(6));

                $product = Product::create([
                    'sku' => $sku,
                    'nombre' => trim($request->nombre),
                    'descripcion' => $request->descripcion,
                    'tipo_producto' => 'PLATO',
                    'unidad_base' => 'UNIDAD',
                    'category_id' => $request->category_id,
                    'stock' => 0.000,
                    'stock_minimo' => 5,
                    'precio_compra' => 0.00,
                    'precio_venta' => $request->precio_venta,
                    'activo' => true,
                ]);
            }

            $recipe = Recipe::firstOrCreate(
                ['producto_plato_id' => $product->id],
                [
                    'nombre' => 'Receta ' . $product->nombre,
                    'activa' => true,
                ]
            );

            // Reemplazar o asignar items
            $recipe->items()->delete();

            foreach ($request->items as $item) {
                $ing = Product::findOrFail($item['ingrediente_id']);
                RecipeItem::create([
                    'receta_id' => $recipe->id,
                    'ingrediente_id' => $ing->id,
                    'cantidad_por_porcion' => $item['cantidad_por_porcion'],
                    'unidad' => $ing->unidad_base,
                ]);
            }

            return $product;
        });

        return redirect()->route('recipes.index')
            ->with('success', "Fórmula de receta creada exitosamente para '{$product->nombre}'.");
    }

    /**
     * Show the form for configuring the recipe items of a dish.
     */
    public function edit(Product $product): View
    {
        if ($product->tipo_producto !== 'PLATO') {
            abort(404, 'Solo los productos de tipo PLATO poseen fórmulas de recetas.');
        }

        $recipe = Recipe::firstOrCreate(
            ['producto_plato_id' => $product->id],
            [
                'nombre' => 'Receta ' . $product->nombre,
                'activa' => true,
            ]
        );

        $recipe->load('items.ingredient');

        $insumos = Product::where('tipo_producto', 'INSUMO')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('recipes.edit', compact('product', 'recipe', 'insumos'));
    }

    /**
     * Update the recipe items of a dish atomically under DB::transaction.
     */
    public function update(UpdateRecipeRequest $request, Product $product): RedirectResponse
    {
        if ($product->tipo_producto !== 'PLATO') {
            abort(404, 'Solo los productos de tipo PLATO poseen fórmulas de recetas.');
        }

        DB::transaction(function () use ($request, $product) {
            $recipe = Recipe::firstOrCreate(
                ['producto_plato_id' => $product->id],
                [
                    'nombre' => 'Receta ' . $product->nombre,
                    'activa' => true,
                ]
            );

            // Eliminar fórmula anterior
            $recipe->items()->delete();

            // Insertar nuevos ingredientes
            foreach ($request->items as $item) {
                $ing = Product::findOrFail($item['ingrediente_id']);

                RecipeItem::create([
                    'receta_id' => $recipe->id,
                    'ingrediente_id' => $ing->id,
                    'cantidad_por_porcion' => $item['cantidad_por_porcion'],
                    'unidad' => $ing->unidad_base,
                ]);
            }
        });

        return redirect()->route('recipes.index')
            ->with('success', "Fórmula de receta actualizada para {$product->nombre}.");
    }
}
