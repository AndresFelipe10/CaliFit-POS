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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary: #10B981;
            --brand-primary-hover: #059669;
            --brand-dark: #111827;
            --brand-slate: #1F2937;
            --brand-surface: #FFFFFF;
            --brand-light: #F9FAFB;
            --brand-border: #E5E7EB;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--brand-surface);
            color: #1F2937;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Columna de Marca (Izquierda en Desktop) */
        .split-brand-col {
            background: radial-gradient(circle at 50% 28%, rgba(16, 185, 129, 0.15) 0%, rgba(17, 24, 39, 1) 75%), #111827;
            color: #FFFFFF;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .split-brand-col::before {
            content: '';
            position: absolute;
            top: -25%;
            left: -25%;
            width: 150%;
            height: 150%;
            background: radial-gradient(circle at center, rgba(16, 185, 129, 0.06) 0%, transparent 60%);
            pointer-events: none;
        }

        .brand-logo-hero {
            max-height: 310px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 15px 25px rgba(0, 0, 0, 0.35));
            transition: transform 0.3s ease;
            margin-bottom: 0.75rem !important;
        }

        .brand-logo-hero:hover {
            transform: scale(1.02);
        }

        /* Columna de Formulario (Derecha en Desktop) */
        .split-form-col {
            background-color: var(--brand-surface);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .auth-form-container {
            width: 100%;
            max-width: 440px;
        }

        /* Inputs & Controls */
        .form-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.35rem;
        }

        .form-control {
            border-radius: 0.65rem;
            padding: 0.75rem 1rem;
            border: 1px solid #D1D5DB;
            font-size: 0.95rem;
            background-color: #FFFFFF;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.18);
            background-color: #FFFFFF;
        }

        .input-group-text {
            border-radius: 0.65rem;
            border: 1px solid #D1D5DB;
            background-color: #F9FAFB;
            color: #6B7280;
        }

        .btn-primary-custom {
            background-color: var(--brand-primary);
            border: none;
            border-radius: 0.65rem;
            padding: 0.8rem 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: #FFFFFF;
            width: 100%;
            min-height: 44px;
            transition: all 0.2s ease-in-out;
        }

        .btn-primary-custom:hover {
            background-color: var(--brand-primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
            color: #FFFFFF;
        }

        .btn-primary-custom:active {
            transform: translateY(0);
        }

        .password-toggle-btn {
            border-color: #D1D5DB;
            background-color: #F9FAFB;
            color: #6B7280;
            cursor: pointer;
            min-width: 44px;
            min-height: 44px;
        }

        .password-toggle-btn:hover {
            color: #111827;
            background-color: #F3F4F6;
        }

        /* Responsive Mobile / Tablet */
        @media (max-width: 991.98px) {
            .split-brand-col {
                min-height: auto !important;
                padding-top: 1.5rem !important;
                padding-bottom: 1.25rem !important;
            }

            .brand-logo-hero {
                max-height: 170px !important;
                margin-bottom: 0.75rem !important;
            }

            .split-form-col {
                min-height: auto !important;
                padding-top: 1.25rem !important;
                padding-bottom: 2rem !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0 min-vh-100">
            {{-- Columna Izquierda: Marca e Impacto (50%) --}}
            <div class="col-12 col-lg-6 split-brand-col p-4 p-md-5 text-center">
                <div class="position-relative z-1 my-auto py-2 py-lg-3">
                    <img src="{{ asset('images/brand/logo-califit.png') }}" 
                         alt="SaborCaliFit" 
                         class="brand-logo-hero">
                    
                    <h2 class="fw-bold text-white mb-1 tracking-tight" style="letter-spacing: -0.02em; font-size: clamp(1.4rem, 2.5vw, 1.85rem);">
                        SABORCALIFIT
                    </h2>
                    
                    <p class="fw-semibold mb-2 mb-lg-3" style="color: #34D399; font-size: clamp(0.9rem, 1.5vw, 1.05rem); letter-spacing: 0.02em;">
                        Nutre tu cuerpo &bull; Impulsa tu vida
                    </p>
                    
                    <div class="d-inline-flex align-items-center text-white-50 small px-3 py-1 rounded-pill" style="background: rgba(255, 255, 255, 0.06); border: 1px solid rgba(255, 255, 255, 0.1);">
                        <i class="bi bi-geo-alt-fill me-1" style="color: #10B981;"></i>
                        <span>Bochalema &bull; Arbolatta Mall, Cali</span>
                    </div>
                </div>

                {{-- Distintivo inferior en escritorio --}}
                <div class="position-relative z-1 mt-auto pt-3 text-white-50 small d-none d-lg-block">
                    <span class="badge bg-dark border border-secondary text-white-50 px-3 py-1 fw-normal" style="font-size: 0.72rem; letter-spacing: 0.05em;">
                        SISTEMA POS &bull; GESTIÓN INTEGRAL
                    </span>
                </div>
            </div>

            {{-- Columna Derecha: Formulario de Autenticación (50%) --}}
            <div class="col-12 col-lg-6 split-form-col p-4 p-md-5">
                <div class="auth-form-container my-auto">
                    @hasSection('heading')
                        <div class="mb-4">
                            <h1 class="h3 fw-bold text-dark mb-1">@yield('heading')</h1>
                            @hasSection('subheading')
                                <p class="text-muted small mb-0">@yield('subheading')</p>
                            @endif
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="alert alert-success d-flex align-items-center mb-4 py-2 px-3 small rounded-3" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div>{{ session('status') }}</div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3" role="alert">
                            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Errores encontrados:</div>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')

                    @hasSection('footer')
                        <div class="mt-4 pt-3 border-top text-center text-muted small">
                            @yield('footer')
                        </div>
                    @endif
                </div>

                <div class="mt-auto pt-3 text-muted small text-center">
                    <span class="text-secondary">&copy; {{ date('Y') }} SaborCaliFit Bochalema</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
