<?php

namespace App\Http\Controllers;

use App\Models\Addition;
use App\Models\Product;
use App\Models\RestaurantTable;
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

        $products = Product::where('activo', true)
            ->with(['adiciones' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('nombre')
            ->get();

        $adiciones = Addition::where('activo', true)->orderBy('nombre')->get();

        return view('restaurant.tables.index', compact('tables', 'products', 'adiciones'));
    }
}
