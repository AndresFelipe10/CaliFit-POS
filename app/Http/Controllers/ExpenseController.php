<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Http\Requests\MarkExpenseAsPaidRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseController extends Controller
{
    /**
     * Display a listing of expenses with financial metrics and filters.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Expense::class);

        $nowBogota = Carbon::now('America/Bogota');
        $startOfMonth = $nowBogota->copy()->startOfMonth()->toDateString();
        $endOfMonth = $nowBogota->copy()->endOfMonth()->toDateString();

        // Query base para listado
        $query = Expense::with(['supplier', 'creator'])->orderByDesc('issue_date')->orderByDesc('id');

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            if ($status === 'overdue') {
                $query->where('status', 'pending')
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $nowBogota->toDateString());
            } else {
                $query->where('status', $status);
            }
        }

        if ($supplierId = $request->input('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('issue_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('issue_date', '<=', $dateTo);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $expenses = $query->paginate(15)->withQueryString();

        // Métricas superiores (Calculadas sobre la totalidad en el mes actual)
        $monthExpenses = Expense::whereBetween('issue_date', [$startOfMonth, $endOfMonth])->get();
        $totalMonthExpenses = (float) $monthExpenses->sum('amount');
        $totalMonthPaid = (float) $monthExpenses->where('status', 'paid')->sum('amount');

        // Pendientes y vencidos globales
        $totalPending = (float) Expense::where('status', 'pending')->sum('amount');
        $overdueExpenses = Expense::where('status', 'pending')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $nowBogota->toDateString())
            ->get();
        $overdueCount = $overdueExpenses->count();
        $overdueAmount = (float) $overdueExpenses->sum('amount');

        $suppliers = Supplier::orderBy('name')->get();
        $categories = ExpenseCategory::cases();

        return view('expenses.index', compact(
            'expenses',
            'suppliers',
            'categories',
            'totalMonthExpenses',
            'totalMonthPaid',
            'totalPending',
            'overdueCount',
            'overdueAmount'
        ));
    }

    /**
     * Store a newly created expense with private attachment storage.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        Gate::authorize('create', Expense::class);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $safeExt = $file->extension() ?: 'bin';
            $filename = Str::random(40) . '.' . $safeExt;
            $path = $file->storeAs('private/expenses', $filename, 'local');
            $attachmentPath = $path;
        }

        $paidAt = $request->status === 'paid' ? ($request->paid_at ?: now()->toDateString()) : null;
        $paymentMethod = $request->status === 'paid' ? $request->payment_method : null;

        Expense::create([
            'created_by' => auth()->id(),
            'supplier_id' => $request->supplier_id,
            'invoice_number' => $request->invoice_number,
            'issue_date' => $request->issue_date,
            'due_date' => $request->due_date,
            'category' => $request->category,
            'description' => $request->description,
            'amount' => $request->amount,
            'status' => $request->status,
            'paid_at' => $paidAt,
            'payment_method' => $paymentMethod,
            'attachment_path' => $attachmentPath,
        ]);

        $statusLabel = $request->status === 'paid' ? 'Pagado' : 'Pendiente';
        $amountStr = number_format((float) $request->amount, 0, ',', '.');

        return redirect()->route('expenses.index')
            ->with('success', "Gasto / Factura registrado exitosamente por \${$amountStr} (Estado: {$statusLabel}).");
    }

    /**
     * Display the specified expense.
     */
    public function show(Expense $expense): View
    {
        Gate::authorize('view', $expense);

        $expense->load(['supplier', 'creator']);

        return view('expenses.show', compact('expense'));
    }

    /**
     * Update the specified expense.
     */
    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('update', $expense);

        $attachmentPath = $expense->attachment_path;
        if ($request->hasFile('attachment')) {
            if ($expense->attachment_path && Storage::disk('local')->exists($expense->attachment_path)) {
                Storage::disk('local')->delete($expense->attachment_path);
            }

            $file = $request->file('attachment');
            $safeExt = $file->extension() ?: 'bin';
            $filename = Str::random(40) . '.' . $safeExt;
            $attachmentPath = $file->storeAs('private/expenses', $filename, 'local');
        }

        $paidAt = $request->status === 'paid' ? ($request->paid_at ?: $expense->paid_at ?: now()->toDateString()) : null;
        $paymentMethod = $request->status === 'paid' ? $request->payment_method : null;

        $expense->update([
            'supplier_id' => $request->supplier_id,
            'invoice_number' => $request->invoice_number,
            'issue_date' => $request->issue_date,
            'due_date' => $request->due_date,
            'category' => $request->category,
            'description' => $request->description,
            'amount' => $request->amount,
            'status' => $request->status,
            'paid_at' => $paidAt,
            'payment_method' => $paymentMethod,
            'attachment_path' => $attachmentPath,
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto actualizado correctamente.');
    }

    /**
     * Mark the expense as paid.
     */
    public function markAsPaid(MarkExpenseAsPaidRequest $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('pay', $expense);

        $expense->update([
            'status' => 'paid',
            'paid_at' => $request->paid_at,
            'payment_method' => $request->payment_method,
        ]);

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto marcado como pagado exitosamente.');
    }

    /**
     * Remove the specified expense from storage.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        if ($expense->attachment_path && Storage::disk('local')->exists($expense->attachment_path)) {
            Storage::disk('local')->delete($expense->attachment_path);
        }

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto eliminado exitosamente.');
    }

    /**
     * Download or stream the private invoice attachment securely.
     */
    public function downloadAttachment(Expense $expense): BinaryFileResponse
    {
        Gate::authorize('downloadAttachment', $expense);

        if (! $expense->attachment_path || ! Storage::disk('local')->exists($expense->attachment_path)) {
            abort(404, 'El archivo adjunto no existe en el almacenamiento privado.');
        }

        $fullPath = Storage::disk('local')->path($expense->attachment_path);
        $filename = basename($expense->attachment_path);

        return response()->file($fullPath, [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ]);
    }
}
