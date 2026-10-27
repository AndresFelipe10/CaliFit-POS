<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Handle an incoming request enforcing mandatory password change.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (bool) $user->must_change_password) {
            $allowedRoutes = ['password.change', 'password.update', 'password.change.update', 'logout'];

            if (! $request->routeIs(...$allowedRoutes)) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Debes cambiar tu contraseña antes de continuar.'], 403);
                }

                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
