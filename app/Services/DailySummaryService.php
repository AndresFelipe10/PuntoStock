<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailySummaryService
{
    /**
     * Generate an exhaustive daily operational and financial summary for a business.
     *
     * @param Business $business
     * @param Carbon|null $date
     * @return array
     */
    public function generateSummary(Business $business, ?Carbon $date = null): array
    {
        $tz = 'America/Bogota';
        $targetDate = $date ? $date->copy()->timezone($tz) : Carbon::now($tz);

        $targetStart = $targetDate->copy()->startOfDay();
        $targetEnd = $targetDate->copy()->endOfDay();

        $yesterday = $targetDate->copy()->subDay();
        $yesterdayStart = $yesterday->copy()->startOfDay();
        $yesterdayEnd = $yesterday->copy()->endOfDay();

        // 1. Métricas de Ventas de Hoy
        $salesToday = Sale::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$targetStart, $targetEnd]);

        $revenueToday = (float) $salesToday->sum('total');
        $countToday = (int) $salesToday->count();
        $avgTicketToday = $countToday > 0 ? round($revenueToday / $countToday, 2) : 0.0;

        // 2. Métricas de Ventas de Ayer
        $salesYesterday = Sale::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$yesterdayStart, $yesterdayEnd]);

        $revenueYesterday = (float) $salesYesterday->sum('total');
        $countYesterday = (int) $salesYesterday->count();

        // 3. Diferencia y Variación % vs Ayer
        $diffRevenue = $revenueToday - $revenueYesterday;
        if ($revenueYesterday > 0) {
            $pctVariation = round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 1);
            $pctVariationStr = ($pctVariation >= 0 ? '+' : '') . number_format($pctVariation, 1, ',', '.') . '%';
        } elseif ($revenueToday > 0) {
            $pctVariationStr = '+100%';
        } else {
            $pctVariationStr = '0.0%';
        }

        // 4. Desglose por Método de Pago
        $payments = SalePayment::withoutGlobalScopes()
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->where('sale_payments.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$targetStart, $targetEnd])
            ->select('sale_payments.method', DB::raw('SUM(sale_payments.amount) as total_amount'), DB::raw('COUNT(*) as payment_count'))
            ->groupBy('sale_payments.method')
            ->orderByDesc('total_amount')
            ->get();

        $paymentBreakdown = [];
        $dominantPaymentMethod = null;

        foreach ($payments as $p) {
            $methodEnum = $p->method instanceof PaymentMethod 
                ? $p->method 
                : PaymentMethod::tryFrom((string) $p->method);

            $label = $methodEnum ? $methodEnum->label() : ucfirst((string) ($p->method instanceof PaymentMethod ? $p->method->value : $p->method));
            $methodValue = $methodEnum ? $methodEnum->value : (string) $p->method;
            $amount = (float) $p->total_amount;
            $percentage = $revenueToday > 0 ? round(($amount / $revenueToday) * 100, 1) : 0.0;

            if ($dominantPaymentMethod === null) {
                $dominantPaymentMethod = $label;
            }

            $paymentBreakdown[] = [
                'method' => $methodValue,
                'label' => $label,
                'amount' => $amount,
                'amount_formatted' => '$' . number_format($amount, 0, ',', '.'),
                'percentage' => $percentage,
                'count' => (int) $p->payment_count,
            ];
        }

        // 5. Top 5 Productos Más Vendidos Hoy
        $topProducts = SaleDetail::withoutGlobalScopes()
            ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.business_id', $business->id)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sale_date', [$targetStart, $targetEnd])
            ->select(
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $qty = (float) $item->total_quantity == (int) $item->total_quantity ? (int) $item->total_quantity : round((float) $item->total_quantity, 3);
                return [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => $qty,
                    'revenue' => (float) $item->total_revenue,
                    'revenue_formatted' => '$' . number_format($item->total_revenue, 0, ',', '.'),
                ];
            })
            ->toArray();

        // 6. Productos en Stock Crítico o por Agotarse (alerta de inventario)
        $criticalStock = Product::withoutGlobalScopes()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->where('product_type', '!=', 'dish')
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit(8)
            ->get()
            ->map(function ($prod) {
                $deficit = max(0, ceil(((float) $prod->min_stock * 2) - (float) $prod->stock));
                return [
                    'name' => $prod->name,
                    'sku' => $prod->sku,
                    'stock' => $prod->formatted_stock,
                    'min_stock' => $prod->min_stock,
                    'deficit' => $deficit,
                ];
            })
            ->toArray();

        // 7. Conclusión Ejecutiva del Asistente IA
        $aiConclusion = $this->generateAiConclusion(
            businessName: $business->name,
            revenueToday: $revenueToday,
            countToday: $countToday,
            pctVariationStr: $pctVariationStr,
            dominantPaymentMethod: $dominantPaymentMethod,
            topProducts: $topProducts,
            criticalStockCount: count($criticalStock)
        );

        return [
            'date_formatted' => $targetDate->format('d/m/Y'),
            'date_human' => $targetDate->translatedFormat('l, d \d\e F \d\e Y'),
            'revenue_today' => $revenueToday,
            'revenue_today_formatted' => '$' . number_format($revenueToday, 0, ',', '.'),
            'revenue_yesterday' => $revenueYesterday,
            'revenue_yesterday_formatted' => '$' . number_format($revenueYesterday, 0, ',', '.'),
            'difference_revenue' => $diffRevenue,
            'difference_revenue_formatted' => ($diffRevenue >= 0 ? '+$' : '-$') . number_format(abs($diffRevenue), 0, ',', '.'),
            'percentage_variation' => $pctVariationStr,
            'sales_count' => $countToday,
            'sales_yesterday_count' => $countYesterday,
            'average_ticket' => $avgTicketToday,
            'average_ticket_formatted' => '$' . number_format($avgTicketToday, 0, ',', '.'),
            'payment_breakdown' => $paymentBreakdown,
            'dominant_payment_method' => $dominantPaymentMethod ?? 'Sin registros',
            'top_products' => $topProducts,
            'critical_stock' => $criticalStock,
            'critical_stock_count' => count($criticalStock),
            'ai_conclusion' => $aiConclusion,
        ];
    }

    /**
     * Build an intelligent executive conclusion paragraph.
     */
    protected function generateAiConclusion(
        string $businessName,
        float $revenueToday,
        int $countToday,
        string $pctVariationStr,
        ?string $dominantPaymentMethod,
        array $topProducts,
        int $criticalStockCount
    ): string {
        if ($countToday === 0) {
            return "El día cerró sin transacciones de venta registradas en {$businessName}. Te recomendamos verificar la apertura de turnos y sincronización de terminales para la jornada de mañana.";
        }

        $formattedRev = '$' . number_format($revenueToday, 0, ',', '.');
        $conclusion = "La jornada cerró con una facturación total de {$formattedRev} distribuida en {$countToday} transacciones, registrando una variación de {$pctVariationStr} frente a la jornada anterior. ";

        if ($dominantPaymentMethod) {
            $conclusion .= "El método de recaudo predominante fue {$dominantPaymentMethod}. ";
        }

        if (!empty($topProducts)) {
            $starProduct = $topProducts[0];
            $conclusion .= "El artículo con mayor rotación fue '{$starProduct['name']}' ({$starProduct['quantity']} unidades por {$starProduct['revenue_formatted']}). ";
        }

        if ($criticalStockCount > 0) {
            $conclusion .= "Atención: se identificaron {$criticalStockCount} ítems en umbral de stock crítico que requieren orden de compra prioritaria al proveedor.";
        } else {
            $conclusion .= "Los niveles de existencias operativas se mantienen en rangos saludables.";
        }

        return $conclusion;
    }
}
