<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    /**
     * Handle an incoming request for mono-business CaliFit POS.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (isset($user->is_active) && ! $user->is_active) {
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $errorMessage = 'Tu cuenta no está activa. Contacta a la administración.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $errorMessage], 403);
            }

            return redirect()->route('login')
                ->withErrors(['email' => $errorMessage])
                ->with('error', $errorMessage);
        }

        return $next($request);
    }
}
