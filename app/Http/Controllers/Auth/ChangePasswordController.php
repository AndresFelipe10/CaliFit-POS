<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    /**
     * Show the mandatory password change view.
     */
    public function show(): View
    {
        return view('auth.change-password');
    }

    /**
     * Update the user password enforcing security validations.
     */
    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'terms_accepted_at' => $user->terms_accepted_at ?: now(),
            'terms_accepted_ip' => $user->terms_accepted_ip ?: $request->ip(),
        ]);

        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Tu contraseña ha sido actualizada exitosamente.');
    }
}
