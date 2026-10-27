@extends('layouts.auth')

@section('title', 'Iniciar Sesión')
@section('heading', 'Iniciar Sesión')
@section('subheading', 'Ingresa tus credenciales para acceder al sistema POS')

@section('content')
<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Correo Electrónico</label>
        <div class="input-group">
            <span class="input-group-text border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" 
                   class="form-control border-start-0 @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   placeholder="ejemplo@califit.com" 
                   required 
                   autofocus 
                   autocomplete="username">
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <div class="input-group">
            <span class="input-group-text border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0 border-end-0 @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   placeholder="••••••••" 
                   required 
                   autocomplete="current-password">
            <button class="btn btn-outline-secondary password-toggle-btn border-start-0" type="button" id="togglePassword" aria-label="Mostrar u ocultar contraseña">
                <i class="bi bi-eye" id="togglePasswordIcon"></i>
            </button>
        </div>
    </div>

    <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
        <label class="form-check-label small text-secondary" for="remember">Recordar mi sesión en este equipo</label>
    </div>

    <button type="submit" class="btn btn-primary-custom">
        <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión en CaliFit POS
    </button>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon');
    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }
});
</script>
@endpush
