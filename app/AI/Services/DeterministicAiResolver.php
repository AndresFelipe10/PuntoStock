<?php

namespace App\AI\Services;

use App\AI\DTOs\AiQueryIntent;
use App\AI\Enums\AiIntent;

class DeterministicAiResolver
{
    /**
     * Resolve natural language queries deterministically with 100% availability.
     *
     * @param string $query
     * @param string|null $businessType
     * @return AiQueryIntent|null
     */
    public function resolve(string $query, ?string $businessType = null): ?AiQueryIntent
    {
        $normalized = mb_strtolower(trim($query));
        $clean = trim($normalized, "¡!¿?.,;:()[]{}\"'\r\n");

        // 1. Detección de Comparativas Temporales y Turnos (SALES_COMPARISON)
        if ($this->isSalesComparison($clean)) {
            $filters = $this->extractComparisonFilters($clean);
            return new AiQueryIntent(
                intent: AiIntent::SALES_COMPARISON,
                filters: $filters,
                confidence: 1.0,
                rawResponse: json_encode(['resolver' => 'deterministic', 'type' => 'sales_comparison', 'filters' => $filters])
            );
        }

        // 2. Detección de Márgenes y Rentabilidad (PRODUCT_MARGINS)
        if ($this->isProductMargins($clean)) {
            $filters = $this->extractMarginFilters($clean, $businessType);
            return new AiQueryIntent(
                intent: AiIntent::PRODUCT_MARGINS,
                filters: $filters,
                confidence: 1.0,
                rawResponse: json_encode(['resolver' => 'deterministic', 'type' => 'product_margins', 'filters' => $filters])
            );
        }

        // 3. Ranking de Productos Más Vendidos (TOP_SELLING_PRODUCTS)
        if ($this->isTopSelling($clean)) {
            $limit = $this->extractLimit($clean, 5);
            return new AiQueryIntent(
                intent: AiIntent::TOP_SELLING_PRODUCTS,
                filters: ['limit' => $limit],
                confidence: 1.0,
                rawResponse: json_encode(['resolver' => 'deterministic', 'type' => 'top_selling', 'limit' => $limit])
            );
        }

        // 4. Inventario Crítico / Bajo Stock / Reposición Proveedor (LOW_STOCK_PRODUCTS)
        if ($this->isLowStock($clean)) {
            return new AiQueryIntent(
                intent: AiIntent::LOW_STOCK_PRODUCTS,
                filters: [],
                confidence: 1.0,
                rawResponse: json_encode(['resolver' => 'deterministic', 'type' => 'low_stock'])
            );
        }

        // 5. Productos Completamente Agotados (OUT_OF_STOCK_PRODUCTS)
        if ($this->isOutOfStock($clean)) {
            return new AiQueryIntent(
                intent: AiIntent::OUT_OF_STOCK_PRODUCTS,
                filters: [],
                confidence: 1.0,
                rawResponse: json_encode(['resolver' => 'deterministic', 'type' => 'out_of_stock'])
            );
        }

        // 6. Valoración y Resumen de Inventario (INVENTORY_SUMMARY)
        if ($this->isInventorySummary($clean)) {
            return new AiQueryIntent(
                intent: AiIntent::INVENTORY_SUMMARY,
                filters: [],
                confidence: 1.0,
                rawResponse: json_encode(['resolver' => 'deterministic', 'type' => 'inventory_summary'])
            );
        }

        // 7. Resumen de Ventas (SALES_SUMMARY)
        if ($this->isSalesSummary($clean)) {
            $period = 'today';
            if (str_contains($clean, 'mes')) {
                $period = 'month';
            } elseif (str_contains($clean, 'ayer')) {
                $period = 'yesterday';
            }
            return new AiQueryIntent(
                intent: AiIntent::SALES_SUMMARY,
                filters: ['period' => $period],
                confidence: 1.0,
                rawResponse: json_encode(['resolver' => 'deterministic', 'type' => 'sales_summary', 'period' => $period])
            );
        }

        // 8. Consultas Específicas de Restaurante
        if ($businessType === 'restaurant' || $this->containsRestaurantKeywords($clean)) {
            if (preg_match('/\b(mesas?|sal[oó]n)\b/u', $clean)) {
                return new AiQueryIntent(intent: AiIntent::RESTAURANT_TABLES_STATUS, filters: [], confidence: 1.0);
            }
            if (preg_match('/\b(comandas?|pedidos?\s+abiertos?)\b/u', $clean)) {
                return new AiQueryIntent(intent: AiIntent::RESTAURANT_ORDERS_OPEN, filters: [], confidence: 1.0);
            }
            if (preg_match('/\b(domicilios?|despachos?)\b/u', $clean)) {
                return new AiQueryIntent(intent: AiIntent::RESTAURANT_DELIVERIES_TODAY, filters: [], confidence: 1.0);
            }
            if (preg_match('/\b(propinas?|servicio\s+voluntario)\b/u', $clean)) {
                return new AiQueryIntent(intent: AiIntent::RESTAURANT_TIPS_COLLECTED, filters: [], confidence: 1.0);
            }
        }

        // 9. Clientes y Proveedores
        if (preg_match('/\b(cu[aá]ntos\s+clientes|total\s+de\s+clientes|cantidad\s+de\s+clientes)\b/u', $clean)) {
            return new AiQueryIntent(intent: AiIntent::COUNT_CUSTOMERS, filters: [], confidence: 1.0);
        }
        if (preg_match('/\b(clientes?)\b/u', $clean)) {
            return new AiQueryIntent(intent: AiIntent::LIST_CUSTOMERS, filters: [], confidence: 1.0);
        }
        if (preg_match('/\b(proveedores?)\b/u', $clean)) {
            return new AiQueryIntent(intent: AiIntent::LIST_SUPPLIERS, filters: [], confidence: 1.0);
        }

        // 10. Gastos
        if (preg_match('/\b(gastos?|compras?\s+registradas?|egresos?)\b/u', $clean)) {
            return new AiQueryIntent(intent: AiIntent::EXPENSES_SUMMARY, filters: [], confidence: 1.0);
        }

        return null;
    }

    protected function isSalesComparison(string $text): bool
    {
        $hasComparisonIndicator = (bool) preg_match('/\b(comparad[oa]|comparar|vs|versus|frente\s+a|respecto\s+a|diferencia|variaci[oó]n)\b/u', $text);
        $hasShift = (bool) preg_match('/\b(turno|mañana|manana|tarde|noche)\b/u', $text);
        $hasRelativeDates = (bool) preg_match('/\b(hoy\s+vs\s+ayer|semana\s+pasada|mes\s+pasado|martes\s+pasado|lunes\s+pasado|mi[eé]rcoles\s+pasado|jueves\s+pasado|viernes\s+pasado|s[aá]bado\s+pasado|domingo\s+pasado)\b/u', $text);

        return ($hasComparisonIndicator && ($hasShift || $hasRelativeDates || str_contains($text, 'ayer') || str_contains($text, 'semana')))
            || ($hasShift && (str_contains($text, 'pasad') || str_contains($text, 'ayer') || str_contains($text, 'semana') || $hasComparisonIndicator))
            || str_contains($text, 'hoy vs ayer')
            || str_contains($text, 'ventas de hoy vs ayer');
    }

    protected function extractComparisonFilters(string $text): array
    {
        $shift = null;
        if (preg_match('/\b(mañana|manana)\b/u', $text)) {
            $shift = 'morning';
        } elseif (preg_match('/\b(tarde)\b/u', $text)) {
            $shift = 'afternoon';
        } elseif (preg_match('/\b(noche)\b/u', $text)) {
            $shift = 'night';
        }

        $compareTo = 'yesterday';
        $days = [
            'lunes' => 'previous_monday',
            'martes' => 'previous_tuesday',
            'miercoles' => 'previous_wednesday',
            'miércoles' => 'previous_wednesday',
            'jueves' => 'previous_thursday',
            'viernes' => 'previous_friday',
            'sabado' => 'previous_saturday',
            'sábado' => 'previous_saturday',
            'domingo' => 'previous_sunday',
        ];

        foreach ($days as $spanishDay => $slug) {
            if (str_contains($text, $spanishDay)) {
                $compareTo = $slug;
                break;
            }
        }

        if (str_contains($text, 'semana pasada') || str_contains($text, 'semana anterior')) {
            $compareTo = 'last_week';
        } elseif (str_contains($text, 'mes pasado') || str_contains($text, 'mes anterior')) {
            $compareTo = 'last_month';
        } elseif (str_contains($text, 'ayer')) {
            $compareTo = 'yesterday';
        }

        return [
            'shift' => $shift,
            'target_date' => 'today',
            'compare_to' => $compareTo,
        ];
    }

    protected function isProductMargins(string $text): bool
    {
        return (bool) preg_match('/\b(m[aá]rgen|rentabilidad|rentables?|ganancia)\b/u', $text);
    }

    protected function extractMarginFilters(string $text, ?string $businessType): array
    {
        $order = 'asc';
        if (preg_match('/\b(mayor|alt[oa]s?|m[aá]s\s+rentables?|mejores)\b/u', $text)) {
            $order = 'desc';
        }

        $limit = $this->extractLimit($text, 5);

        $productType = null;
        if (preg_match('/\b(platos?|men[uú]|comida)\b/u', $text) || $businessType === 'restaurant') {
            $productType = 'dish';
        }

        return [
            'order' => $order,
            'limit' => $limit,
            'product_type' => $productType,
        ];
    }

    protected function isTopSelling(string $text): bool
    {
        return (bool) preg_match('/\b(m[aá]s\s+vendidos?|top\s+ventas|producto\s+estrella|top\s+\d+)\b/u', $text);
    }

    protected function isLowStock(string $text): bool
    {
        return (bool) preg_match('/\b(bajo\s+stock|stock\s+bajo|stock\s+cr[ií]tico|cr[ií]tico|reposici[oó]n|por\s+agotarse|pedir\s+al\s+proveedor|proveedor)\b/u', $text);
    }

    protected function isOutOfStock(string $text): bool
    {
        return (bool) preg_match('/\b(agotados?|sin\s+stock|stock\s+cero|cero\s+existencias?)\b/u', $text);
    }

    protected function isInventorySummary(string $text): bool
    {
        return (bool) preg_match('/\b(valor\s+(total\s+)?(del\s+)?inventario|resumen\s+de\s+inventario|cu[aá]nto\s+vale\s+el\s+inventario|valoraci[oó]n)\b/u', $text);
    }

    protected function isSalesSummary(string $text): bool
    {
        return (bool) preg_match('/\b(ventas\s+de\s+hoy|resumen\s+de\s+ventas|ventas\s+del\s+mes|balance\s+de\s+ventas|cu[aá]nto\s+vendimos)\b/u', $text);
    }

    protected function containsRestaurantKeywords(string $text): bool
    {
        return (bool) preg_match('/\b(mesas?|comandas?|domicilios?|propinas?)\b/u', $text);
    }

    protected function extractLimit(string $text, int $default = 5): int
    {
        if (preg_match('/\b(\d+)\b/', $text, $matches)) {
            $num = (int) $matches[1];
            if ($num >= 1 && $num <= 20) {
                return $num;
            }
        }

        return $default;
    }
}
