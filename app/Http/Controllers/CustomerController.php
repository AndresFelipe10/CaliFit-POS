<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Search customers for POS autocomplete (min 3 chars, max 10 results).
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);

        $customers = Customer::query()
            ->where('is_active', true)
            ->where(function ($query) use ($escaped) {
                $query->whereRaw("document LIKE ? ESCAPE '!'", ["{$escaped}%"])
                      ->orWhereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                      ->orWhereRaw("phone LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
            })
            ->orderByRaw("CASE 
                WHEN document LIKE ? ESCAPE '!' THEN 1 
                ELSE 2 
            END", ["{$escaped}%"])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'document', 'phone', 'address']);

        return response()->json($customers);
    }

    /**
     * Display a listing of customers with sales count and search.
     */
    public function index(Request $request): View
    {
        $query = Customer::withCount([
            'sales' => function ($q) {
                $q->where('status', 'completed');
            }
        ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('document', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $customers = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('customers.index', compact('customers', 'search'));
    }

    /**
     * Store a newly created customer in storage with DIAN validation.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($request->validatedCustomerData());

        if ($request->wantsJson() || $request->ajax() || $request->isJson()) {
            return response()->json([
                'success' => true,
                'message' => "Cliente '{$customer->name}' registrado exitosamente.",
                'id' => $customer->id,
                'name' => $customer->name,
                'document' => $customer->document,
                'phone' => $customer->phone,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'document' => $customer->document,
                    'document_number' => $customer->document,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'address' => $customer->address,
                ],
            ], 201);
        }

        return redirect()->route('customers.index')
            ->with('success', "Cliente '{$customer->name}' registrado exitosamente.");
    }

    /**
     * Display the specified customer with purchase history.
     */
    public function show(Customer $customer): View
    {
        $customer->load([
            'sales' => function ($q) {
                $q->latest()->take(20);
            },
            'creditSales' => function ($q) {
                $q->with('creditPayments.user')->latest();
            },
            'creditPayments' => function ($q) {
                $q->with('sale', 'user')->latest();
            }
        ]);

        return view('customers.show', compact('customer'));
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validatedCustomerData());

        return redirect()->route('customers.index')
            ->with('success', "Cliente '{$customer->name}' actualizado correctamente.");
    }

    /**
     * Toggle active/inactive status of a customer.
     */
    public function toggleStatus(Customer $customer): RedirectResponse
    {
        $customer->update(['is_active' => ! $customer->is_active]);
        $statusText = $customer->is_active ? 'activado' : 'desactivado';

        return redirect()->route('customers.index')
            ->with('success', "Cliente '{$customer->name}' {$statusText}.");
    }
}
