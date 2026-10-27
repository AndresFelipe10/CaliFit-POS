<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of collaborators for SaborCaliFit Admin.
     */
    public function index(): View
    {
        $users = User::with('role')->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * Store a newly created collaborator with secure temporary password.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $tempPassword = Str::random(10);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'password' => Hash::make($tempPassword),
            'must_change_password' => true,
            'is_active' => true,
        ]);

        return redirect()->route('users.index')
            ->with('success', "Colaborador {$user->name} creado exitosamente.")
            ->with('temp_password', $tempPassword)
            ->with('temp_user_name', $user->name)
            ->with('temp_user_email', $user->email);
    }

    /**
     * Update the specified collaborator.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($user->id === auth()->id() && (int) $request->role_id !== (int) $user->role_id) {
            return back()->with('error', 'No puedes cambiar tu propio rol de administrador.');
        }

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'role_id' => $request->role_id,
        ]);

        return redirect()->route('users.index')
            ->with('success', "Datos del colaborador {$user->name} actualizados.");
    }

    /**
     * Toggle active/inactive status of a user.
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta de usuario.');
        }

        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        $statusText = $user->is_active ? 'activado' : 'desactivado';

        return redirect()->route('users.index')
            ->with('success', "El usuario {$user->name} ha sido {$statusText}.");
    }

    /**
     * Reset password and generate a new secure temporary password.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $tempPassword = Str::random(10);

        $user->update([
            'password' => Hash::make($tempPassword),
            'must_change_password' => true,
        ]);

        return redirect()->route('users.index')
            ->with('success', "Contraseña restablecida para {$user->name}.")
            ->with('temp_password', $tempPassword)
            ->with('temp_user_name', $user->name)
            ->with('temp_user_email', $user->email);
    }
}
