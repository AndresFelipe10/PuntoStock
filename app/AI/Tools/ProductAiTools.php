<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductAiTools
{
    /**
     * List products in catalog for specified tenant (read-only).
     */
    public function list(int $businessId, array $filters = []): AiQueryResult
    {
        $query = Product::where('business_id', $businessId)
            ->where('is_active', true)
            ->with('category');

        if (!empty($filters['search']) && is_string($filters['search'])) {
            $escaped = addcslashes(trim($filters['search']), '%_\\');
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                  ->orWhere('sku', 'like', "%{$escaped}%");
            });
        }

        $totalCount = $query->count();

        if ($totalCount === 0) {
            return new AiQueryResult(
                intent: 'list_products',
                summary: "No se encontraron productos activos con los criterios solicitados.",
                data: []
            );
        }

        $products = (clone $query)->orderBy('name')->limit(15)->get();

        $data = $products->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'sku' => $p->sku,
            'category' => $p->category ? $p->category->name : 'Sin categoría',
            'stock' => $p->stock,
            'sale_price' => '$' . number_format($p->sale_price, 0, ',', '.'),
        ])->toArray();

        return new AiQueryResult(
            intent: 'list_products',
            summary: "Se encontraron {$totalCount} productos en el catálogo.",
            data: $data,
            meta: ['domain' => 'products', 'count' => $totalCount]
        );
    }

    /**
     * Profitability and unit/percentage margin analysis (read-only).
     */
    public function margins(int $businessId, array $filters = []): AiQueryResult
    {
        $query = Product::where('business_id', $businessId)
            ->where('is_active', true);

        if (!empty($filters['product_type']) && is_string($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        $order = strtolower(trim((string) ($filters['order'] ?? 'asc')));
        $direction = ($order === 'desc') ? 'desc' : 'asc';
        $limit = isset($filters['limit']) ? min(20, max(1, (int) $filters['limit'])) : 5;

        $products = $query
            ->select('id', 'name', 'sku', 'product_type', 'cost_price', 'sale_price')
            ->orderByRaw('(sale_price - cost_price) ' . $direction)
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            return new AiQueryResult(
                intent: 'product_margins',
                summary: "No se encontraron productos activos registrados para calcular márgenes de rentabilidad.",
                data: []
            );
        }

        $zeroCostCount = $products->filter(fn($p) => (float) ($p->cost_price ?? 0) <= 0)->count();

        $data = $products->map(function ($p) {
            $cost = (float) ($p->cost_price ?? 0);
            $price = (float) ($p->sale_price ?? 0);
            $unitMargin = $price - $cost;
            $marginPct = $price > 0 ? round(($unitMargin / $price) * 100, 1) : 0.0;

            return [
                'name' => $p->name,
                'sku' => $p->sku,
                'cost' => '$' . number_format($cost, 0, ',', '.'),
                'sale_price' => '$' . number_format($price, 0, ',', '.'),
                'margin_amount' => '$' . number_format($unitMargin, 0, ',', '.'),
                'margin_percent' => number_format($marginPct, 1, ',', '.') . '%',
            ];
        })->toArray();

        $typeName = (!empty($filters['product_type']) && $filters['product_type'] === 'dish') ? 'platos' : 'productos';
        $qualifier = ($direction === 'asc') ? 'menor margen de ganancia' : 'mayor margen de ganancia';
        $summary = "Aquí tienes los {$products->count()} {$typeName} con {$qualifier}:";

        if ($zeroCostCount > 0) {
            $summary .= " (Nota: {$zeroCostCount} ítem(s) tienen costo en $0, por lo que su margen unitario equivale al 100% del precio de venta).";
        }

        return new AiQueryResult(
            intent: 'product_margins',
            summary: $summary,
            data: $data,
            meta: [
                'domain' => 'products',
                'count' => $products->count(),
                'direction' => $direction,
                'zero_cost_count' => $zeroCostCount,
            ]
        );
    }
}
