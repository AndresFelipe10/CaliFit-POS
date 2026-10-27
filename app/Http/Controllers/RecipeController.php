<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRecipeRequest;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
