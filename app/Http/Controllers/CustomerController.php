<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CustomerController extends Controller
{
    /**
     * Search customers for POS autocomplete (min 3 chars, max 10 results).
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 3) {
            return response()->json([]);
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);

        $customers = Customer::query()
            ->where('is_active', true)
            ->where(function ($query) use ($escaped) {
                $query->whereRaw("document LIKE ? ESCAPE '!'", ["{$escaped}%"])
                      ->orWhereRaw("identification_number LIKE ? ESCAPE '!'", ["{$escaped}%"])
                      ->orWhereRaw("name LIKE ? ESCAPE '!'", ["%{$escaped}%"])
                      ->orWhereRaw("phone LIKE ? ESCAPE '!'", ["%{$escaped}%"]);
            })
            ->orderByRaw("CASE 
                WHEN document LIKE ? ESCAPE '!' THEN 1 
                WHEN identification_number LIKE ? ESCAPE '!' THEN 2 
                ELSE 3 
            END", ["{$escaped}%", "{$escaped}%"])
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'document', 'phone', 'address']);

        return response()->json($customers);
    }

    /**
     * Display a listing of the customers.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Customer::class);

        $query = Customer::withCount('sales');

        if ($search = $request->input('search')) {
            $escaped = addcslashes(trim((string) $search), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('document', 'like', "%{$escaped}%")
                  ->orWhere('identification_number', 'like', "%{$escaped}%")
                  ->orWhere('email', 'like', "%{$escaped}%")
                  ->orWhere('phone', 'like', "%{$escaped}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $customers = $query->latest()->paginate(15)->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
            'selectedStatus' => $request->input('status'),
        ]);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(): View
    {
        Gate::authorize('create', Customer::class);

        return view('customers.create');
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(StoreCustomerRequest $request): RedirectResponse|JsonResponse
    {
        $customer = Customer::create($request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $customer->id,
                'name' => $customer->name,
                'document' => $customer->document,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'message' => "Cliente '{$customer->name}' registrado exitosamente.",
            ], 201);
        }

        return redirect()->route('customers.index')
            ->with('status', "Cliente '{$customer->name}' registrado exitosamente.");
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): View
    {
        Gate::authorize('view', $customer);

        $customer->load(['sales' => function ($q) {
            $q->latest('sale_date')->take(10);
        }]);

        return view('customers.show', [
            'customer' => $customer,
        ]);
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Customer $customer): View
    {
        Gate::authorize('update', $customer);

        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('status', "Cliente '{$customer->name}' actualizado correctamente.");
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $hasHistory = $customer->sales()->exists()
            || \App\Models\RestaurantOrder::withoutGlobalScopes()->where('customer_id', $customer->id)->exists();

        if ($hasHistory) {
            $customer->update(['is_active' => false]);

            return redirect()->route('customers.index')
                ->with('info', "El cliente '{$customer->name}' tiene ventas o pedidos registrados. Ha sido desactivado en lugar de eliminado para conservar el historial.");
        }

        $name = $customer->name;
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('status', "Cliente '{$name}' eliminado correctamente.");
    }
}
