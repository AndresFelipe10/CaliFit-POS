<?php

namespace App\AI\Services;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiQueryResult;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;
use App\AI\Tools\CustomerAiTools;
use App\AI\Tools\InventoryAiTools;
use App\AI\Tools\ProductAiTools;
use App\AI\Tools\RestaurantAiTools;
use App\AI\Tools\SaleAiTools;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiQueryService
{
    public const FALLBACK_MESSAGE = 'El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de inventario, ventas y reportes.';

    public function __construct(
        protected AiProviderInterface $provider,
        protected CustomerAiTools $customerTools,
        protected ProductAiTools $productTools,
        protected InventoryAiTools $inventoryTools,
        protected SaleAiTools $saleTools,
        protected RestaurantAiTools $restaurantTools
    ) {
    }

    /**
     * Process a natural language query with strict whitelist, read-only enforcement, conversational memory, and fallback.
     *
     * @param string $userQuery
     * @param mixed $history
     * @return AiQueryResult
     */
    public function query(string $userQuery, mixed $history = []): AiQueryResult
    {
        $historyArray = is_array($history) ? $history : [];

        // 1. Verificación de módulo activado en .env / configuración
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

        // 2. Bloqueo determinístico contra intentos de mutación o inyección SQL
        if ($this->containsMutationAttempt($cleanQuery)) {
            return new AiQueryResult(
                intent: 'rejected_mutation',
                summary: 'El asistente de IA opera exclusivamente en modo de solo lectura. Queda prohibida cualquier instrucción de inserción, modificación, eliminación o alteración de datos.',
                data: [],
                meta: ['security_violation' => 'mutation_blocked'],
                isSuccess: false
            );
        }

        // 3. Detección rápida de saludos y aperturas conversacionales
        if ($this->isGreeting($cleanQuery)) {
            return new AiQueryResult(
                intent: 'greeting',
                summary: '¡Hola! Soy tu asistente de consultas de SaborCaliFit. Puedes preguntarme sobre insumos con bajo stock o agotados, suplementos deportivos, balance de ventas por canal, comandas activas o arqueo de caja. ¿Qué te gustaría consultar?',
                data: [],
                meta: ['type' => 'conversational'],
                isSuccess: true
            );
        }

        // 3.5 Detección determinística de preguntas de seguimiento ("cuales son", "quiénes son", etc.)
        if ($this->isFollowUpQuery($cleanQuery)) {
            $lastTool = $this->resolveLastToolFromHistory($historyArray);
            if ($lastTool === AiIntent::COUNT_CUSTOMERS || $lastTool === AiIntent::LIST_CUSTOMERS || str_contains((string) $lastTool, 'customer')) {
                $result = $this->customerTools->list();
                session(['ai_last_intent' => AiIntent::LIST_CUSTOMERS]);
                return $result;
            }
            if ($lastTool === AiIntent::SALES_SUMMARY || $lastTool === AiIntent::SALES_BY_PERIOD || $lastTool === AiIntent::SALES_BY_CHANNEL) {
                $result = $this->saleTools->byPeriod();
                session(['ai_last_intent' => AiIntent::SALES_BY_PERIOD]);
                return $result;
            }
            if ($lastTool === AiIntent::LOW_STOCK_INGREDIENTS || $lastTool === AiIntent::OUT_OF_STOCK_INGREDIENTS) {
                $result = $this->inventoryTools->lowStockIngredients();
                session(['ai_last_intent' => AiIntent::LOW_STOCK_INGREDIENTS]);
                return $result;
            }
            if ($lastTool === AiIntent::ACTIVE_ORDERS) {
                $result = $this->restaurantTools->activeOrders();
                session(['ai_last_intent' => AiIntent::ACTIVE_ORDERS]);
                return $result;
            }
        }

        // 4. Extracción de intención mediante el proveedor (con captura de timeouts/errores)
        try {
            $extractedIntent = $this->provider->extractIntent($cleanQuery, $historyArray);
        } catch (AiProviderException | Throwable $e) {
            $sanitizedMessage = preg_replace('/(\?|&)(key|apiKey|api_key|token)=[^&\s]+/i', '$1$2=[REDACTED]', $e->getMessage());
            $sanitizedMessage = preg_replace('/https?:\/\/[^\s]+/i', '[URL]', $sanitizedMessage);

            Log::warning("Falla en proveedor de IA: {$sanitizedMessage}", [
                'query' => $cleanQuery,
            ]);

            return new AiQueryResult(
                intent: 'fallback',
                summary: self::FALLBACK_MESSAGE,
                data: [],
                meta: ['error' => 'provider_unavailable'],
                isSuccess: false
            );
        }

        $intent = $extractedIntent->intent;
        $filters = $extractedIntent->filters;

        // 5. Verificación estricta contra Whitelist inmutable
        if (!AiIntent::isValid($intent)) {
            return new AiQueryResult(
                intent: 'unauthorized',
                summary: 'La consulta solicitada no corresponde a una operación de lectura permitida en SaborCaliFit. Puedo ayudarte con información sobre insumos, suplementos, ventas, comandas, clientes y caja.',
                data: [],
                meta: ['attempted_intent' => $intent],
                isSuccess: false
            );
        }

        // 6. Despacho a herramienta de solo lectura exclusiva de SaborCaliFit
        $result = match ($intent) {
            AiIntent::LOW_STOCK_INGREDIENTS => $this->inventoryTools->lowStockIngredients(),
            AiIntent::OUT_OF_STOCK_INGREDIENTS => $this->inventoryTools->outOfStockIngredients(),
            AiIntent::LOW_STOCK_SUPPLEMENTS => $this->inventoryTools->lowStockSupplements(),
            AiIntent::OUT_OF_STOCK_SUPPLEMENTS => $this->inventoryTools->outOfStockSupplements(),
            AiIntent::LOW_STOCK_PRODUCTS => $this->inventoryTools->lowStock(),
            AiIntent::OUT_OF_STOCK_PRODUCTS => $this->inventoryTools->outOfStock(),
            AiIntent::INVENTORY_SUMMARY => $this->inventoryTools->summary(),
            AiIntent::SALES_SUMMARY => $this->saleTools->summary($filters),
            AiIntent::SALES_BY_PERIOD => $this->saleTools->byPeriod($filters),
            AiIntent::SALES_BY_CHANNEL => $this->saleTools->byChannel($filters),
            AiIntent::TOP_SELLING_PRODUCTS => $this->saleTools->topSelling($filters),
            AiIntent::ACTIVE_ORDERS => $this->restaurantTools->activeOrders(),
            AiIntent::DAILY_CASH_SUMMARY => $this->restaurantTools->dailyCashSummary(),
            AiIntent::LIST_CUSTOMERS => $this->customerTools->list($filters),
            AiIntent::COUNT_CUSTOMERS => $this->customerTools->count(),
            AiIntent::LIST_PRODUCTS => $this->productTools->list($filters),
            default => new AiQueryResult(
                intent: 'unauthorized',
                summary: 'Operación no autorizada.',
                data: [],
                isSuccess: false
            ),
        };

        if ($result->isSuccess) {
            session(['ai_last_intent' => $intent]);
        }

        return $result;
    }

    /**
     * Resolve the last tool / topic used from conversation history or session.
     */
    protected function resolveLastToolFromHistory(array $history): ?string
    {
        if (!empty($history)) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                if (($history[$i]['role'] ?? '') === 'assistant' && !empty($history[$i]['tool_used'])) {
                    return $history[$i]['tool_used'];
                }
            }
        }

        return session()->get('ai_last_intent');
    }

    /**
     * Detect if a user message is a short follow-up question.
     */
    protected function isFollowUpQuery(string $text): bool
    {
        $clean = mb_strtolower(trim($text, " ¡!¿?.,;"));
        $followUpPhrases = [
            'cuales son', 'cuáles son', 'quienes son', 'quiénes son',
            'cuales', 'cuáles', 'quienes', 'quiénes',
            'muestramelos', 'muéstramelos', 'muestramelas', 'muéstramelas',
            'dame la lista', 'dame el listado', 'ver lista', 'ver listado',
            'detalle', 'mostrar detalle', 'muestrame el detalle', 'muéstrame el detalle',
            'lista', 'listado', 'y cuales son', 'y cuáles son', 'y quienes son', 'y quiénes son',
            'dame los nombres', 'quienes estan', 'quiénes están',
        ];

        return in_array($clean, $followUpPhrases, true);
    }

    /**
     * Check if user query contains keywords indicating mutation or SQL execution attempts.
     */
    protected function containsMutationAttempt(string $text): bool
    {
        $patterns = [
            '/\b(insert|update|delete|drop|alter|truncate|replace|create|grant|revoke)\b/i',
            '/\b(eliminar|borrar|actualizar|modificar|insertar|crear\s+tabla|cambiar\s+precio)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
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

        return (bool) preg_match('/^(hola|buenos\s*d[ií]as|buenas\s*tardes|buenas\s*noches|saludos)\b/i', $normalized);
    }
}
