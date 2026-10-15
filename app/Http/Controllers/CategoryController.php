<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Category::class);

        $query = Category::withCount('products');

        if ($search = $request->input('search')) {
            $escaped = addcslashes(trim((string) $search), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        $categories = $query->latest()->paginate(15)->withQueryString();

        return view('categories.index', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('categories.create');
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('categories.index')
            ->with('status', "Categoría '{$category->name}' creada exitosamente.");
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('categories.edit', [
            'category' => $category,
        ]);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')
            ->with('status', "Categoría '{$category->name}' actualizada correctamente.");
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $name = $category->name;
        $category->delete();

        return redirect()->route('categories.index')
            ->with('status', "Categoría '{$name}' eliminada correctamente.");
    }
}
