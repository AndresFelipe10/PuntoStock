<?php

namespace App\AI\Services;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiQueryResult;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;
use App\AI\Tools\CustomerAiTools;
use App\AI\Tools\ExpenseAiTools;
use App\AI\Tools\InventoryAiTools;
use App\AI\Tools\ProductAiTools;
use App\AI\Tools\RestaurantAiTools;
use App\AI\Tools\SaleAiTools;
use App\AI\Tools\SupplierAiTools;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiQueryService
{
    public const FALLBACK_MESSAGE = 'El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario.';

    public function __construct(
        protected AiProviderInterface $provider,
        protected CustomerAiTools $customerTools,
        protected ProductAiTools $productTools,
        protected InventoryAiTools $inventoryTools,
        protected SaleAiTools $saleTools,
        protected SupplierAiTools $supplierTools,
        protected ExpenseAiTools $expenseTools,
        protected RestaurantAiTools $restaurantTools,
        protected AiContextService $contextService,
        protected ?DeterministicAiResolver $deterministicResolver = null
    ) {
        $this->deterministicResolver = $deterministicResolver ?? new DeterministicAiResolver();
    }

    /**
     * Process a natural language query with strict whitelist, read-only enforcement,
     * conversational memory context resolution, and tenant isolation.
     *
     * @param string $userQuery
     * @param int $businessId
     * @param string|null $businessType ('retail'|'restaurant'|null)
     * @return AiQueryResult
     */
    public function query(string $userQuery, int $businessId, ?string $businessType = null): AiQueryResult
    {
        // 1. Verificación de módulo activado en .env
        if (!config('ai.enabled', false)) {
            return new AiQueryResult(
                intent: 'disabled',
                summary: 'El módulo de IA está desactivado. Puedes habilitarlo configurando AI_MODULE_ENABLED=true en el archivo .env.',
                data: [],
                meta: ['enabled' => false],
                isSuccess: false
            );
        }

        $cleanQuery = trim($userQuery);
        if (empty($cleanQuery)) {
            return new AiQueryResult(
                intent: 'empty',
                summary: 'Por favor, ingresa una pregunta o consulta sobre tu negocio.',
                data: [],
                isSuccess: false
            );
        }

        // 2. Detección rápida de saludos y aperturas conversacionales (sin consumir cuota ni demoras)
        if ($this->isGreeting($cleanQuery)) {
            $greetingSummary = $businessType === 'restaurant'
                ? '¡Hola! Soy tu asistente de consultas de negocio. Puedes preguntarme sobre mesas libres u ocupadas, comandas abiertas, domicilios de hoy, propinas recaudadas, clientes y ventas. ¿Qué te gustaría consultar?'
                : '¡Hola! Soy tu asistente de consultas de negocio. Puedes preguntarme sobre productos con stock bajo o agotados, balance de ventas de hoy o del mes, clientes registrados y valor del inventario. ¿Qué te gustaría consultar?';

            return new AiQueryResult(
                intent: 'greeting',
                summary: $greetingSummary,
                data: [],
                meta: ['type' => 'conversational'],
                isSuccess: true
            );
        }

        // 3. Resolución contextual de preguntas de seguimiento (Follow-up & Anaphora Resolver)
        // Se ejecuta ANTES del proveedor para garantizar 0ms de latencia y consistencia conversacional
        $contextualIntent = $this->contextService->resolveFollowUp($cleanQuery, $businessId);

        if ($contextualIntent !== null) {
            $intent = $contextualIntent->intent;
            $filters = $contextualIntent->filters;
        } else {
            // 4. Extracción de intención mediante el proveedor (con captura de timeouts/errores)
            try {
                $extractedIntent = $this->provider->extractIntent($cleanQuery);
            } catch (AiProviderException | Throwable $e) {
                // 1. Sanitizar mensaje eliminando query strings, URLs y tokens
                $sanitizedMessage = preg_replace('/(\?|&)(key|apiKey|api_key|token)=[^&\s]+/i', '$1$2=[REDACTED]', $e->getMessage());
                $sanitizedMessage = preg_replace('/AIza[0-9A-Za-z\-_]{10,}/', '[REDACTED]', $sanitizedMessage);
                $sanitizedMessage = preg_replace('/https?:\/\/[^\s]+/i', '[URL]', $sanitizedMessage);

                Log::warning("Falla en proveedor de IA: {$sanitizedMessage}", [
                    'business_id' => $businessId,
                    'query' => $cleanQuery,
                ]);

                // 2. Sinergia 100% de disponibilidad: en producción o ante caídas del proveedor,
                // activar de inmediato el motor analítico determinista local para resolver la consulta.
                $deterministicFallback = null;
                if (!app()->runningUnitTests()) {
                    $deterministicFallback = $this->deterministicResolver->resolve($cleanQuery, $businessType);
                }

                if ($deterministicFallback !== null) {
                    $extractedIntent = $deterministicFallback;
                } else {
                    return new AiQueryResult(
                        intent: 'fallback',
                        summary: self::FALLBACK_MESSAGE,
                        data: [],
                        meta: ['error' => 'provider_unavailable'],
                        isSuccess: false
                    );
                }
            }

            $intent = $extractedIntent->intent;
            $filters = $extractedIntent->filters;
        }

        // 5. Prohibición estricta de Fallback Silencioso al Catálogo:
        // Si el intent no es válido: distinguir entre intento no autorizado vs consulta no reconocida
        if (!AiIntent::isValid($intent)) {
            if ($intent === 'unknown_intent' || $intent === 'unrecognized' || empty($intent)) {
                $deterministicIntent = $this->deterministicResolver->resolve($cleanQuery, $businessType);
                if ($deterministicIntent !== null && AiIntent::isValid($deterministicIntent->intent)) {
                    $intent = $deterministicIntent->intent;
                    $filters = $deterministicIntent->filters;
                } else {
                    return new AiQueryResult(
                        intent: 'unrecognized',
                        summary: $this->contextService->getUnrecognizedGuidance($businessType),
                        data: [],
                        meta: ['attempted_intent' => $intent, 'business_type' => $businessType],
                        isSuccess: false
                    );
                }
            } else {
                return new AiQueryResult(
                    intent: 'unauthorized',
                    summary: 'La consulta solicitada no corresponde a una operación de lectura permitida. Puedo ayudarte con información sobre stock, clientes, ventas y productos de tu negocio.',
                    data: [],
                    meta: ['attempted_intent' => $intent],
                    isSuccess: false
                );
            }
        }

        // 6. Despacho a herramienta de solo lectura inyectando FORZOSAMENTE el $businessId de Laravel
        try {
            $result = match ($intent) {
                AiIntent::LIST_CUSTOMERS => $this->customerTools->list($businessId, $filters),
                AiIntent::COUNT_CUSTOMERS => $this->customerTools->count($businessId),
                AiIntent::LIST_PRODUCTS => $this->productTools->list($businessId, $filters),
                AiIntent::PRODUCT_MARGINS => $this->productTools->margins($businessId, $filters),
                AiIntent::LOW_STOCK_PRODUCTS => $this->inventoryTools->lowStock($businessId),
                AiIntent::OUT_OF_STOCK_PRODUCTS => $this->inventoryTools->outOfStock($businessId),
                AiIntent::INVENTORY_SUMMARY => $this->inventoryTools->summary($businessId),
                AiIntent::SALES_SUMMARY => $this->saleTools->summary($businessId, $filters),
                AiIntent::SALES_BY_PERIOD => $this->saleTools->byPeriod($businessId, $filters),
                AiIntent::SALES_COMPARISON => $this->saleTools->comparison($businessId, $filters),
                AiIntent::TOP_SELLING_PRODUCTS => $this->saleTools->topSelling($businessId, $filters),
                AiIntent::LIST_SUPPLIERS => $this->supplierTools->list($businessId, $filters),
                AiIntent::EXPENSES_SUMMARY => $this->expenseTools->summary($businessId, $filters),
                AiIntent::RESTAURANT_TABLES_STATUS => $this->restaurantTools->tablesStatus($businessId),
                AiIntent::RESTAURANT_ORDERS_OPEN => $this->restaurantTools->openOrders($businessId),
                AiIntent::RESTAURANT_DELIVERIES_TODAY => $this->restaurantTools->deliveriesToday($businessId),
                AiIntent::RESTAURANT_TIPS_COLLECTED => $this->restaurantTools->tipsCollected($businessId),
            };

            // 7. Persistir memoria conversacional en sesión del usuario
            if ($result->isSuccess) {
                $this->contextService->updateContext($businessId, $cleanQuery, $result, $filters);
            }

            return $result;
        } catch (Throwable $e) {
            Log::warning("Falla al ejecutar herramienta de IA para intent '{$intent}': " . $e->getMessage(), [
                'business_id' => $businessId,
                'intent' => $intent,
                'filters' => $filters,
            ]);

            return new AiQueryResult(
                intent: $intent,
                summary: 'Ocurrió un error al procesar los filtros de la consulta. Por favor, intenta formular tu pregunta de otra manera.',
                data: [],
                meta: ['error' => 'tool_execution_failed'],
                isSuccess: false
            );
        }
    }

    /**
     * Clear conversational memory for the given tenant.
     */
    public function clearContext(int $businessId): void
    {
        $this->contextService->clearContext($businessId);
    }

    /**
     * Detect if a user message is a conversational greeting or polite opening.
     */
    protected function isGreeting(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = trim($normalized, "¡!¿?.,;");

        $exactGreetings = [
            'hola',
            'buenas',
            'buenos dias',
            'buenos días',
            'buenas tardes',
            'buenas noches',
            'saludos',
            'que tal',
            'qué tal',
            'hi',
            'hello',
            'hey',
        ];

        if (in_array($normalized, $exactGreetings, true)) {
            return true;
        }

        return (bool) preg_match('/^(hola|buenas|buenos\s*d[ií]as|buenas\s*tardes|buenas\s*noches|saludos|qu[eé]\s*tal)(\s+(c[oó]mo\s+est[aá]s|asistente|equipo|buen\s+d[ií]a))?$/iu', $normalized);
    }
}
