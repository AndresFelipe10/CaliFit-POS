<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): View
    {
        $query = Supplier::withCount('expenses');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $suppliers = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search'));
    }

    /**
     * Store a newly created supplier in storage.
     */
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create(array_merge($request->validated(), [
            'is_active' => true,
        ]));

        return redirect()->route('suppliers.index')
            ->with('success', "Proveedor '{$supplier->name}' registrado exitosamente.");
    }

    /**
     * Display the specified supplier with expense history.
     */
    public function show(Supplier $supplier): View
    {
        $supplier->load(['expenses' => function ($q) {
            $q->latest('issue_date')->take(20);
        }]);

        return view('suppliers.show', compact('supplier'));
    }

    /**
     * Update the specified supplier in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')
            ->with('success', "Proveedor '{$supplier->name}' actualizado correctamente.");
    }

    /**
     * Toggle active/inactive status of a supplier.
     */
    public function toggleStatus(Supplier $supplier): RedirectResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);
        $statusText = $supplier->is_active ? 'activado' : 'desactivado';

        return redirect()->route('suppliers.index')
            ->with('success', "Proveedor '{$supplier->name}' {$statusText}.");
    }
}
