@extends('layouts.auth')

@section('title', 'Iniciar Sesión')
@section('heading', 'Bienvenido de nuevo')
@section('subheading', 'Ingresa tus credenciales para acceder a tu panel')

@section('content')
@if (session('session_warning') || session('warning'))
    <div style="background-color: #fffbeb; border: 1px solid #fde68a; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px;">
        <div style="display: flex; align-items: flex-start; gap: 10px;">
            <svg style="width: 20px; height: 20px; color: #d97706; flex-shrink: 0; margin-top: 1px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p style="margin: 0; font-size: 13.5px; line-height: 1.45; color: #92400e; font-weight: 500;">
                {{ session('session_warning') ?? session('warning') }}
            </p>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
            <input type="email" 
                   class="form-control border-start-0 @error('email') is-invalid @enderror" 
                   id="email" 
                   name="email" 
                   value="{{ old('email') }}" 
                   placeholder="ejemplo@negocio.com" 
                   required 
                   autofocus 
                   autocomplete="username">
        </div>
    </div>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label small fw-semibold text-secondary mb-0">Contraseña</label>
            @if (in_array(config('mail.default'), ['log', 'array']))
                <a href="https://wa.me/{{ config('app.support_whatsapp', '573163765939') }}?text={{ urlencode('Hola, olvidé mi clave de PuntoStock') }}" 
                   target="_blank" rel="noopener noreferrer" 
                   class="small text-decoration-none text-primary fw-medium">
                    <i class="bi bi-whatsapp me-1"></i>Contacta a soporte
                </a>
            @else
                <a href="{{ route('password.request') }}" class="small text-decoration-none text-primary fw-medium">¿Olvidaste tu clave?</a>
            @endif
        </div>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
            <input type="password" 
                   class="form-control border-start-0 border-end-0 @error('password') is-invalid @enderror" 
                   id="password" 
                   name="password" 
                   placeholder="••••••••" 
                   required 
                   autocomplete="current-password">
            <button type="button" 
                    class="btn btn-outline-secondary bg-white border-start-0 text-secondary px-3" 
                    id="togglePasswordBtn"
                    aria-label="Mostrar u ocultar contraseña"
                    title="Mostrar / ocultar contraseña"
                    style="border-color: var(--bs-border-color);">
                <i class="bi bi-eye" id="togglePasswordIcon"></i>
            </button>
        </div>
    </div>

    <div class="mb-4 form-check">
        <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
        <label class="form-check-label small text-secondary" for="remember">Recordar mi sesión en este equipo</label>
    </div>

    <button type="submit" class="btn btn-primary-custom">
        <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
    </button>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passwordInput && toggleIcon) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleIcon.classList.toggle('bi-eye', !isPassword);
                toggleIcon.classList.toggle('bi-eye-slash', isPassword);
                passwordInput.focus();
            });
        }
    });
</script>
@endsection

@section('footer')
    <div class="mb-2">
        <a href="{{ config('app.commercial_url', 'https://puntostockpos.com') }}" 
           target="_blank" rel="noopener noreferrer" 
           class="text-secondary text-decoration-none">
            ¿Quieres PuntoStock para tu negocio?
        </a>
        <a href="https://wa.me/{{ config('app.support_whatsapp', '573163765939') }}?text={{ urlencode('Hola, deseo más información sobre PuntoStock POS') }}" 
           target="_blank" rel="noopener noreferrer" 
           class="text-primary text-decoration-none fw-semibold ms-1">
            <i class="bi bi-whatsapp me-1"></i>Escríbenos
        </a>
    </div>
    <div class="small text-muted pt-2 border-top">
        <a href="{{ config('app.legal_terms_url', 'https://puntostockpos.com/terminos-y-condiciones/') }}" 
           target="_blank" rel="noopener noreferrer" 
           class="text-secondary text-decoration-none me-2">Términos y Condiciones</a>
        <span>•</span>
        <a href="{{ config('app.legal_privacy_url', 'https://puntostockpos.com/politica-de-privacidad/') }}" 
           target="_blank" rel="noopener noreferrer" 
           class="text-secondary text-decoration-none ms-2">Privacidad y Cookies</a>
    </div>
@endsection
