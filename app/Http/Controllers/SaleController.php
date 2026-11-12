<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\Sales\InvalidSaleItemException;
use App\Exceptions\Sales\InvalidSalePaymentException;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        protected SaleService $saleService
    ) {}

    /**
     * Display a listing of sales.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Sale::class);

        $query = Sale::with(['customer', 'user'])
            ->whereNull('restaurant_order_id');

        // Filter by date range
        if ($from = $request->input('from')) {
            $query->whereDate('sale_date', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('sale_date', '<=', $to);
        }

        // Filter by payment method
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Search by invoice number or customer name
        if ($search = $request->input('search')) {
            $escaped = addcslashes(trim((string) $search), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('invoice_number', 'like', "%{$escaped}%")
                  ->orWhereHas('customer', function ($cq) use ($escaped) {
                      $cq->where('name', 'like', "%{$escaped}%");
                  });
            });
        }

        $sales = $query->latest('sale_date')->paginate(15)->withQueryString();

        return view('sales.index', [
            'sales' => $sales,
            'search' => $search,
            'from' => $from,
            'to' => $request->input('to'),
            'selectedPaymentMethod' => $request->input('payment_method'),
            'selectedStatus' => $request->input('status'),
        ]);
    }

    /**
     * Show the form for creating a new sale (POS).
     */
    public function create(): View
    {
        Gate::authorize('create', Sale::class);

        $selectedCustomer = null;
        if (old('customer_id')) {
            $selectedCustomer = Customer::find(old('customer_id'));
        }

        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get();

        Product::attachAvailableStock($products);

        $catalogProducts = $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'sale_price' => (float) $p->sale_price,
                'price' => (float) $p->sale_price,
                'stock' => (float) ($p->available_stock ?? $p->stock),
                'available_stock' => (float) ($p->available_stock ?? $p->stock),
                'product_type' => $p->product_type,
                'is_standard' => $p->isStandard(),
            ];
        })->values();

        return view('sales.create', [
            'defaultCustomerName' => config('sales.default_customer_name', 'Consumidor Final'),
            'defaultCustomerDocument' => config('sales.default_customer_document', '222222222222'),
            'selectedCustomer' => $selectedCustomer,
            'paymentMethods' => PaymentMethod::cases(),
            'products' => $products,
            'catalogProducts' => $catalogProducts,
        ]);
    }

    /**
     * Store a newly created sale in storage.
     */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->saleService->processSale(
                data: $request->validated(),
                businessId: (int) session('current_business_id'),
                userId: (int) auth()->id(),
            );

            return redirect()->route('sales.show', $sale)
                ->with('status', "Venta {$sale->invoice_number} registrada exitosamente.");
        } catch (InsufficientStockException $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['inventory' => $e->getMessage(), 'items' => $e->getMessage()])
                ->with('error', $e->getMessage());
        } catch (InvalidSaleItemException $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['items' => $e->getMessage()]);
        } catch (InvalidSalePaymentException $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['payments' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified sale (invoice/receipt).
     */
    public function show(Sale $sale): View
    {
        Gate::authorize('view', $sale);

        $sale->load(['details.product', 'customer', 'user', 'payments', 'restaurantOrder']);

        return view('sales.show', [
            'sale' => $sale,
        ]);
    }

    /**
     * Print invoice format (Letter/A4).
     */
    public function printInvoice(Sale $sale): View
    {
        Gate::authorize('view', $sale);
        abort_if($sale->business_id !== (int) session('current_business_id'), 403);

        $sale->load(['details.product', 'customer', 'user', 'payments', 'restaurantOrder']);
        $business = Business::findOrFail(session('current_business_id'));

        return view('sales.print-invoice', [
            'sale' => $sale,
            'business' => $business,
        ]);
    }

    /**
     * Print receipt format (thermal 80mm).
     */
    public function printReceipt(Sale $sale): View
    {
        Gate::authorize('view', $sale);
        abort_if($sale->business_id !== (int) session('current_business_id'), 403);

        $sale->load(['details.product', 'customer', 'user', 'payments', 'restaurantOrder']);
        $business = Business::findOrFail(session('current_business_id'));

        return view('sales.print-receipt', [
            'sale' => $sale,
            'business' => $business,
        ]);
    }

    /**
     * Cancel a sale and restore stock (admin only).
     */
    public function destroy(Sale $sale): RedirectResponse
    {
        Gate::authorize('delete', $sale);

        if ($sale->status === 'cancelled') {
            return redirect()->route('sales.index')
                ->with('info', "La venta {$sale->invoice_number} ya se encuentra anulada.");
        }

        $this->saleService->cancelSale($sale, (int) auth()->id());

        return redirect()->route('sales.index')
            ->with('status', "Venta {$sale->invoice_number} anulada correctamente. Stock restaurado.");
    }
}
