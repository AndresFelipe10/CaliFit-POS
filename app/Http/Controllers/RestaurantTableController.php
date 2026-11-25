<?php

namespace App\Http\Controllers;

use App\Models\Addition;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RestaurantTableController extends Controller
{
    /**
     * Muestra la vista de cuadrícula de mesas de salón.
     */
    public function index(): View
    {
        $tables = RestaurantTable::orderBy('name')
            ->with(['activeOrder' => function ($query) {
                $query->with(['items.product', 'items.additions.addition', 'user']);
            }])
            ->get();

        foreach ($tables as $table) {
            if (in_array($table->status, ['occupied', 'billed'], true) && $table->activeOrder === null) {
                $hasActiveOrder = RestaurantOrder::where('table_id', $table->id)
                    ->whereIn('status', RestaurantOrder::ACTIVE_STATUSES)
                    ->exists();

                if (! $hasActiveOrder) {
                    $previousStatus = $table->status;
                    $table->update(['status' => 'available']);

                    Log::warning('Auto-reconciliación de mesa sin orden activa en Salón.', [
                        'table_id' => $table->id,
                        'table_name' => $table->name,
                        'previous_status' => $previousStatus,
                        'reconciled_status' => 'available',
                    ]);
                }
            }
        }

        $products = Product::where('activo', true)
            ->with(['adiciones' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('nombre')
            ->get();

        $adiciones = Addition::where('activo', true)->orderBy('nombre')->get();

        return view('restaurant.tables.index', compact('tables', 'products', 'adiciones'));
    }

    /**
     * Registra una nueva mesa física en el salón (Solo Administrador).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:restaurant_tables,name'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'name.required' => 'El nombre de la mesa es obligatorio.',
            'name.unique' => 'Ya existe una mesa registrada con este nombre en el salón.',
            'name.max' => 'El nombre de la mesa no puede superar 50 caracteres.',
            'capacity.integer' => 'La capacidad debe ser un número entero válido.',
            'capacity.min' => 'La capacidad mínima debe ser de al menos 1 comensal.',
            'capacity.max' => 'La capacidad máxima no puede superar 50 comensales.',
        ]);

        $table = RestaurantTable::create([
            'name' => trim($validated['name']),
            'capacity' => (int) ($validated['capacity'] ?? 4),
            'status' => 'available',
            'is_active' => true,
        ]);

        return redirect()->route('restaurant.tables.index')
            ->with('success', "Mesa '{$table->name}' registrada exitosamente en el salón.");
    }
}
