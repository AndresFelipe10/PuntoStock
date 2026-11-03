<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\Expense\PayExpenseRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Supplier;
use App\Services\Expenses\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    /**
     * Display a listing of expenses.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Expense::class);

        $businessId = (int) session('current_business_id');
        $filters = $request->only(['supplier_id', 'category', 'status', 'month', 'year', 'search', 'date_from', 'date_to']);

        $expenses = $this->expenseService->getFilteredExpenses($businessId, $filters);
        $summary = $this->expenseService->getSummaryMetrics($businessId, $filters);
        $suppliers = Supplier::where('business_id', $businessId)->orderBy('name')->get();

        return view('expenses.index', [
            'expenses' => $expenses,
            'summary' => $summary,
            'suppliers' => $suppliers,
            'categories' => ExpenseCategory::cases(),
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new expense.
     */
    public function create(): View
    {
        Gate::authorize('create', Expense::class);

        $businessId = (int) session('current_business_id');
        $suppliers = Supplier::where('business_id', $businessId)->where('is_active', true)->orderBy('name')->get();

        return view('expenses.create', [
            'suppliers' => $suppliers,
            'categories' => ExpenseCategory::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Store a newly created expense in storage.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $businessId = (int) session('current_business_id');
        $userId = (int) auth()->id();

        $expense = $this->expenseService->createExpense(
            data: $request->validated(),
            businessId: $businessId,
            userId: $userId,
            attachment: $request->file('attachment'),
        );

        return redirect()->route('expenses.index')
            ->with('success', "Gasto registrado exitosamente (Monto: $" . number_format($expense->amount, 0, ',', '.') . ").");
    }

    /**
     * Display the specified expense.
     */
    public function show(Expense $expense): View
    {
        Gate::authorize('view', $expense);

        $expense->load(['supplier', 'creator']);

        return view('expenses.show', [
            'expense' => $expense,
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Show the form for editing the specified expense.
     */
    public function edit(Expense $expense): View
    {
        Gate::authorize('update', $expense);

        $businessId = (int) session('current_business_id');
        $suppliers = Supplier::where('business_id', $businessId)
            ->where(function ($q) use ($expense) {
                $q->where('is_active', true)
                  ->orWhere('id', $expense->supplier_id);
            })
            ->orderBy('name')
            ->get();

        return view('expenses.edit', [
            'expense' => $expense,
            'suppliers' => $suppliers,
            'categories' => ExpenseCategory::cases(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    /**
     * Update the specified expense in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenseService->updateExpense(
            expense: $expense,
            data: $request->validated(),
            attachment: $request->file('attachment'),
            removeAttachment: (bool) $request->boolean('remove_attachment'),
        );

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto actualizado exitosamente.');
    }

    /**
     * Remove the specified expense from storage.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        Gate::authorize('delete', $expense);

        $this->expenseService->deleteExpense($expense);

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto eliminado exitosamente.');
    }

    /**
     * Quick action to mark an expense as paid.
     */
    public function markAsPaid(PayExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenseService->markAsPaid($expense, $request->validated());

        return redirect()->back()
            ->with('success', 'Gasto marcado como pagado exitosamente.');
    }

    /**
     * Securely download or view the private attachment.
     */
    public function downloadAttachment(Expense $expense): StreamedResponse
    {
        Gate::authorize('downloadAttachment', $expense);

        return $this->expenseService->getAttachmentResponse($expense);
    }
}
