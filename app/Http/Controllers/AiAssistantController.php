<?php

namespace App\Http\Controllers;

use App\AI\Services\AiQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    /**
     * Show the natural language query assistant interface for SaborCaliFit.
     */
    public function index(): View
    {
        $suggestions = [
            '¿Qué insumos de cocina tienen bajo stock o requieren reposición?',
            '¿Cuáles insumos de cocina están completamente agotados?',
            '¿Qué suplementos deportivos tienen bajo stock?',
            '¿Cuáles son los suplementos agotados en mostrador?',
            '¿Cuáles son los platos y suplementos más vendidos?',
            'Resumen de ventas de hoy',
            'Balance de ventas por canal hoy (salón vs domicilios vs mostrador)',
            '¿Cuántas comandas u órdenes activas hay en el restaurante?',
            'Resumen del cuadre de caja de hoy',
            '¿Cuántos clientes activos tenemos registrados?',
            '¿Cuál es la valoración estimada de nuestro inventario?',
        ];

        return view('ai.index', [
            'enabled' => (bool) config('ai.enabled', false),
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Process a natural language question and return structured response with multi-turn session memory.
     */
    public function ask(Request $request, AiQueryService $aiQueryService): JsonResponse
    {
        $rawQuery = $request->input('query') ?? $request->input('question') ?? $request->input('message');
        if (empty($rawQuery) || !is_string($rawQuery)) {
            return response()->json([
                'success' => false,
                'message' => 'La consulta es requerida.',
                'errors' => ['query' => ['El campo de consulta es obligatorio.']],
            ], 422);
        }

        $queryText = mb_substr(trim($rawQuery), 0, 500);
        $history = session()->get('ai_conversation_history', []);
        $historyArray = is_array($history) ? $history : [];

        $result = $aiQueryService->query($queryText, $historyArray);

        // Actualizar memoria conversacional en sesión
        $historyArray[] = [
            'role' => 'user',
            'content' => $queryText,
        ];
        $historyArray[] = [
            'role' => 'assistant',
            'tool_used' => $result->intent,
            'summary' => $result->summary,
        ];

        if (count($historyArray) > 10) {
            $historyArray = array_slice($historyArray, -10);
        }

        session()->put('ai_conversation_history', $historyArray);

        return response()->json($result->toArray());
    }

    /**
     * Clear the conversational history stored in session.
     */
    public function clear(): JsonResponse
    {
        session()->forget('ai_conversation_history');
        session()->forget('ai_last_intent');

        return response()->json([
            'success' => true,
            'message' => 'Conversación e historial reiniciados exitosamente.',
        ]);
    }
}
