<?php

namespace App\Http\Controllers;

use App\AI\Services\AiQueryService;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    /**
     * Show the natural language query assistant interface.
     */
    public function index(Request $request): View
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);
        $business = Business::find($businessId);

        if ($business && $business->isRestaurant()) {
            $suggestions = [
                '¿Cuánto vendimos en el turno de la tarde comparado con el martes pasado?',
                '¿Cuáles son los 3 platos con menor margen de ganancia?',
                '¿Cuál es el estado de las mesas en el salón?',
                '¿Cuáles son las comandas abiertas en este momento?',
                '¿Cuántos pedidos a domicilio tenemos hoy?',
                '¿Cuánto se ha recaudado hoy en propinas?',
                '¿Cuáles son los productos más vendidos?',
                '¿Qué productos tienen bajo stock o requieren reposición?',
                'Resumen de ventas de hoy',
            ];
        } else {
            $suggestions = [
                'Ventas de hoy vs ayer',
                '¿Cuánto vendimos en el turno de la tarde comparado con la semana pasada?',
                '¿Cuáles son los 3 productos con menor margen de ganancia?',
                '¿Qué productos tienen bajo stock o requieren reposición?',
                '¿Cuáles son los productos completamente agotados?',
                '¿Cuáles son los productos más vendidos?',
                'Resumen de ventas de hoy',
                '¿Cuál es el valor total de nuestro inventario?',
            ];
        }

        return view('ai.index', [
            'enabled' => (bool) config('ai.enabled', false),
            'suggestions' => $suggestions,
            'business' => $business,
        ]);
    }

    /**
     * Process a natural language question and return structured response.
     */
    public function ask(Request $request, AiQueryService $aiQueryService): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
        ]);

        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);
        $business = Business::find($businessId);
        $businessType = $business?->business_type;

        $result = $aiQueryService->query($validated['query'], $businessId, $businessType);

        return response()->json($result->toArray());
    }

    /**
     * Clear conversational memory for the current tenant.
     */
    public function clearContext(Request $request, AiQueryService $aiQueryService): JsonResponse|RedirectResponse
    {
        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);
        $aiQueryService->clearContext($businessId);
        session()->forget(['ai_chat_history', 'ai_context']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Memoria conversacional reiniciada exitosamente.',
            ]);
        }

        return back()->with('status', 'Contexto del asistente reiniciado exitosamente.');
    }
}
