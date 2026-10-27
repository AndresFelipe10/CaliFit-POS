<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of products with type filter tabs and search.
     */
    public function index(Request $request): View
    {
        $query = Product::with(['category', 'receta']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($tipo = $request->input('tipo')) {
            if (in_array($tipo, ['INSUMO', 'PREPARACION', 'PLATO', 'SUPLEMENTO'], true)) {
                $query->where('tipo_producto', $tipo);
            }
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->filled('status')) {
            $query->where('activo', $request->boolean('status'));
        }

        $products = $query->orderBy('tipo_producto')->orderBy('nombre')->paginate(15)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        // Conteos para pestañas
        $totalCount = Product::count();
        $platosCount = Product::where('tipo_producto', 'PLATO')->count();
        $suplementosCount = Product::where('tipo_producto', 'SUPLEMENTO')->count();
        $insumosCount = Product::where('tipo_producto', 'INSUMO')->count();
        $prepCount = Product::where('tipo_producto', 'PREPARACION')->count();

        return view('products.index', compact(
            'products',
            'categories',
            'totalCount',
            'platosCount',
            'suplementosCount',
            'insumosCount',
            'prepCount'
        ));
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.form', [
            'product' => new Product([
                'tipo_producto' => 'PLATO',
                'unidad_base' => 'UNIDAD',
                'stock_minimo' => 5,
                'precio_compra' => 0,
                'precio_venta' => 0,
                'activo' => true,
            ]),
            'categories' => $categories,
            'isEdit' => false,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = Product::create([
            'sku' => strtoupper(trim($request->sku)),
            'nombre' => trim($request->nombre),
            'descripcion' => $request->descripcion,
            'tipo_producto' => $request->tipo_producto,
            'unidad_base' => $request->unidad_base,
            'category_id' => $request->category_id,
            'stock' => 0.000,
            'stock_minimo' => $request->stock_minimo,
            'precio_compra' => $request->precio_compra ?: 0.00,
            'precio_venta' => $request->precio_venta,
            'activo' => true,
        ]);

        // Si es de tipo PLATO, crear automáticamente cabecera de receta
        if ($product->tipo_producto === 'PLATO') {
            Recipe::firstOrCreate(
                ['producto_plato_id' => $product->id],
                [
                    'nombre' => 'Receta ' . $product->nombre,
                    'activa' => true,
                ]
            );
        }

        return redirect()->route('products.index')
            ->with('success', "Producto '{$product->nombre}' creado exitosamente.");
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.form', [
            'product' => $product,
            'categories' => $categories,
            'isEdit' => true,
        ]);
    }

    /**
     * Update the specified product in storage (sin alterar stock físico directo).
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update([
            'sku' => strtoupper(trim($request->sku)),
            'nombre' => trim($request->nombre),
            'descripcion' => $request->descripcion,
            'tipo_producto' => $request->tipo_producto,
            'unidad_base' => $request->unidad_base,
            'category_id' => $request->category_id,
            'stock_minimo' => $request->stock_minimo,
            'precio_compra' => $request->precio_compra ?: 0.00,
            'precio_venta' => $request->precio_venta,
        ]);

        if ($product->tipo_producto === 'PLATO') {
            Recipe::firstOrCreate(
                ['producto_plato_id' => $product->id],
                [
                    'nombre' => 'Receta ' . $product->nombre,
                    'activa' => true,
                ]
            );
        }

        return redirect()->route('products.index')
            ->with('success', "Producto '{$product->nombre}' actualizado exitosamente.");
    }

    /**
     * Toggle active/inactive status of a product.
     */
    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['activo' => ! $product->activo]);
        $statusText = $product->activo ? 'activado' : 'desactivado';

        return redirect()->route('products.index')
            ->with('success', "Producto '{$product->nombre}' {$statusText}.");
    }
}
