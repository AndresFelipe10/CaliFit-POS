<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name', 'CaliFit POS') }} — SaborCaliFit Bochalema</title>
    @include('partials.brand-head')

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --califit-primary: #10B981;
            --califit-primary-hover: #059669;
            --califit-primary-light: #D1FAE5;
            --califit-dark: #111827;
            --califit-surface: #1F2937;
            --califit-bg: #F9FAFB;
            --califit-accent: #34D399;
            --bs-primary: #10B981;
            --bs-primary-rgb: 16, 185, 129;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 0.9375rem;
            background-color: var(--califit-bg);
            color: var(--califit-dark);
            min-height: 100vh;
        }

        .navbar-califit, .sidebar-califit {
            background-color: var(--califit-dark);
            color: #FFFFFF;
        }

        .btn-califit {
            background-color: var(--califit-primary);
            color: #FFFFFF;
            font-weight: 600;
            border: none;
        }

        .btn-califit:hover {
            background-color: var(--califit-primary-hover);
            color: #FFFFFF;
        }

        /* Paleta Oficial CaliFit: Cero Botones Azules y Pestañas Esmeralda */
        .btn-primary {
            background-color: var(--califit-primary) !important;
            border-color: var(--califit-primary) !important;
            color: #FFFFFF !important;
            font-weight: 600;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--califit-primary-hover) !important;
            border-color: var(--califit-primary-hover) !important;
            color: #FFFFFF !important;
        }
        .btn-outline-primary {
            border-color: var(--califit-primary) !important;
            color: var(--califit-primary) !important;
            font-weight: 600;
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary:active {
            background-color: var(--califit-primary) !important;
            border-color: var(--califit-primary) !important;
            color: #FFFFFF !important;
        }
        .nav-pills .nav-link.active, .nav-pills .show > .nav-link {
            background-color: var(--califit-primary) !important;
            color: #FFFFFF !important;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25) !important;
        }
        .nav-pills .nav-link:not(.active) {
            background-color: #FFFFFF;
            color: #374151 !important;
            border: 1px solid #E5E7EB;
            font-weight: 500;
        }
        .nav-pills .nav-link:not(.active):hover {
            background-color: #ECFDF5 !important;
            color: var(--califit-primary-hover) !important;
            border-color: #A7F3D0 !important;
        }
        .text-primary {
            color: var(--califit-primary) !important;
        }
        .bg-primary {
            background-color: var(--califit-primary) !important;
        }
        .bg-primary-subtle {
            background-color: #ECFDF5 !important;
            color: #065F46 !important;
        }

        /* Paginación en Paleta CaliFit (Verde Esmeralda #10B981) */
        .page-item.active .page-link {
            background-color: #10B981 !important;
            border-color: #10B981 !important;
            color: #FFFFFF !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25) !important;
        }
        .page-link {
            color: #111827 !important;
            border-radius: 8px !important;
            margin: 0 3px !important;
            border: 1px solid #E5E7EB !important;
            transition: all 0.15s ease-in-out !important;
        }
        .page-link:hover {
            background-color: #ECFDF5 !important;
            color: #059669 !important;
            border-color: #10B981 !important;
        }
        .page-link:focus {
            box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25) !important;
        }
        .page-item.disabled .page-link {
            color: #9CA3AF !important;
            background-color: #F9FAFB !important;
            border-color: #E5E7EB !important;
        }

        .badge-status-available { background-color: #10B981; color: #FFFFFF; }
        .badge-status-occupied { background-color: #EF4444; color: #FFFFFF; }
        .badge-status-billed { background-color: #F59E0B; color: #FFFFFF; }

        /* Forzar visibilidad permanente de spinners numéricos en navegadores WebKit/Chromium */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }
        /* Soporte en Firefox */
        input[type=number] {
            -moz-appearance: number-input;
        }

        /* Ocultar flechas nativas SÓLO en inputs con controles externos dedicados */
        .input-stepper::-webkit-outer-spin-button,
        .input-stepper::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        .input-stepper,
        .qty-input {
            -moz-appearance: textfield !important;
        }

        /* Flechas visibles siempre para inputs numéricos estándar y de grupo */
        input[type=number].always-spinners::-webkit-inner-spin-button,
        input[type=number].always-spinners::-webkit-outer-spin-button,
        .input-group input[type=number]::-webkit-inner-spin-button,
        .input-group input[type=number]::-webkit-outer-spin-button {
            opacity: 1 !important;
            display: inline-block !important;
            cursor: pointer;
        }
        .navbar-custom {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            min-height: 64px;
            z-index: 1030;
        }
        .navbar-brand-logo {
            height: 42px;
            width: auto;
            max-height: 44px;
            object-fit: contain;
            transition: height 0.2s ease;
        }
        @media (min-width: 992px) {
            .navbar-custom {
                min-height: 70px;
            }
            .navbar-brand-logo {
                height: 48px;
                max-height: 50px;
            }
        }
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
        .navbar-brand {
            font-weight: 700;
            color: var(--califit-primary) !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.15rem;
        }
        .card-custom {
            background: #ffffff;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .user-pill {
            background: #f1f5f9;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .location-badge {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            border-radius: 2rem;
            padding: 0.35rem 0.95rem;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Ocultar flechas numéricas nativas intrusivas para inputs amplios en POS y modales */
        input[type="number"].no-spinners::-webkit-inner-spin-button,
        input[type="number"].no-spinners::-webkit-outer-spin-button,
        .no-spinners::-webkit-inner-spin-button,
        .no-spinners::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type="number"].no-spinners,
        .no-spinners {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        /* Layout con Sidebar */
        .app-layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }

        @media (min-width: 992px) {
            .app-layout {
                min-height: calc(100vh - 70px);
            }
            .sidebar-desktop {
                position: fixed;
                top: 70px;
                bottom: 0;
                left: 0;
                width: 250px;
                height: calc(100vh - 70px);
                background-color: #ffffff;
                border-right: 1px solid #e2e8f0;
                overflow-y: auto;
                padding: 1.25rem 0.75rem;
                z-index: 1020;
                scrollbar-width: thin;
                scrollbar-color: #cbd5e1 transparent;
            }
            .sidebar-desktop::-webkit-scrollbar {
                width: 6px;
            }
            .sidebar-desktop::-webkit-scrollbar-track {
                background: transparent;
            }
            .sidebar-desktop::-webkit-scrollbar-thumb {
                background-color: #cbd5e1;
                border-radius: 9999px;
            }
            .sidebar-desktop::-webkit-scrollbar-thumb:hover {
                background-color: #94a3b8;
            }
            .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
                min-height: calc(100vh - 70px);
            }
        }

        @media (max-width: 991.98px) {
            .sidebar-desktop {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                min-height: calc(100vh - 64px);
            }
        }

        /* Estilos del Sidebar */
        .sidebar-nav-container .nav-link {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-nav-container .nav-link:hover {
            background-color: #ecfdf5;
            color: var(--califit-primary-hover) !important;
        }

        .sidebar-nav-container .nav-link.active {
            background-color: var(--califit-primary);
            color: #ffffff !important;
            font-weight: 600;
        }

        .sidebar-nav-container .nav-link.active i {
            color: #ffffff !important;
        }

        .sidebar-nav-container .nav-link i {
            font-size: 1.15rem;
            width: 1.5rem;
            text-align: center;
            flex-shrink: 0;
        }

        /* Sobrescrituras de estilo de Bootstrap para identidad visual CaliFit POS */
        .btn-primary {
            background-color: var(--califit-primary) !important;
            border-color: var(--califit-primary) !important;
            color: #ffffff !important;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--califit-primary-hover) !important;
            border-color: var(--califit-primary-hover) !important;
            color: #ffffff !important;
        }
        .btn-outline-primary {
            color: var(--califit-primary) !important;
            border-color: var(--califit-primary) !important;
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary:active {
            background-color: var(--califit-primary) !important;
            border-color: var(--califit-primary) !important;
            color: #ffffff !important;
        }
        .text-primary {
            color: var(--califit-primary) !important;
        }
        .bg-primary:not([class*="bg-opacity-"]):not([class*="bg-primary-subtle"]) {
            background-color: var(--califit-primary) !important;
        }
        .border-primary {
            border-color: var(--califit-primary) !important;
        }

        /* Salvaguarda para iconos de paginación SVG */
        .pagination svg {
            width: 1em !important;
            height: 1em !important;
            max-width: 16px !important;
            max-height: 16px !important;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    {{-- Navbar Superior --}}
    <nav class="navbar navbar-custom sticky-top py-2 py-lg-2.5">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                {{-- Botón hamburguesa para móvil/tablet que abre el Offcanvas (área táctil mínima 44x44 px) --}}
                <button class="btn btn-outline-secondary d-lg-none p-1 border-0" 
                        type="button" 
                        data-bs-toggle="offcanvas" 
                        data-bs-target="#sidebarOffcanvas" 
                        aria-controls="sidebarOffcanvas" 
                        aria-label="Abrir menú de navegación"
                        style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">
                    <i class="bi bi-list fs-3"></i>
                </button>

                <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                    <img src="{{ asset('images/brand/logo-califit.png') }}" 
                         alt="SaborCaliFit" 
                         class="navbar-brand-logo" 
                         style="object-fit: contain;">
                    <div class="d-flex flex-column">
                        <span class="fw-bold text-dark lh-1 fs-5">CaliFit <span class="badge bg-success-subtle text-success fs-xs fw-semibold px-2 py-0 ms-1">POS</span></span>
                        <span class="text-muted text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.05em; font-weight: 700;">SaborCaliFit</span>
                    </div>
                </a>
            </div>

            {{-- Elementos de escritorio (>= 768px) --}}
            <div class="d-none d-md-flex align-items-center gap-2 gap-md-3">
                <span class="location-badge">
                    <i class="bi bi-geo-alt-fill text-success"></i> <span>Bochalema • Arbolatta Mall</span>
                </span>

                <span class="user-pill">
                    <i class="bi bi-person-circle me-1 text-success"></i> <span>{{ auth()->user()->name }}</span>
                </span>

                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                        <i class="bi bi-box-arrow-right me-1"></i> Salir
                    </button>
                </form>
            </div>

            {{-- Menú de usuario móvil (< 768px) --}}
            <div class="d-md-none dropdown">
                <button class="btn btn-light rounded-circle border shadow-sm p-0 d-flex align-items-center justify-content-center" 
                        type="button" 
                        id="mobileUserMenu" 
                        data-bs-toggle="dropdown" 
                        aria-expanded="false"
                        style="width: 40px; height: 40px;"
                        aria-label="Menú de usuario">
                    <i class="bi bi-person-circle text-success fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2 mt-2" aria-labelledby="mobileUserMenu" style="min-width: 250px; border-radius: 0.85rem;">
                    <li class="px-3 py-2 bg-light border-bottom mb-1">
                        <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Sede Activa</span>
                        <div class="fw-bold text-dark text-truncate d-flex align-items-center gap-2 mt-1">
                            <i class="bi bi-geo-alt-fill text-success"></i>
                            <span class="text-truncate">SABORCALIFIT | Bochalema</span>
                        </div>
                    </li>
                    <li class="px-3 py-2">
                        <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Usuario</span>
                        <div class="fw-semibold text-dark text-truncate d-flex align-items-center gap-2 mt-1">
                            <i class="bi bi-person-badge text-secondary"></i>
                            <span class="text-truncate">{{ auth()->user()->name }}</span>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary border mt-1">
                            {{ auth()->user()->role?->name ?? (auth()->user()->isAdmin() ? 'Administrador' : 'Mesero') }}
                        </span>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li class="px-2 pt-1">
                        <form method="POST" action="{{ route('logout') }}" class="w-100 m-0">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100 rounded-pill py-2 d-flex align-items-center justify-content-center gap-2 fw-semibold">
                                <i class="bi bi-box-arrow-right"></i> Salir
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    {{-- Offcanvas para pantallas móviles y tablets (< 992px) --}}
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold text-success d-flex align-items-center gap-2" id="sidebarOffcanvasLabel">
                <i class="bi bi-box-seam-fill"></i> Módulos
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body p-3" style="overflow-y: auto;">
            @include('layouts.sidebar')
        </div>
    </div>

    {{-- Contenedor principal con Sidebar de escritorio y contenido --}}
    <div class="app-layout">
        {{-- Sidebar fijo para pantallas de escritorio (>= 992px) --}}
        <aside class="sidebar-desktop d-none d-lg-block">
            @include('layouts.sidebar')
        </aside>

        {{-- Área de contenido principal --}}
        <div class="main-content flex-grow-1">
            <main class="container py-4">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important; color: #065f46;">
                        <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i>
                        <div class="flex-grow-1 fw-semibold">{{ session('success') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3 mb-3 d-flex align-items-center" role="alert" style="background-color: #ecfdf5; border-left: 4px solid #10b981 !important; color: #065f46;">
                        <i class="bi bi-check-circle-fill me-2 fs-5 text-success"></i>
                        <div class="flex-grow-1 fw-semibold">{{ session('status') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert" style="background-color: #fef2f2; border-left: 4px solid #ef4444 !important; color: #991b1b;">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5 text-danger"></i>
                        <div class="flex-grow-1 fw-semibold">{{ session('error') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2 fs-5 text-warning"></i>
                        <div class="flex-grow-1 fw-semibold">{{ session('warning') }}</div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                @if (session('info'))
                    <div class="alert alert-info alert-dismissible fade show rounded-3 mb-3" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i> {{ session('info') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3" role="alert" style="background-color: #fef2f2; border-left: 4px solid #ef4444 !important; color: #991b1b;">
                        <div class="d-flex align-items-center mb-1">
                            <i class="bi bi-exclamation-octagon-fill me-2 fs-5 text-danger"></i>
                            <strong class="fw-bold">Por favor verifique los siguientes errores:</strong>
                        </div>
                        <ul class="mb-0 ps-4 small">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle (incluye Popper para dropdowns y offcanvas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // =========================================================================
        // MOTOR ANTI-FREEZE & GESTIÓN RESILIENTE DE BOTONES DE ACCIÓN (CALIFIT POS)
        // =========================================================================

        // 1. Función global accesible en todo el DOM para restaurar botones de carga
        window.resetLoadingButtons = function(container = document) {
            const scope = (container instanceof HTMLElement || container instanceof Document) ? container : document;
            scope.querySelectorAll('button[type="submit"][disabled], button[data-loading-state="active"]').forEach(btn => {
                btn.disabled = false;
                delete btn.dataset.loadingState;
                if (btn.dataset.originalHtml !== undefined) {
                    btn.innerHTML = btn.dataset.originalHtml;
                    delete btn.dataset.originalHtml;
                }
            });
        };

        // 2. Restaurar estado en eventos de ciclo de vida del navegador (navegación atrás / caché bfcache)
        window.addEventListener('pageshow', function () {
            window.resetLoadingButtons();
        });
        window.addEventListener('pagehide', function () {
            window.resetLoadingButtons();
        });

        // 3. Restaurar botones si cualquier modal de Bootstrap es cerrado o cancelado
        document.addEventListener('hidden.bs.modal', function (e) {
            window.resetLoadingButtons(e.target);
            window.resetLoadingButtons(document);
        });

        // 4. Restaurar botones si falla la validación nativa HTML5 en cualquier formulario
        document.addEventListener('invalid', function (e) {
            const form = e.target.closest('form');
            if (form) {
                window.resetLoadingButtons(form);
            }
        }, true);

        // 5. Manejo seguro de envíos de formularios con feedback táctil y prevención de dobles envíos
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;

            // Formularios excluidos (abren en nueva pestaña o tienen bypass explícito)
            if (form.hasAttribute('data-no-disable') || form.getAttribute('target') === '_blank') {
                return;
            }

            // Si el formulario requiere confirmación modal interactiva y aún no ha sido confirmado
            if (form.hasAttribute('data-confirm') && !form.dataset.confirmed) {
                e.preventDefault();
                e.stopImmediatePropagation();
                window.califitConfirm({
                    title: form.getAttribute('data-confirm-title') || 'Confirmar Acción',
                    message: form.getAttribute('data-confirm'),
                    confirmText: form.getAttribute('data-confirm-btn') || 'Confirmar',
                    confirmClass: form.getAttribute('data-confirm-btn-class') || 'btn-danger',
                    icon: form.getAttribute('data-confirm-icon') || 'bi-exclamation-triangle',
                    onConfirm: function() {
                        form.dataset.confirmed = 'true';
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                });
                return;
            }

            // Si ya fue confirmado, limpiamos el flag para futuros envíos
            if (form.dataset.confirmed) {
                delete form.dataset.confirmed;
            }

            // Si el envío fue prevenido por validaciones previas o confirm cancelado
            if (e.defaultPrevented) {
                window.resetLoadingButtons(form);
                return;
            }

            // Si falla la validación HTML5
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                window.resetLoadingButtons(form);
                return;
            }

            // Localizar el botón exacto que disparó el submit
            const submitBtn = e.submitter || form.querySelector('button[type="submit"]:not([disabled])');
            if (submitBtn) {
                if (submitBtn.dataset.originalHtml === undefined) {
                    submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                }
                setTimeout(() => {
                    if (e.defaultPrevented || (typeof form.checkValidity === 'function' && !form.checkValidity())) {
                        window.resetLoadingButtons(form);
                        return;
                    }
                    submitBtn.disabled = true;
                    submitBtn.dataset.loadingState = 'active';
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
                }, 20);
            }
        });

        // Motor Global de Formato Monetario Colombiano (COP)
        window.formatCOP = function(val, allowDecimals = true) {
            if (val === null || val === undefined || val === '') return '';
            let str = String(val).trim();
            if (!str) return '';

            // Si contiene coma, es decimal en formato colombiano (ej: 140000,50 o 140.000,50)
            if (str.includes(',')) {
                let parts = str.split(',');
                let intPart = parts[0].replace(/[^\d]/g, '');
                let decPart = parts.slice(1).join('').replace(/[^\d]/g, '').substring(0, 2);
                if (intPart === '') intPart = '0';
                intPart = intPart.replace(/^0+(?=\d)/, '');
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return allowDecimals && decPart.length > 0 ? `${intPart},${decPart}` : intPart;
            }

            // Si contiene punto y parece decimal (ej: "140.5" o "140000.00")
            if (str.includes('.') && str.indexOf('.') === str.lastIndexOf('.') && (str.length - str.indexOf('.')) <= 3 && !/^\d{1,3}(\.\d{3})+$/.test(str)) {
                let parts = str.split('.');
                let intPart = parts[0].replace(/[^\d]/g, '');
                let decPart = parts[1].replace(/[^\d]/g, '');
                if (intPart === '') intPart = '0';
                intPart = intPart.replace(/^0+(?=\d)/, '');
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                if (decPart === '0' || decPart === '00' || !allowDecimals) {
                    return intPart;
                }
                return `${intPart},${decPart}`;
            }

            // Entero puro o con puntos existentes
            let clean = str.replace(/[^\d]/g, '');
            if (clean === '') return '';
            clean = clean.replace(/^0+(?=\d)/, '');
            return clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        };

        window.unformatCOP = function(val) {
            if (val === null || val === undefined || val === '') return '0';
            let str = String(val).trim();
            if (!str) return '0';
            str = str.replace(/[$ \s]/g, '');
            if (str.includes(',')) {
                let parts = str.split(',');
                let intPart = parts[0].replace(/\./g, '').replace(/[^\d-]/g, '');
                let decPart = parts[1].replace(/[^\d]/g, '') || '0';
                return `${intPart || '0'}.${decPart}`;
            }
            return str.replace(/\./g, '').replace(/[^\d-]/g, '') || '0';
        };

        // Formatea automáticamente cualquier input con clase .currency-input
        window.setupCurrencyInputs = function() {
            document.querySelectorAll('.currency-input, input[data-currency="true"]').forEach(input => {
                if (input.dataset.currencyAttached) return;
                input.dataset.currencyAttached = 'true';

                // Si tiene valor inicial, formatearlo
                if (input.value && input.value !== '') {
                    input.value = window.formatCOP(input.value);
                }

                input.addEventListener('input', function(e) {
                    const cursorPosition = this.selectionStart;
                    const originalLength = this.value.length;
                    
                    this.value = window.formatCOP(this.value);
                    
                    const newLength = this.value.length;
                    const newPosition = cursorPosition + (newLength - originalLength);
                    if (newPosition >= 0 && newPosition <= newLength) {
                        this.setSelectionRange(newPosition, newPosition);
                    }
                });
            });
        };

        // Antes de enviar cualquier formulario tradicional, desformatear los .currency-input
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form && form.matches('form')) {
                form.querySelectorAll('.currency-input, input[data-currency="true"]').forEach(input => {
                    input.value = window.unformatCOP(input.value);
                });
            }
        });

        // Ergonomía global: Auto-selección en foco y eliminación reactiva de ceros parásitos
        function setupNumberInputErgonomics() {
            document.querySelectorAll('input[type="number"], .currency-input, input[name="cost_price"], input[name="sale_price"], input[name="stock"], input[name="min_stock"]').forEach(input => {
                if (input.dataset.ergonomicsAttached) return;
                input.dataset.ergonomicsAttached = 'true';

                input.addEventListener('focus', function () {
                    this.select();
                });
            });
        }

        // Delegación de eventos para inputs numéricos y monetarios estáticos y dinámicos
        document.addEventListener('focusin', function (e) {
            const target = e.target;
            if (target && target.matches('input[type="number"], .currency-input, input[name="cost_price"], input[name="sale_price"], input[name="stock"], input[name="min_stock"]')) {
                setTimeout(() => {
                    target.select();
                }, 0);
            }
        });

        document.addEventListener('input', function (e) {
            const target = e.target;
            if (target && target.matches('input[type="number"]:not(.currency-input)')) {
                const val = target.value;
                if (/^0+[1-9]/.test(val)) {
                    target.value = val.replace(/^0+/, '');
                }
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            setupNumberInputErgonomics();
            setupCurrencyInputs();
        });

        // Sistema Global de Notificaciones Visuales CaliFit (Reemplazo elegante de alert)
        window.califitNotify = function(message, type = 'info', title = null) {
            let container = document.getElementById('califit-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'califit-toast-container';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '10999';
                document.body.appendChild(container);
            }

            const bgMap = {
                success: { bg: '#ecfdf5', border: '#10B981', text: '#065f46', icon: 'bi-check-circle-fill', defaultTitle: 'Operación Exitosa' },
                danger: { bg: '#fef2f2', border: '#EF4444', text: '#991b1b', icon: 'bi-x-circle-fill', defaultTitle: 'Atención requerida' },
                error: { bg: '#fef2f2', border: '#EF4444', text: '#991b1b', icon: 'bi-x-circle-fill', defaultTitle: 'Atención requerida' },
                warning: { bg: '#fffbeb', border: '#F59E0B', text: '#92400e', icon: 'bi-exclamation-triangle-fill', defaultTitle: 'Aviso importante' },
                info: { bg: '#eff6ff', border: '#3B82F6', text: '#1e40af', icon: 'bi-info-circle-fill', defaultTitle: 'Información' }
            };

            const cfg = bgMap[type] || bgMap.info;
            const toastId = 'toast-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
            const toastTitle = title || cfg.defaultTitle;

            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center shadow-lg border-0 mb-2 fade show" role="alert" aria-live="assertive" aria-atomic="true" style="background-color: ${cfg.bg}; border-left: 5px solid ${cfg.border} !important; border-radius: 0.5rem; min-width: 290px; max-width: 420px;">
                    <div class="d-flex p-3 align-items-start">
                        <i class="bi ${cfg.icon} me-2 fs-5" style="color: ${cfg.border}; line-height: 1.2;"></i>
                        <div class="flex-grow-1 me-2" style="color: ${cfg.text};">
                            <div class="fw-bold small mb-1" style="color: ${cfg.text};">${toastTitle}</div>
                            <div class="small fw-normal" style="font-size: 0.85rem; line-height: 1.35;">${message}</div>
                        </div>
                        <button type="button" class="btn-close ms-auto me-1" style="font-size: 0.75rem;" onclick="document.getElementById('${toastId}').remove()"></button>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', toastHtml);
            setTimeout(() => {
                const el = document.getElementById(toastId);
                if (el) {
                    el.classList.remove('show');
                    setTimeout(() => el.remove(), 300);
                }
            }, 4500);
        };

        // Salvaguarda global obligatoria: Previene cualquier pop-up nativo 'localhost:8080 dice...'
        window.alert = function(msg) {
            window.califitNotify(msg, 'warning', 'Aviso del Sistema');
        };

        // Salvaguarda fail-safe: Previene cualquier pop-up confirm nativo residual del navegador
        window.confirm = function(msg) {
            console.warn('[CaliFit UX] Invocación nativa a window.confirm interceptada:', msg);
            window.califitNotify(msg, 'warning', 'Confirmación Requerida');
            window.resetLoadingButtons();
            return false;
        };

        // Modal global interactivo CaliFit POS (Reemplazo ergonómico de window.confirm)
        window.califitConfirm = function(options) {
            const modalEl = document.getElementById('globalConfirmModal');
            if (!modalEl) {
                if (typeof options.onConfirm === 'function') options.onConfirm();
                return;
            }

            const titleEl = document.getElementById('globalConfirmModalLabel');
            const messageEl = document.getElementById('globalConfirmMessage');
            const submitBtn = document.getElementById('globalConfirmSubmitBtn');
            const iconEl = document.getElementById('globalConfirmIcon');
            const iconWrapper = document.getElementById('globalConfirmIconWrapper');

            if (titleEl) titleEl.textContent = options.title || 'Confirmar Acción';
            if (messageEl) messageEl.textContent = options.message || '¿Deseas continuar con esta acción?';

            const btnClass = options.confirmClass || 'btn-danger';
            const iconClass = options.icon || 'bi-exclamation-triangle';

            if (iconEl) {
                iconEl.className = `bi ${iconClass} fs-5`;
                if (btnClass.includes('danger')) {
                    iconEl.className = `bi ${iconClass} text-danger fs-5`;
                    if (iconWrapper) iconWrapper.className = 'rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center';
                } else if (btnClass.includes('warning')) {
                    iconEl.className = `bi ${iconClass} text-warning fs-5`;
                    if (iconWrapper) iconWrapper.className = 'rounded-circle bg-warning-subtle d-flex align-items-center justify-content-center';
                } else {
                    iconEl.className = `bi ${iconClass} text-success fs-5`;
                    if (iconWrapper) iconWrapper.className = 'rounded-circle bg-success-subtle d-flex align-items-center justify-content-center';
                }
            }

            if (submitBtn) {
                submitBtn.className = `btn ${btnClass} px-4 fw-semibold`;
                submitBtn.innerHTML = `<i class="bi bi-check-circle me-1"></i> ${options.confirmText || 'Confirmar'}`;

                submitBtn.onclick = function() {
                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    if (bsModal) bsModal.hide();
                    if (typeof options.onConfirm === 'function') {
                        options.onConfirm();
                    }
                };
            }

            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        };

        // =========================================================================
        // HIGIENIZACIÓN REACTIVA Y CONTROL ESTRICTO DE INPUTS (CALIFIT POS)
        // =========================================================================
        document.addEventListener('input', function(e) {
            const target = e.target;
            if (!target || !target.matches('input, textarea')) return;

            const name = (target.name || '').toLowerCase();
            const id = (target.id || '').toLowerCase();
            const role = (target.dataset.sanitize || '').toLowerCase();

            // 1. Nombres de Personas (name, waiter_name, contact_name, recipient_name, customClientName, etc.)
            // Solo permitir letras (incluyendo tildes, diéresis y ñ/Ñ) y espacios
            const isNameField = role === 'person-name' || 
                                ['name', 'nombre', 'waiter_name', 'contact_name', 'recipient_name', 'customer_name'].includes(name) ||
                                id.includes('customername') || id.includes('custname') || id === 'opentablewaitername' || id === 'customclientname';

            if (isNameField) {
                const cleaned = target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s]/g, '').slice(0, 100);
                if (target.value !== cleaned) {
                    target.value = cleaned;
                }
            }

            // 2. Teléfono / Celular / WhatsApp
            // Filtrado estricto de solo dígitos numéricos (hasta 10 dígitos)
            const isPhoneField = role === 'phone' || 
                                 ['phone', 'telefono', 'customer_phone', 'dispatchphone'].includes(name) ||
                                 id.includes('phone') || id.includes('telefono');

            if (isPhoneField) {
                const cleaned = target.value.replace(/[^0-9]/g, '').slice(0, 10);
                if (target.value !== cleaned) {
                    target.value = cleaned;
                }
            }

            // 3. Documento / Cédula de Clientes (Solo dígitos, hasta 12)
            const isDocField = role === 'customer-doc' || 
                               ['document', 'document_number', 'cedula', 'customer_document'].includes(name) ||
                               id.includes('customerdoc') || id === 'customclientdoc' || id === 'quickcustomerdoc';

            if (isDocField) {
                const cleaned = target.value.replace(/[^0-9]/g, '').slice(0, 12);
                if (target.value !== cleaned) {
                    target.value = cleaned;
                }
            }

            // 4. Proveedores: NIT (dígitos y un guion, hasta 15)
            const isNitField = role === 'supplier-nit' || name === 'nit' || id === 'supplier_nit' || id === 'nit';
            if (isNitField) {
                const cleaned = target.value.replace(/[^0-9-]/g, '').slice(0, 15);
                if (target.value !== cleaned) {
                    target.value = cleaned;
                }
            }
        });

        // Prevenir caracteres no válidos (e, E, +, -) en inputs numéricos monetarios/cantidades >= 0
        document.addEventListener('keydown', function(e) {
            const target = e.target;
            if (target && target.matches('input[type="number"]') && !target.classList.contains('allow-negative')) {
                if (['e', 'E', '+', '-'].includes(e.key)) {
                    e.preventDefault();
                }
            }
        });

        // =========================================================================
        // UTILIDADES GLOBALES DE MONEDA COLOMBIANA ($ COP) - (REGLAMENTACIÓN ESTRICTA)
        // =========================================================================
        window.parseCopNumber = function(val) {
            if (val === null || val === undefined) return 0;
            if (typeof val === 'number') return isNaN(val) ? 0 : val;
            let clean = String(val).trim().replace(/[^\d.,-]/g, '');
            if (!clean || clean === '-') return 0;
            if (clean.includes(',')) {
                clean = clean.replace(/\./g, '').replace(',', '.');
            } else if ((clean.match(/\./g) || []).length > 1 || /\.\d{3}$/.test(clean)) {
                clean = clean.replace(/\./g, '');
            }
            const n = parseFloat(clean);
            return isNaN(n) ? 0 : n;
        };

        window.formatCopNumber = function(val) {
            const n = window.parseCopNumber(val);
            return new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(n);
        };

        // Formateador en vivo para campos con clase .cop-currency-input
        document.addEventListener('input', function(e) {
            const target = e.target;
            if (!target || !target.classList.contains('cop-currency-input')) return;

            const rawVal = target.value.replace(/[^\d]/g, '');
            if (!rawVal) {
                target.value = '';
                return;
            }
            const formatted = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 }).format(parseInt(rawVal, 10));
            target.value = formatted;
        });

        // Limpiar inputs .cop-currency-input antes del envío de formularios para enviar valor numérico limpio
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            form.querySelectorAll('.cop-currency-input').forEach(inp => {
                if (inp.value) {
                    inp.value = window.parseCopNumber(inp.value);
                }
            });
        }, true);

        // =========================================================================
        // HELPER GLOBAL PARA ENVIAR TICKETS A LA COLA DE IMPRESIÓN CENTRALIZADA
        // =========================================================================
        window.sendPrintToQueue = function(tipo, id, extra = {}) {
            const payload = Object.assign({ tipo: tipo, id: id }, extra);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            return fetch('{{ route("print-jobs.enqueue") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok && typeof window.califitNotify === 'function') {
                    window.califitNotify(data.message || 'Ticket enviado a la cola de impresión', 'success', 'Impresora Local POS');
                }
                return data;
            })
            .catch(err => {
                console.error('Error enviando a la cola de impresión:', err);
                if (typeof window.califitNotify === 'function') {
                    window.califitNotify('No se pudo comunicar con la cola de impresión', 'danger');
                }
            });
        };
    </script>

    {{-- MODAL GLOBAL DE CONFIRMACIÓN DE ACCIONES DESTRUCTIVAS (CALIFIT POS) --}}
    <div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-labelledby="globalConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
                <div class="modal-header bg-light border-bottom py-3 px-4">
                    <div class="d-flex align-items-center gap-2">
                        <div id="globalConfirmIconWrapper" class="rounded-circle bg-danger-subtle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                            <i id="globalConfirmIcon" class="bi bi-exclamation-triangle text-danger fs-5"></i>
                        </div>
                        <h6 class="modal-title fw-bold text-dark mb-0" id="globalConfirmModalLabel">Confirmar Acción</h6>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body py-4 px-4 bg-white">
                    <p id="globalConfirmMessage" class="text-muted mb-0" style="line-height: 1.6;">¿Deseas continuar con esta acción?</p>
                </div>
                <div class="modal-footer bg-light border-top py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Volver</button>
                    <button type="button" id="globalConfirmSubmitBtn" class="btn btn-danger px-4 fw-semibold">
                        <i class="bi bi-check-circle me-1"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="califit-toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 10999;"></div>
    @stack('scripts')
</body>
</html>
