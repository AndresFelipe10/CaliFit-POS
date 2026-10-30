<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintAgentToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('services.print.token');
        $providedToken   = (string) $request->header('X-Print-Token');

        if ($configuredToken === '' || !hash_equals($configuredToken, $providedToken)) {
            abort(401, 'No autorizado: Token de agente de impresión inválido.');
        }

        return $next($request);
    }
}

