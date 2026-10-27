<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdditionRequest;
use App\Http\Requests\UpdateAdditionRequest;
use App\Models\Addition;
use App\Models\AdditionItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdditionController extends Controller
{
    /**
     * Display a listing of reusable additions with price, consumed inputs, and linked dishes.
     */
    public function index(): View
    {
        $additions = Addition::with(['items.ingredient', 'products'])
            ->orderBy('nombre')
            ->paginate(15);

        return view('additions.index', compact('additions'));
    }

    /**
     * Show the form for creating a new addition.
     */
    public function create(): View
    {
        $insumos = Product::where('tipo_producto', 'INSUMO')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $dishes = Product::where('tipo_producto', 'PLATO')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('additions.form', [
            'addition' => new Addition(['precio' => 0, 'activo' => true]),
            'insumos' => $insumos,
            'dishes' => $dishes,
            'isEdit' => false,
        ]);
    }

    /**
     * Store a newly created addition and its input recipe and dish relationships atomically.
     */
    public function store(StoreAdditionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $addition = Addition::create([
                'nombre' => trim($request->nombre),
                'precio' => $request->precio,
                'activo' => true,
            ]);

            foreach ($request->ingredients as $item) {
                AdditionItem::create([
                    'adicion_id' => $addition->id,
                    'ingrediente_id' => $item['ingrediente_id'],
                    'cantidad' => $item['cantidad'],
                ]);
            }

            if (! empty($request->dish_ids)) {
                $addition->products()->sync($request->dish_ids);
            }
        });

        return redirect()->route('additions.index')
            ->with('success', "Adición '{$request->nombre}' creada exitosamente.");
    }

    /**
     * Show the form for editing the specified addition.
     */
    public function edit(Addition $addition): View
    {
        $addition->load(['items.ingredient', 'products']);

        $insumos = Product::where('tipo_producto', 'INSUMO')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        $dishes = Product::where('tipo_producto', 'PLATO')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();

        return view('additions.form', [
            'addition' => $addition,
            'insumos' => $insumos,
            'dishes' => $dishes,
            'isEdit' => true,
        ]);
    }

    /**
     * Update the specified addition and its input recipes and dish links.
     */
    public function update(UpdateAdditionRequest $request, Addition $addition): RedirectResponse
    {
        DB::transaction(function () use ($request, $addition) {
            $addition->update([
                'nombre' => trim($request->nombre),
                'precio' => $request->precio,
            ]);

            $addition->items()->delete();

            foreach ($request->ingredients as $item) {
                AdditionItem::create([
                    'adicion_id' => $addition->id,
                    'ingrediente_id' => $item['ingrediente_id'],
                    'cantidad' => $item['cantidad'],
                ]);
            }

            $addition->products()->sync($request->dish_ids ?? []);
        });

        return redirect()->route('additions.index')
            ->with('success', "Adición '{$addition->nombre}' actualizada exitosamente.");
    }

    /**
     * Toggle active/inactive status of an addition.
     */
    public function toggleStatus(Addition $addition): RedirectResponse
    {
        $addition->update(['activo' => ! $addition->activo]);
        $statusText = $addition->activo ? 'activada' : 'desactivada';

        return redirect()->route('additions.index')
            ->with('success', "Adición '{$addition->nombre}' {$statusText}.");
    }
}
