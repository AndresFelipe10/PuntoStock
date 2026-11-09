<?php

namespace App\AI\Tools;

use App\AI\DTOs\AiQueryResult;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SaleAiTools
{
    /**
     * Sales summary for today or current month (read-only).
     */
    public function summary(int $businessId, array $filters = []): AiQueryResult
    {
        $period = $filters['period'] ?? 'today';

        if ($period === 'month') {
            $dateFrom = Carbon::now()->startOfMonth();
            $dateTo = Carbon::now()->endOfMonth();
            $periodLabel = "este mes (" . Carbon::now()->translatedFormat('F Y') . ")";
        } elseif ($period === 'yesterday') {
            $dateFrom = Carbon::yesterday()->startOfDay();
            $dateTo = Carbon::yesterday()->endOfDay();
            $periodLabel = "el día de ayer";
        } else {
            $dateFrom = Carbon::today()->startOfDay();
            $dateTo = Carbon::today()->endOfDay();
            $periodLabel = "el día de hoy";
        }

        $aggregates = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo])
            ->selectRaw('COUNT(*) as aggregate_count, COALESCE(SUM(total), 0) as aggregate_total')
            ->first();

        $count = (int) ($aggregates->aggregate_count ?? 0);
        $totalRevenue = (float) ($aggregates->aggregate_total ?? 0);
        $avgTicket = $count > 0 ? round($totalRevenue / $count, 2) : 0.0;

        return new AiQueryResult(
            intent: 'sales_summary',
            summary: "En {$periodLabel} se han registrado {$count} ventas por un valor total de $" .
                number_format($totalRevenue, 0, ',', '.') . " con un ticket promedio de $" .
                number_format($avgTicket, 0, ',', '.') . ".",
            data: [
                'period' => $period,
                'sales_count' => $count,
                'total_revenue' => '$' . number_format($totalRevenue, 0, ',', '.'),
                'average_ticket' => '$' . number_format($avgTicket, 0, ',', '.'),
            ],
            meta: [
                'domain' => 'sales',
                'period' => $period,
            ]
        );
    }

    /**
     * Sales by date range or period (read-only).
     */
    public function byPeriod(int $businessId, array $filters = []): AiQueryResult
    {
        $dateFrom = Carbon::now()->startOfMonth();
        if (!empty($filters['date_from']) && is_string($filters['date_from'])) {
            try {
                $dateFrom = Carbon::parse($filters['date_from'])->startOfDay();
            } catch (\Throwable) {
                $dateFrom = Carbon::now()->startOfMonth();
            }
        }

        $dateTo = Carbon::now()->endOfDay();
        if (!empty($filters['date_to']) && is_string($filters['date_to'])) {
            try {
                $dateTo = Carbon::parse($filters['date_to'])->endOfDay();
            } catch (\Throwable) {
                $dateTo = Carbon::now()->endOfDay();
            }
        }

        if ($dateFrom->gt($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $baseQuery = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$dateFrom, $dateTo]);

        $totalCount = $baseQuery->count();
        $totalRevenue = (float) (clone $baseQuery)->sum('total');

        $sales = (clone $baseQuery)
            ->with('customer')
            ->orderByDesc('sale_date')
            ->limit(10)
            ->get();

        $data = $sales->map(fn($s) => [
            'invoice' => $s->invoice_number,
            'date' => $s->sale_date->format('d/m/Y H:i'),
            'customer' => $s->customer_name ?? ($s->customer ? $s->customer->name : config('sales.default_customer_name', 'CONSUMIDOR FINAL')),
            'payment_method' => $s->payment_method_label,
            'total' => '$' . number_format($s->total, 0, ',', '.'),
        ])->toArray();

        return new AiQueryResult(
            intent: 'sales_by_period',
            summary: "Se registraron ventas por un total de $" . number_format($totalRevenue, 0, ',', '.') .
                " entre {$dateFrom->format('d/m/Y')} y {$dateTo->format('d/m/Y')}.",
            data: $data,
            meta: [
                'domain' => 'sales',
                'count' => $totalCount,
            ]
        );
    }

    /**
     * Top selling products by quantity (read-only).
     */
    public function topSelling(int $businessId, array $filters = []): AiQueryResult
    {
        $limit = isset($filters['limit']) ? min(15, max(1, (int)$filters['limit'])) : 5;

        $results = SaleDetail::join('sales', 'sale_details.sale_id', '=', 'sales.id')
            ->join('products', 'sale_details.product_id', '=', 'products.id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_details.quantity) as total_quantity'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        if ($results->isEmpty()) {
            return new AiQueryResult(
                intent: 'top_selling_products',
                summary: "Aún no se registran ventas completadas para calcular el ranking de productos más vendidos.",
                data: []
            );
        }

        $data = $results->map(function ($r) {
            $qty = (float) $r->total_quantity == (int) $r->total_quantity ? (int) $r->total_quantity : round((float) $r->total_quantity, 3);
            return [
                'name' => $r->name,
                'sku' => $r->sku,
                'quantity_sold' => $qty,
                'revenue_generated' => '$' . number_format($r->total_revenue, 0, ',', '.'),
            ];
        })->toArray();

        $topName = $results->first()->name;
        $rawTopQty = (float) $results->first()->total_quantity;
        $topQty = $rawTopQty == (int) $rawTopQty ? (int) $rawTopQty : round($rawTopQty, 3);

        return new AiQueryResult(
            intent: 'top_selling_products',
            summary: "Tu producto estrella es '{$topName}' con {$topQty} unidades vendidas. Aquí tienes el top {$results->count()}:",
            data: $data
        );
    }

    /**
     * Temporal and shift-based sales comparison (read-only).
     */
    public function comparison(int $businessId, array $filters = []): AiQueryResult
    {
        $tz = 'America/Bogota';
        $now = Carbon::now($tz);

        // 1. Detectar turno (mañana: 06:00-11:59, tarde: 12:00-17:59, noche: 18:00-23:59)
        $shiftParam = strtolower(trim((string) ($filters['shift'] ?? '')));
        $shiftTimeWindow = null;
        $shiftLabel = null;

        if (in_array($shiftParam, ['morning', 'mañana', 'manana'], true)) {
            $shiftTimeWindow = ['06:00:00', '11:59:59'];
            $shiftLabel = 'Turno Mañana (06:00 - 12:00)';
        } elseif (in_array($shiftParam, ['afternoon', 'tarde'], true)) {
            $shiftTimeWindow = ['12:00:00', '17:59:59'];
            $shiftLabel = 'Turno Tarde (12:00 - 18:00)';
        } elseif (in_array($shiftParam, ['night', 'noche'], true)) {
            $shiftTimeWindow = ['18:00:00', '23:59:59'];
            $shiftLabel = 'Turno Noche (18:00 - 23:59)';
        }

        // 2. Resolver período actual (target) y comparativo (compare_to)
        $targetParam = strtolower(trim((string) ($filters['target_date'] ?? 'today')));
        $compareParam = strtolower(trim((string) ($filters['compare_to'] ?? 'yesterday')));

        // Mapeo de días de la semana en español e inglés
        $dayMap = [
            'monday' => Carbon::MONDAY, 'lunes' => Carbon::MONDAY,
            'tuesday' => Carbon::TUESDAY, 'martes' => Carbon::TUESDAY,
            'wednesday' => Carbon::WEDNESDAY, 'miercoles' => Carbon::WEDNESDAY, 'miércoles' => Carbon::WEDNESDAY,
            'thursday' => Carbon::THURSDAY, 'jueves' => Carbon::THURSDAY,
            'friday' => Carbon::FRIDAY, 'viernes' => Carbon::FRIDAY,
            'saturday' => Carbon::SATURDAY, 'sabado' => Carbon::SATURDAY, 'sábado' => Carbon::SATURDAY,
            'sunday' => Carbon::SUNDAY, 'domingo' => Carbon::SUNDAY,
        ];

        // Fechas base
        $targetDate = $now->copy();
        $compareDate = null;
        $targetLabel = 'Hoy';
        $compareLabel = 'Período anterior';

        // Manejo específico de semanas y meses completos si no hay turno
        if ($shiftTimeWindow === null && ($targetParam === 'this_week' || $compareParam === 'last_week' || str_contains($compareParam, 'semana'))) {
            $targetStart = $now->copy()->startOfWeek();
            $targetEnd = $now->copy()->endOfWeek();
            $compareStart = $now->copy()->subWeek()->startOfWeek();
            $compareEnd = $now->copy()->subWeek()->endOfWeek();
            $targetLabel = 'Esta Semana (' . $targetStart->format('d/m') . ' - ' . $targetEnd->format('d/m') . ')';
            $compareLabel = 'Semana Pasada (' . $compareStart->format('d/m') . ' - ' . $compareEnd->format('d/m') . ')';
        } elseif ($shiftTimeWindow === null && ($targetParam === 'this_month' || $compareParam === 'last_month' || str_contains($compareParam, 'mes'))) {
            $targetStart = $now->copy()->startOfMonth();
            $targetEnd = $now->copy()->endOfMonth();
            $compareStart = $now->copy()->subMonth()->startOfMonth();
            $compareEnd = $now->copy()->subMonth()->endOfMonth();
            $targetLabel = 'Este Mes (' . $now->translatedFormat('F Y') . ')';
            $compareLabel = 'Mes Pasado (' . $compareStart->translatedFormat('F Y') . ')';
        } else {
            // Comparación de días puntuales (con o sin turno)
            if ($targetParam === 'yesterday' || $targetParam === 'ayer') {
                $targetDate = $now->copy()->subDay();
                $targetLabel = 'Ayer';
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetParam)) {
                $targetDate = Carbon::parse($targetParam, $tz);
                $targetLabel = $targetDate->format('d/m/Y');
            }

            // Determinar fecha de comparación
            foreach ($dayMap as $dayName => $dayConstant) {
                if (str_contains($compareParam, $dayName)) {
                    $compareDate = $now->copy()->previous($dayConstant);
                    $capitalizedDay = ucfirst($dayName);
                    $compareLabel = "{$capitalizedDay} pasado ({$compareDate->format('d/m/Y')})";
                    break;
                }
            }

            if ($compareDate === null) {
                if ($compareParam === 'yesterday' || $compareParam === 'ayer' || str_contains($compareParam, 'ayer')) {
                    $compareDate = $targetDate->copy()->subDay();
                    $compareLabel = 'Ayer (' . $compareDate->format('d/m/Y') . ')';
                } elseif ($compareParam === 'last_week' || str_contains($compareParam, 'semana')) {
                    $compareDate = $targetDate->copy()->subWeek();
                    $compareLabel = 'Mismo día semana pasada (' . $compareDate->format('d/m/Y') . ')';
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $compareParam)) {
                    $compareDate = Carbon::parse($compareParam, $tz);
                    $compareLabel = $compareDate->format('d/m/Y');
                } else {
                    $compareDate = $targetDate->copy()->subDay();
                    $compareLabel = 'Ayer (' . $compareDate->format('d/m/Y') . ')';
                }
            }

            if ($shiftTimeWindow !== null) {
                [$startH, $endH] = $shiftTimeWindow;
                $targetStart = Carbon::parse($targetDate->format('Y-m-d') . ' ' . $startH, $tz);
                $targetEnd = Carbon::parse($targetDate->format('Y-m-d') . ' ' . $endH, $tz);
                $compareStart = Carbon::parse($compareDate->format('Y-m-d') . ' ' . $startH, $tz);
                $compareEnd = Carbon::parse($compareDate->format('Y-m-d') . ' ' . $endH, $tz);

                $targetLabel .= ' - ' . $shiftLabel;
                $compareLabel .= ' - ' . $shiftLabel;
            } else {
                $targetStart = $targetDate->copy()->startOfDay();
                $targetEnd = $targetDate->copy()->endOfDay();
                $compareStart = $compareDate->copy()->startOfDay();
                $compareEnd = $compareDate->copy()->endOfDay();
            }
        }

        // 3. Consultas agregadas aisladas por tenant
        $currentAggregates = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$targetStart, $targetEnd])
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total), 0) as total_sum')
            ->first();

        $compareAggregates = Sale::where('business_id', $businessId)
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$compareStart, $compareEnd])
            ->selectRaw('COUNT(*) as total_count, COALESCE(SUM(total), 0) as total_sum')
            ->first();

        $currentCount = (int) ($currentAggregates->total_count ?? 0);
        $currentTotal = (float) ($currentAggregates->total_sum ?? 0);
        $compareCount = (int) ($compareAggregates->total_count ?? 0);
        $compareTotal = (float) ($compareAggregates->total_sum ?? 0);

        // 4. Cálculo de diferencias y variaciones
        $diffTotal = $currentTotal - $compareTotal;
        $diffCount = $currentCount - $compareCount;

        if ($compareTotal > 0) {
            $pctVariation = round((($currentTotal - $compareTotal) / $compareTotal) * 100, 1);
            $pctVariationStr = ($pctVariation >= 0 ? '+' : '') . number_format($pctVariation, 1, ',', '.') . '%';
        } elseif ($currentTotal > 0) {
            $pctVariationStr = '+100%';
        } else {
            $pctVariationStr = '0.0%';
        }

        $diffTotalFormatted = ($diffTotal >= 0 ? '+$' : '-$') . number_format(abs($diffTotal), 0, ',', '.');
        $diffCountFormatted = ($diffCount >= 0 ? '+' : '') . $diffCount;

        // Estructura tabular para renderizar en UI
        $tableData = [
            [
                'period' => $targetLabel,
                'total_revenue' => '$' . number_format($currentTotal, 0, ',', '.'),
                'sales_count' => $currentCount,
                'difference' => '-',
                'variation' => '-',
            ],
            [
                'period' => $compareLabel,
                'total_revenue' => '$' . number_format($compareTotal, 0, ',', '.'),
                'sales_count' => $compareCount,
                'difference' => $diffTotalFormatted,
                'variation' => $pctVariationStr,
            ],
        ];

        $summary = "En {$targetLabel} se registraron {$currentCount} ventas por un total de $" .
            number_format($currentTotal, 0, ',', '.') . ", comparado con {$compareCount} ventas por $" .
            number_format($compareTotal, 0, ',', '.') . " en {$compareLabel}. " .
            "Variación: {$pctVariationStr} ({$diffTotalFormatted}).";

        return new AiQueryResult(
            intent: 'sales_comparison',
            summary: $summary,
            data: $tableData,
            meta: [
                'domain' => 'sales',
                'current_total' => $currentTotal,
                'compare_total' => $compareTotal,
                'current_count' => $currentCount,
                'compare_count' => $compareCount,
                'difference_total' => $diffTotal,
                'difference_count' => $diffCount,
                'percentage_variation' => $pctVariationStr,
            ]
        );
    }
}
