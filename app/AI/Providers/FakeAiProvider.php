<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiQueryIntent;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;

class FakeAiProvider implements AiProviderInterface
{
    protected ?AiQueryIntent $nextIntent = null;
    protected ?AiProviderException $nextException = null;

    /**
     * Queue a specific intent for the next query.
     */
    public function setNextIntent(string $intent, array $filters = []): self
    {
        $this->nextIntent = new AiQueryIntent($intent, $filters, 1.0, 'fake_response');
        $this->nextException = null;
        return $this;
    }

    /**
     * Queue a specific exception for the next query.
     */
    public function setException(AiProviderException $exception): self
    {
        $this->nextException = $exception;
        $this->nextIntent = null;
        return $this;
    }

    /**
     * Extract intent either from queued setup or deterministic keyword fallback.
     */
    public function extractIntent(string $userQuery): AiQueryIntent
    {
        if ($this->nextException) {
            $exception = $this->nextException;
            $this->nextException = null;
            throw $exception;
        }

        if ($this->nextIntent) {
            $intent = $this->nextIntent;
            $this->nextIntent = null;
            return $intent;
        }

        $query = mb_strtolower($userQuery, 'UTF-8');

        if (str_contains($query, 'cuantos clientes') || str_contains($query, 'cuántos clientes') || str_contains($query, 'total de clientes')) {
            return new AiQueryIntent(AiIntent::COUNT_CUSTOMERS);
        }

        if (str_contains($query, 'turno') || (str_contains($query, 'comparad') && str_contains($query, 'ventas')) || str_contains($query, 'hoy vs ayer')) {
            $shift = str_contains($query, 'tarde') ? 'afternoon' : (str_contains($query, 'mañana') || str_contains($query, 'manana') ? 'morning' : (str_contains($query, 'noche') ? 'night' : null));
            $compareTo = str_contains($query, 'martes') ? 'previous_tuesday' : (str_contains($query, 'ayer') ? 'yesterday' : 'last_week');
            return new AiQueryIntent(AiIntent::SALES_COMPARISON, [
                'shift' => $shift,
                'target_date' => 'today',
                'compare_to' => $compareTo,
            ]);
        }

        if (str_contains($query, 'margen') || str_contains($query, 'rentab')) {
            preg_match('/\b(\d+)\b/', $query, $m);
            $limit = isset($m[1]) ? (int) $m[1] : 5;
            $order = (str_contains($query, 'mayor') || str_contains($query, 'mas rentab') || str_contains($query, 'más rentab')) ? 'desc' : 'asc';
            $type = (str_contains($query, 'plato') || str_contains($query, 'comida')) ? 'dish' : null;
            return new AiQueryIntent(AiIntent::PRODUCT_MARGINS, [
                'order' => $order,
                'limit' => $limit,
                'product_type' => $type,
            ]);
        }

        if (str_contains($query, 'cliente')) {
            return new AiQueryIntent(AiIntent::LIST_CUSTOMERS);
        }

        if (str_contains($query, 'agotad') || str_contains($query, 'sin stock')) {
            return new AiQueryIntent(AiIntent::OUT_OF_STOCK_PRODUCTS);
        }

        if (str_contains($query, 'bajo stock') || str_contains($query, 'stock bajo') || str_contains($query, 'reponer') || str_contains($query, 'critico')) {
            return new AiQueryIntent(AiIntent::LOW_STOCK_PRODUCTS);
        }

        if (str_contains($query, 'mas vendido') || str_contains($query, 'más vendido') || str_contains($query, 'top')) {
            return new AiQueryIntent(AiIntent::TOP_SELLING_PRODUCTS);
        }

        if (str_contains($query, 'ventas de hoy') || str_contains($query, 'ventas del mes') || str_contains($query, 'resumen de ventas') || str_contains($query, 'ventas de ayer')) {
            $period = str_contains($query, 'ayer') ? 'yesterday' : (str_contains($query, 'mes') ? 'month' : 'today');
            return new AiQueryIntent(AiIntent::SALES_SUMMARY, ['period' => $period]);
        }

        if (str_contains($query, 'ventas')) {
            return new AiQueryIntent(AiIntent::SALES_BY_PERIOD);
        }

        if (str_contains($query, 'mesa')) {
            return new AiQueryIntent(AiIntent::RESTAURANT_TABLES_STATUS);
        }

        if (str_contains($query, 'comanda') || str_contains($query, 'pedidos abiertos') || str_contains($query, 'ordenes abiertas')) {
            return new AiQueryIntent(AiIntent::RESTAURANT_ORDERS_OPEN);
        }

        if (str_contains($query, 'domicilio')) {
            return new AiQueryIntent(AiIntent::RESTAURANT_DELIVERIES_TODAY);
        }

        if (str_contains($query, 'propina') || str_contains($query, 'servicio voluntario')) {
            return new AiQueryIntent(AiIntent::RESTAURANT_TIPS_COLLECTED);
        }

        if (str_contains($query, 'proveedor')) {
            return new AiQueryIntent(AiIntent::LIST_SUPPLIERS);
        }

        if (str_contains($query, 'gasto')) {
            return new AiQueryIntent(AiIntent::EXPENSES_SUMMARY);
        }

        if (str_contains($query, 'inventario') || str_contains($query, 'valoracion') || str_contains($query, 'valoración')) {
            return new AiQueryIntent(AiIntent::INVENTORY_SUMMARY);
        }

        if (str_contains($query, 'producto') || str_contains($query, 'catalogo') || str_contains($query, 'catálogo') || str_contains($query, 'plato')) {
            return new AiQueryIntent(AiIntent::LIST_PRODUCTS);
        }

        // Si no coincide con nada, intent no reconocido
        return new AiQueryIntent('unknown_intent');
    }
}
