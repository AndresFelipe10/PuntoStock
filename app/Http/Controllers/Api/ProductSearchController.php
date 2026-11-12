<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSearchController extends Controller
{
    /**
     * Search products by name or SKU for the active tenant.
     * Used by the POS sale form for autocomplete.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $rawSearch = trim((string) $request->input('q', ''));

        $query = Product::where('is_active', true);

        if ($rawSearch !== '') {
            $escaped = addcslashes($rawSearch, '%_\\');

            $query->where(function ($q) use ($escaped, $rawSearch) {
                // Si coincide exactamente con SKU, búsqueda directa indexada
                $q->where('sku', $rawSearch)
                  ->orWhere('name', 'like', "%{$escaped}%")
                  ->orWhere('sku', 'like', "%{$escaped}%");
            });
        }

        $products = $query->select(['id', 'name', 'sku', 'sale_price', 'stock', 'product_type'])
            ->orderBy('name')
            ->limit(15)
            ->get();

        Product::attachAvailableStock($products);

        $products->each(function ($p) {
            $p->append(['price', 'available_stock']);
            $p->setAttribute('price', (float) $p->sale_price);
            $p->setAttribute('available_stock', (float) ($p->available_stock ?? $p->stock));
            if ($p->isStandard()) {
                $p->stock = $p->available_stock;
            }
        });

        return response()->json($products)
            ->header('Cache-Control', 'private, max-age=5');
    }
}
