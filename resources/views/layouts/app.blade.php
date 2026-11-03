<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
    @include('partials.brand-head')

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.9375rem;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }

        /* Forzar visibilidad permanente de spinners numéricos en navegadores WebKit/Chromium */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: inner-spin-button !important;
            opacity: 1 !important;
            cursor: pointer;
        }
        /* Soporte en Firefox */
        input[type=number] {
            -moz-appearance: number-input;
        }

        /* Ocultar flechas nativas en inputs que ya cuentan con botones + y - */
        .input-stepper::-webkit-outer-spin-button,
        .input-stepper::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button,
        .input-group input[type=number]::-webkit-outer-spin-button,
        .input-group input[type=number]::-webkit-inner-spin-button {
            -webkit-appearance: none !important;
            margin: 0 !important;
            display: none !important;
            visibility: hidden !important;
            opacity: 0 !important;
        }
        .input-stepper,
        .qty-input,
        .input-group input[type=number] {
            -moz-appearance: textfield !important;
        }
        .navbar-custom {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            height: 62px;
            z-index: 1030;
        }
        .navbar-brand {
            font-weight: 700;
            color: #4f46e5 !important;
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
        .tenant-selector {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 2rem;
            padding: 0.35rem 0.95rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Layout con Sidebar */
        .app-layout {
            display: flex;
            min-height: calc(100vh - 62px);
        }

        @media (min-width: 992px) {
            .sidebar-desktop {
                position: fixed;
                top: 62px;
                bottom: 0;
                left: 0;
                width: 250px;
                background-color: #ffffff;
                border-right: 1px solid #e2e8f0;
                overflow-y: auto;
                padding: 1.25rem 0.75rem;
                z-index: 1020;
            }
            .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
                min-height: calc(100vh - 62px);
            }
        }

        @media (max-width: 991.98px) {
            .sidebar-desktop {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                min-height: calc(100vh - 62px);
            }
        }

        /* Estilos del Sidebar */
        .sidebar-section-label {
            font-size: 0.70rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
            padding: 0.85rem 1rem 0.35rem 1rem;
            margin-bottom: 0.15rem;
            user-select: none;
        }

        .sidebar-nav-container .nav-link {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #64748b;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-nav-container .nav-link:hover {
            background-color: #f1f5f9;
            color: #4f46e5 !important;
        }

        .sidebar-nav-container .nav-link.active {
            background-color: #eef2ff;
            color: #4f46e5 !important;
            font-weight: 600;
        }

        .sidebar-nav-container .nav-link.active i {
            color: #4f46e5 !important;
        }

        .sidebar-nav-container .nav-link i {
            font-size: 1.15rem;
            width: 1.5rem;
            text-align: center;
            flex-shrink: 0;
        }

        /* Banner no invasivo de vencimiento de suscripción */
        .subscription-banner {
            position: relative;
            z-index: 1010;
            font-size: 0.875rem;
        }
        .subscription-banner-warning {
            background-color: #fffbeb;
            border-bottom: 1px solid #fde68a !important;
            color: #92400e;
        }
        .subscription-banner-danger {
            background-color: #fef2f2;
            border-bottom: 1px solid #fecaca !important;
            color: #991b1b;
        }

        /* Salvaguarda para iconos de paginación SVG */
        .pagination svg {
            width: 1em !important;
            height: 1em !important;
            max-width: 16px !important;
            max-height: 16px !important;
            vertical-align: middle;
        }

        /* Botón Estandarizado de Creación Rápida de Cliente */
        .btn-open-quick-customer,
        #btnOpenQuickCustomer,
        .btn-quick-customer-trigger {
            background-color: #ffffff !important;
            color: #0d6efd !important;
            border: 1px solid #0d6efd !important;
            min-width: 42px;
            transition: all 0.15s ease-in-out;
        }
        .btn-open-quick-customer:hover,
        #btnOpenQuickCustomer:hover,
        .btn-quick-customer-trigger:hover,
        .btn-open-quick-customer:focus,
        #btnOpenQuickCustomer:focus,
        .btn-quick-customer-trigger:focus {
            background-color: #eff6ff !important;
            color: #0a58ca !important;
            border-color: #0a58ca !important;
        }

        /* Botón Estandarizado de Importación de Excel */
        .btn-import-excel,
        #btnOpenImportModal {
            background-color: #ffffff;
            color: #0d6efd;
            border: 1px solid #0d6efd;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }
        .btn-import-excel:hover,
        #btnOpenImportModal:hover {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            border-color: #0d6efd !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25) !important;
        }
        .btn-import-excel:hover i,
        #btnOpenImportModal:hover i {
            color: #ffffff !important;
        }
        .btn-import-excel:active,
        #btnOpenImportModal:active {
            background-color: #0b5ed7 !important;
            border-color: #0a58ca !important;
            color: #ffffff !important;
            transform: scale(0.96) translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }
        .btn-import-excel:focus-visible,
        #btnOpenImportModal:focus-visible {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.35) !important;
        }
    </style>
</head>
<body>
    {{-- Navbar Superior --}}
    <nav class="navbar navbar-custom sticky-top">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                {{-- Botón hamburguesa para móvil/tablet que abre el Offcanvas --}}
                <button class="btn btn-outline-secondary d-lg-none p-1 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Abrir menú de navegación">
                    <i class="bi bi-list fs-3"></i>
                </button>

                <a class="navbar-brand m-0 text-decoration-none" href="{{ route('dashboard') }}">
                    <x-brand size="sm" />
                </a>
            </div>

            {{-- Elementos de escritorio (>= 768px): Mantener 100% intacto --}}
            <div class="d-none d-md-flex align-items-center gap-2 gap-md-3">
                @if (isset($currentBusiness))
                    <span class="tenant-selector">
                        <i class="bi bi-shop text-primary"></i> <span>{{ $currentBusiness->name }}</span>
                    </span>
                @endif

                <span class="user-pill">
                    <i class="bi bi-person-circle me-1 text-primary"></i> <span>{{ auth()->user()->name }}</span>
                </span>

                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                        <i class="bi bi-box-arrow-right me-1"></i> Salir
                    </button>
                </form>
            </div>

            {{-- Menú de usuario móvil (< 768px): Avatar unificado con desplegable para evitar fragmentación --}}
            <div class="d-md-none dropdown">
                <button class="btn btn-light rounded-circle border shadow-sm p-0 d-flex align-items-center justify-content-center" 
                        type="button" 
                        id="mobileUserMenu" 
                        data-bs-toggle="dropdown" 
                        aria-expanded="false"
                        style="width: 40px; height: 40px;"
                        aria-label="Menú de usuario">
                    <i class="bi bi-person-circle text-primary fs-5"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2 mt-2" aria-labelledby="mobileUserMenu" style="min-width: 250px; border-radius: 0.85rem;">
                    @if (isset($currentBusiness))
                        <li class="px-3 py-2 bg-light border-bottom mb-1">
                            <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Negocio Activo</span>
                            <div class="fw-bold text-dark text-truncate d-flex align-items-center gap-2 mt-1">
                                <i class="bi bi-shop text-primary"></i>
                                <span class="text-truncate">{{ $currentBusiness->name }}</span>
                            </div>
                        </li>
                    @endif
                    <li class="px-3 py-2">
                        <span class="text-muted small fw-semibold text-uppercase d-block" style="font-size: 0.7rem; letter-spacing: 0.5px;">Usuario</span>
                        <div class="fw-semibold text-dark text-truncate d-flex align-items-center gap-2 mt-1">
                            <i class="bi bi-person-badge text-secondary"></i>
                            <span class="text-truncate">{{ auth()->user()->name }}</span>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary border mt-1">
                            {{ auth()->user()->isCurrentAdmin() ? 'Administrador' : 'Empleado' }}
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
            <h5 class="offcanvas-title fw-bold text-primary d-flex align-items-center gap-2" id="sidebarOffcanvasLabel">
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
            @if (isset($currentBusiness) && $currentBusiness->subscription_ends_at)
                @php
                    $subDays = $currentBusiness->daysUntilExpiration();
                    $subExpDate = $currentBusiness->subscription_ends_at->timezone('America/Bogota')->format('d M, Y');
                    $supportPhone = config('app.support_whatsapp', '573163765939');
                @endphp

                @if ($currentBusiness->isCriticalExpiring())
                    {{-- Caso 1 día antes o día de vencimiento (Falta 1 día o vence hoy) --}}
                    @php
                        $whenText = $subDays === 0 ? 'hoy' : 'mañana';
                        $waCriticalMsg = "Hola, deseo renovar la mensualidad de mi negocio {$currentBusiness->name}.";
                    @endphp
                    <div class="subscription-banner subscription-banner-danger px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-octagon-fill text-danger fs-5 flex-shrink-0"></i>
                            <span class="fw-medium">
                                ¡Atención! Tu mensualidad vence {{ $whenText }} ({{ $subExpDate }}). Por favor realiza tu pago para evitar la suspensión del servicio.
                            </span>
                        </div>
                        <a href="https://wa.me/{{ $supportPhone }}?text={{ rawurlencode($waCriticalMsg) }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="btn btn-danger btn-sm rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1">
                            <i class="bi bi-whatsapp"></i> Reportar Pago
                        </a>
                    </div>
                @elseif ($currentBusiness->isExpiringSoon())
                    {{-- Caso 3 días antes (Faltan 3 o 2 días) --}}
                    @php
                        $waWarningMsg = "Hola, deseo renovar la mensualidad de mi negocio {$currentBusiness->name}.";
                    @endphp
                    <div class="subscription-banner subscription-banner-warning px-3 py-2 d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-5 flex-shrink-0"></i>
                            <span class="fw-medium">
                                Recordatorio: Tu mensualidad vence en {{ $subDays }} días ({{ $subExpDate }}). Comunícate con soporte para renovar tu suscripción.
                            </span>
                        </div>
                        <a href="https://wa.me/{{ $supportPhone }}?text={{ rawurlencode($waWarningMsg) }}" 
                           target="_blank" 
                           rel="noopener noreferrer" 
                           class="btn btn-warning btn-sm rounded-pill px-3 py-1 fw-semibold d-inline-flex align-items-center gap-1 text-dark">
                            <i class="bi bi-whatsapp"></i> Renovar por WhatsApp
                        </a>
                    </div>
                @endif
            @endif

            <main class="container py-4">
                @if (session('status') && !session('import_result'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @elseif (session('success') && !session('import_result'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('info'))
                    <div class="alert alert-info alert-dismissible fade show rounded-3" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i> {{ session('info') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle (incluye Popper para dropdowns y offcanvas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Feedback táctil inmediato en formularios para evitar dobles envíos y sensación de lentitud
        document.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            const form = e.target;
            if (!form || !(form instanceof HTMLFormElement)) return;

            // Ignorar formularios que abren en nueva pestaña/ventana o marcados para no deshabilitar
            if (form.getAttribute('target') === '_blank' ||
                form.hasAttribute('data-no-disable') ||
                form.hasAttribute('data-no-spinner') ||
                form.dataset.noDisable !== undefined ||
                form.dataset.noSpinner !== undefined) {
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"]:not([disabled])');
            if (submitBtn) {
                if (submitBtn.getAttribute('formtarget') === '_blank' ||
                    submitBtn.hasAttribute('data-no-disable') ||
                    submitBtn.hasAttribute('data-no-spinner') ||
                    submitBtn.dataset.noDisable !== undefined ||
                    submitBtn.dataset.noSpinner !== undefined) {
                    return;
                }

                setTimeout(() => {
                    if (e.defaultPrevented) return;
                    submitBtn.disabled = true;
                    submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Procesando...';
                }, 0);
            }
        });

        // Restaurar estado si el usuario navega hacia atrás desde la caché del navegador
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                document.querySelectorAll('button[type="submit"][disabled]').forEach(btn => {
                    btn.disabled = false;
                    if (btn.dataset.originalHtml) {
                        btn.innerHTML = btn.dataset.originalHtml;
                    }
                });
            }
        });

        // Helpers globales de moneda COP (Pesos Colombianos)
        window.parseCopNumber = function (val) {
            if (val === null || val === undefined || val === '') return 0;
            if (typeof val === 'number') return isNaN(val) ? 0 : val;
            const str = String(val).trim();
            // Eliminar puntos de miles y reemplazar coma decimal por punto
            const cleaned = str.replace(/\./g, '').replace(',', '.');
            const num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        };

        window.formatCopInteger = function (val) {
            if (val === null || val === undefined || val === '') return '';
            const num = window.parseCopNumber(val);
            if (isNaN(num)) return '';
            const rounded = Math.round(num);
            return rounded.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        };

        // Formatea visualmente un input data-cop-money preservando la posición exacta del cursor
        function formatCopMoneyInput(input) {
            const rawVal = input.value;
            if (!rawVal) return;

            const cursor = input.selectionStart || rawVal.length;
            const textBeforeCursor = rawVal.substring(0, cursor);
            const digitsBeforeCursor = textBeforeCursor.replace(/\D/g, '').length;

            let digits = rawVal.replace(/\D/g, '');
            // Eliminar ceros iniciales si hay dígitos adicionales (ej. 05000 -> 5000)
            if (/^0+[1-9]/.test(digits)) {
                digits = digits.replace(/^0+/, '');
            }

            if (digits === '') {
                input.value = '';
                return;
            }

            const formatted = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            input.value = formatted;

            // Recalcular posición del cursor basada en la cantidad de dígitos previos
            let newCursor = 0;
            let count = 0;
            for (let i = 0; i < formatted.length; i++) {
                if (/\d/.test(formatted[i])) {
                    count++;
                }
                if (count >= digitsBeforeCursor) {
                    newCursor = i + 1;
                    break;
                }
            }
            if (digitsBeforeCursor === 0) newCursor = 0;
            if (newCursor > formatted.length) newCursor = formatted.length;

            input.setSelectionRange(newCursor, newCursor);
        }

        // Actualiza el indicador dinámico en vivo en inputs de número (spinners)
        function updateCopLivePreview(input) {
            let preview = input.parentElement?.querySelector('.cop-live-preview');
            if (!preview && input.closest('.input-group')) {
                preview = input.closest('.input-group').parentElement?.querySelector('.cop-live-preview');
            }
            if (!preview) return;

            const val = window.parseCopNumber(input.value);
            preview.textContent = val > 0 ? '$' + window.formatCopInteger(val) + ' COP' : '';
        }

        // Ergonomía global: Auto-selección en foco y formateo inicial COP
        function setupNumberInputErgonomics() {
            // Formatear valores iniciales en inputs COP
            document.querySelectorAll('input[data-cop-money="true"]').forEach(input => {
                if (input.value && input.value !== '0' && input.value !== '0.00') {
                    const num = window.parseCopNumber(input.value);
                    if (num > 0) {
                        input.value = window.formatCopInteger(num);
                    }
                }
            });

            // Live preview inicial para inputs de tarifas y montos
            document.querySelectorAll('input[name="delivery_fee"], input[data-cop-live-preview="true"]').forEach(input => {
                updateCopLivePreview(input);
            });

            document.querySelectorAll('input[type="number"], input[data-cop-money="true"], .currency-input, input[name="cost_price"], input[name="sale_price"], input[name="stock"], input[name="min_stock"]').forEach(input => {
                if (input.dataset.ergonomicsAttached) return;
                input.dataset.ergonomicsAttached = 'true';

                input.addEventListener('focus', function () {
                    this.select();
                });
            });
        }

        // Limpieza automática de puntos de miles al enviar cualquier formulario
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form || !form.querySelectorAll) return;

            form.querySelectorAll('input[data-cop-money="true"]').forEach(input => {
                if (input.value) {
                    input.value = String(input.value).replace(/\./g, '').trim();
                }
            });
        }, true);

        // Prevenir que la tecla Backspace quede atrapada detrás de un separador de miles
        document.addEventListener('keydown', function (e) {
            const target = e.target;
            if (target && target.matches('input[data-cop-money="true"]')) {
                if (e.key === 'Backspace') {
                    const pos = target.selectionStart;
                    if (pos === target.selectionEnd && pos > 0 && target.value[pos - 1] === '.') {
                        target.setSelectionRange(pos - 1, pos - 1);
                    }
                }
            }
        });

        // Prevenir que la rueda del ratón (wheel) altere valores numéricos accidentalmente
        document.addEventListener('wheel', function (e) {
            if (document.activeElement && document.activeElement.type === 'number') {
                document.activeElement.blur();
            }
        }, { passive: true });

        // Prevenir caracteres no numéricos prohibidos en inputs de números positivos
        document.addEventListener('keydown', function (e) {
            const target = e.target;
            if (target && target.matches('input[type="number"]') && target.getAttribute('min') === '0') {
                if (e.key === 'e' || e.key === 'E' || e.key === '+' || (e.key === '-' && !target.hasAttribute('data-allow-negative'))) {
                    e.preventDefault();
                }
            }
        });

        // Motor global y declarativo de higienización y restricción de inputs en tiempo real
        document.addEventListener('input', function (e) {
            const target = e.target;
            if (!target || !target.tagName || (target.tagName !== 'INPUT' && target.tagName !== 'TEXTAREA')) return;

            // 0. Inputs de moneda COP en vivo (puntos de miles)
            if (target.matches('input[data-cop-money="true"]')) {
                formatCopMoneyInput(target);
                updateCopLivePreview(target);
                return;
            }

            // Live preview para inputs de tipo number (como delivery_fee)
            if (target.matches('input[name="delivery_fee"], input[data-cop-live-preview="true"]')) {
                updateCopLivePreview(target);
            }

            // 1. Inputs numéricos: supresión reactiva de ceros a la izquierda
            if (target.matches('input[type="number"], .currency-input, input[name="cost_price"], input[name="sale_price"], input[name="stock"], input[name="min_stock"]')) {
                const val = target.value;
                if (/^0+[1-9]/.test(val)) {
                    target.value = val.replace(/^0+/, '');
                }
                return;
            }

            // 2. Teléfono / WhatsApp: Solo dígitos y prefijo + opcional al inicio (longitud 7 a 15)
            if (target.matches('input[type="tel"], input[name*="phone"], input[data-input-type="phone"]')) {
                const val = target.value;
                let cleaned = val.replace(/[^0-9+]/g, '');
                if (cleaned.startsWith('+')) {
                    cleaned = '+' + cleaned.substring(1).replace(/\+/g, '');
                } else {
                    cleaned = cleaned.replace(/\+/g, '');
                }
                if (cleaned.length > 15) {
                    cleaned = cleaned.slice(0, 15);
                }
                if (val !== cleaned) {
                    target.value = cleaned;
                }
                return;
            }

            // 3. Documento / Cédula / NIT: Solo dígitos y máximo un guion para dígito de verificación
            if (target.matches('input[name="document"], input[name="document_number"], input[name="nit"], input[name="identification_number"], input[data-input-type="document"]')) {
                const val = target.value;
                let cleaned = val.replace(/[^0-9-]/g, '');
                const firstDash = cleaned.indexOf('-');
                if (firstDash !== -1) {
                    cleaned = cleaned.substring(0, firstDash + 1) + cleaned.substring(firstDash + 1).replace(/-/g, '');
                }
                if (cleaned.length > 15) {
                    cleaned = cleaned.slice(0, 15);
                }
                if (val !== cleaned) {
                    target.value = cleaned;
                }
                return;
            }

            // 4. Nombre de Personas (Clientes, Meseros, Contactos): Solo letras, acentos, espacios, puntos y apóstrofes
            if (target.matches('input[name="customer_name"], input[name="waiter_name"], input[name="contact_name"], input[data-person-name]')) {
                const val = target.value;
                let cleaned = val.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s.']/g, '').replace(/\s{2,}/g, ' ');
                if (cleaned.length > 80) {
                    cleaned = cleaned.slice(0, 80);
                }
                if (val !== cleaned) {
                    target.value = cleaned;
                }
                return;
            }

            // 5. Dirección de Entrega / Ubicación: Caracteres alfanuméricos viales colombianos
            if (target.matches('input[name="delivery_address"], input[name="address"], textarea[name="delivery_address"], textarea[name="address"]')) {
                const val = target.value;
                let cleaned = val.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑüÜ\s#\-\.,\/°]/g, '');
                if (cleaned.length > 150) {
                    cleaned = cleaned.slice(0, 150);
                }
                if (val !== cleaned) {
                    target.value = cleaned;
                }
                return;
            }
        });

        document.addEventListener('DOMContentLoaded', setupNumberInputErgonomics);
    </script>
    @stack('scripts')
</body>
</html>
