<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request checking user roles in mono-business CaliFit.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Acceso no autorizado para su rol operativo.');
        }

        // Normalizar los roles permitidos (soporta "role:admin" o múltiples argumentos)
        $allowedRoles = [];
        foreach ($roles as $roleGroup) {
            foreach (explode(',', $roleGroup) as $role) {
                $trimmed = trim($role);
                if ($trimmed !== '') {
                    $allowedRoles[] = $trimmed;
                }
            }
        }

        // Si el slug de su rol actual coincide con los permitidos
        $userRoleSlug = $user->role?->slug;
        if ($userRoleSlug && in_array($userRoleSlug, $allowedRoles, true)) {
            return $next($request);
        }

        abort(403, 'Acceso no autorizado para su rol operativo.');
    }
}
