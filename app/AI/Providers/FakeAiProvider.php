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
     * Extract intent either from queued setup or deterministic keyword fallback with conversational memory.
     */
    public function extractIntent(string $userQuery, array $history = []): AiQueryIntent
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

        $query = mb_strtolower(trim($userQuery), 'UTF-8');
        $cleanFollowUp = trim($query, " ¡!¿?.,;");

        // Detección de preguntas de seguimiento conversacional
        $followUpPhrases = [
            'cuales son', 'cuáles son', 'quienes son', 'quiénes son',
            'cuales', 'cuáles', 'quienes', 'quiénes',
            'muestramelos', 'muéstramelos', 'muestramelas', 'muéstramelas',
            'dame la lista', 'dame el listado', 'ver lista', 'ver listado',
            'detalle', 'mostrar detalle', 'muestrame el detalle', 'muéstrame el detalle',
            'lista', 'listado', 'y cuales son', 'y cuáles son', 'y quienes son', 'y quiénes son',
        ];

        if (in_array($cleanFollowUp, $followUpPhrases, true)) {
            // Revisar el último turno del asistente en el historial
            $lastTool = null;
            if (!empty($history)) {
                for ($i = count($history) - 1; $i >= 0; $i--) {
                    if (($history[$i]['role'] ?? '') === 'assistant' && !empty($history[$i]['tool_used'])) {
                        $lastTool = $history[$i]['tool_used'];
                        break;
                    }
                }
            }

            if (!$lastTool && session()->has('ai_last_intent')) {
                $lastTool = session()->get('ai_last_intent');
            }

            if ($lastTool === AiIntent::COUNT_CUSTOMERS || $lastTool === AiIntent::LIST_CUSTOMERS || str_contains((string) $lastTool, 'customer')) {
                return new AiQueryIntent(AiIntent::LIST_CUSTOMERS);
            }

            if ($lastTool === AiIntent::LOW_STOCK_INGREDIENTS || $lastTool === AiIntent::OUT_OF_STOCK_INGREDIENTS) {
                return new AiQueryIntent($lastTool);
            }

            if ($lastTool === AiIntent::SALES_SUMMARY || $lastTool === AiIntent::SALES_BY_PERIOD || $lastTool === AiIntent::SALES_BY_CHANNEL) {
                return new AiQueryIntent(AiIntent::SALES_BY_PERIOD);
            }

            if ($lastTool === AiIntent::ACTIVE_ORDERS) {
                return new AiQueryIntent(AiIntent::ACTIVE_ORDERS);
            }

            // Por defecto si la última consulta fue clientes o no se especifica, asociar a clientes
            return new AiQueryIntent(AiIntent::LIST_CUSTOMERS);
        }

        // Clientes
        if (str_contains($query, 'cuantos clientes') || str_contains($query, 'cuántos clientes') || str_contains($query, 'total de clientes')) {
            return new AiQueryIntent(AiIntent::COUNT_CUSTOMERS);
        }

        if (str_contains($query, 'cliente')) {
            return new AiQueryIntent(AiIntent::LIST_CUSTOMERS);
        }

        // Insumos y Materias Primas
        if ((str_contains($query, 'insumo') || str_contains($query, 'ingrediente')) && (str_contains($query, 'bajo') || str_contains($query, 'reponer'))) {
            return new AiQueryIntent(AiIntent::LOW_STOCK_INGREDIENTS);
        }

        if ((str_contains($query, 'insumo') || str_contains($query, 'ingrediente')) && (str_contains($query, 'agotad') || str_contains($query, 'cero') || str_contains($query, 'sin stock'))) {
            return new AiQueryIntent(AiIntent::OUT_OF_STOCK_INGREDIENTS);
        }

        // Suplementos
        if ((str_contains($query, 'suplemento') || str_contains($query, 'proteina') || str_contains($query, 'creatina')) && (str_contains($query, 'bajo') || str_contains($query, 'reponer'))) {
            return new AiQueryIntent(AiIntent::LOW_STOCK_SUPPLEMENTS);
        }

        if ((str_contains($query, 'suplemento') || str_contains($query, 'proteina') || str_contains($query, 'creatina')) && (str_contains($query, 'agotad') || str_contains($query, 'sin stock'))) {
            return new AiQueryIntent(AiIntent::OUT_OF_STOCK_SUPPLEMENTS);
        }

        // Stock general
        if (str_contains($query, 'agotad') || str_contains($query, 'sin stock')) {
            return new AiQueryIntent(AiIntent::OUT_OF_STOCK_PRODUCTS);
        }

        if (str_contains($query, 'bajo stock') || str_contains($query, 'stock bajo') || str_contains($query, 'critico') || str_contains($query, 'reponer')) {
            return new AiQueryIntent(AiIntent::LOW_STOCK_PRODUCTS);
        }

        // Ventas por canal
        if (str_contains($query, 'canal') || str_contains($query, 'domicilio') || str_contains($query, 'salon') || str_contains($query, 'salón') || str_contains($query, 'mesas')) {
            return new AiQueryIntent(AiIntent::SALES_BY_CHANNEL);
        }

        // Comandas y Restaurante
        if (str_contains($query, 'comanda') || str_contains($query, 'ordenes activas') || str_contains($query, 'órdenes activas') || str_contains($query, 'mesas ocupadas')) {
            return new AiQueryIntent(AiIntent::ACTIVE_ORDERS);
        }

        // Cuadre de caja
        if (str_contains($query, 'cuadre de caja') || str_contains($query, 'caja de hoy') || str_contains($query, 'arqueo') || str_contains($query, 'efectivo en caja')) {
            return new AiQueryIntent(AiIntent::DAILY_CASH_SUMMARY);
        }

        // Productos más vendidos
        if (str_contains($query, 'mas vendido') || str_contains($query, 'más vendido') || str_contains($query, 'top')) {
            return new AiQueryIntent(AiIntent::TOP_SELLING_PRODUCTS);
        }

        // Resumen de ventas
        if (str_contains($query, 'ventas de hoy') || str_contains($query, 'ventas del mes') || str_contains($query, 'resumen de ventas')) {
            return new AiQueryIntent(AiIntent::SALES_SUMMARY);
        }

        if (str_contains($query, 'ventas')) {
            return new AiQueryIntent(AiIntent::SALES_BY_PERIOD);
        }

        // Inventario valoración
        if (str_contains($query, 'inventario') || str_contains($query, 'valoracion') || str_contains($query, 'valoración')) {
            return new AiQueryIntent(AiIntent::INVENTORY_SUMMARY);
        }

        // Productos catálogo
        if (str_contains($query, 'producto') || str_contains($query, 'catalogo') || str_contains($query, 'catálogo') || str_contains($query, 'plato')) {
            return new AiQueryIntent(AiIntent::LIST_PRODUCTS);
        }

        return new AiQueryIntent('unknown_intent');
    }
}
