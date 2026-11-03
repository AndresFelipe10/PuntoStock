<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $query = Product::with('category');

        if ($search = $request->input('search')) {
            $escaped = addcslashes(trim((string) $search), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('sku', 'like', "%{$escaped}%")
                  ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        $tenant = app(\App\Services\Tenant\TenantManager::class)->get();
        $isRestaurant = $tenant?->isRestaurant() ?? false;
        $tab = $request->input('tab', 'all');

        if ($isRestaurant) {
            if ($tab === 'menu') {
                $query->where('product_type', 'dish');
            } elseif ($tab === 'merchandise') {
                $query->where('product_type', 'standard');
            }
        }

        $products = $query->latest()->paginate(15)->withQueryString();
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.index', [
            'products' => $products,
            'categories' => $categories,
            'search' => $search,
            'selectedCategory' => $categoryId,
            'selectedStatus' => $request->input('status'),
            'lowStockOnly' => $request->boolean('low_stock'),
            'activeTab' => $tab,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): View
    {
        Gate::authorize('create', Product::class);

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.create', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = DB::transaction(function () use ($data) {
            if (empty($data['sku'])) {
                $tenantId = app(\App\Services\Tenant\TenantManager::class)->id();
                $data['sku'] = Product::generateUniqueSku($tenantId, $data['product_type'] ?? 'standard');
            }

            return Product::create($data);
        });

        return redirect()->route('products.index')
            ->with('status', "Producto '{$product->name}' (SKU: {$product->sku}) registrado exitosamente.");
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product): View
    {
        Gate::authorize('view', $product);

        $product->load(['category', 'inventoryMovements' => function ($q) {
            $q->latest()->take(10);
        }]);

        return view('products.show', [
            'product' => $product,
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('products.edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')
            ->with('status', "Producto '{$product->name}' actualizado correctamente.");
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $hasHistory = $product->saleDetails()->exists()
            || $product->inventoryMovements()->exists()
            || \App\Models\RestaurantOrderItem::withoutGlobalScopes()->where('product_id', $product->id)->exists();

        if ($hasHistory) {
            $product->update(['is_active' => false]);

            return redirect()->route('products.index')
                ->with('info', "El producto '{$product->name}' no se puede eliminar porque tiene historial de ventas, comandas o movimientos de inventario registrados. Ha sido desactivado para conservar la trazabilidad.");
        }

        $name = $product->name;
        $product->delete();

        return redirect()->route('products.index')
            ->with('status', "Producto '{$name}' eliminado correctamente.");
    }

    /**
     * Download the official Excel template according to the authenticated business type.
     */
    public function downloadTemplate(Request $request, ProductImportService $importService): BinaryFileResponse
    {
        Gate::authorize('viewAny', Product::class);

        $business = auth()->user()?->business
            ?? app(\App\Services\Tenant\TenantManager::class)->get()
            ?? auth()->user()?->businesses()->first();

        // Determinar vertical: default es retail a menos que sea explícitamente restaurante
        $type = 'retail';

        // Asegurar que solo si el negocio tiene como categoría, tipo o nombre restaurant se le envíe la de restaurante
        $businessType = strtolower($business->business_type ?? $business->category ?? '');

        if (str_contains($businessType, 'restaurant') || str_contains($businessType, 'gastronomic') || $request->query('type') === 'restaurant') {
            $type = 'restaurant';
        }

        if ($request->query('type') === 'retail' || $request->query('type') === 'commerce') {
            $type = 'retail';
        }

        if ($type === 'restaurant') {
            $filePath = public_path('templates/Plantilla_Restaurante_PuntoStock.xlsx');
            $fileName = 'Plantilla_Restaurante_PuntoStock.xlsx';
        } else {
            $filePath = public_path('templates/Plantilla_Comercio_Retail_PuntoStock.xlsx');
            $fileName = 'Plantilla_Comercio_Retail_PuntoStock.xlsx';
        }

        if (!file_exists($filePath)) {
            // Llamar al fallback de generación en tiempo de ejecución en caso de testing o CI sin plantillas físicas
            $importService->ensureTemplateExists($type);
        }

        return response()->download($filePath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Process bulk import of products and inventory from Excel (.xlsx) or CSV (.csv).
     */
    public function import(Request $request, ProductImportService $importService): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
            'update_existing' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Debe seleccionar un archivo Excel o CSV para importar.',
            'file.mimes' => 'El archivo debe estar en formato Excel (.xlsx, .xls) o CSV (.csv).',
            'file.max' => 'El archivo no puede superar los 10MB.',
        ]);

        $tenant = app(\App\Services\Tenant\TenantManager::class)->get()
            ?? auth()->user()?->business
            ?? auth()->user()?->businesses()->first();
        $userId = (int) auth()->id();
        $updateExisting = $request->boolean('update_existing', true);

        $result = $importService->import(
            $request->file('file'),
            $tenant,
            $userId,
            $updateExisting
        );

        return redirect()->route('products.index')
            ->with('import_result', $result);
    }
}
