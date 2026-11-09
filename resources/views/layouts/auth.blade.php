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
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #334155;
        }
        .auth-card {
            background: #ffffff;
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 440px;
        }
        .brand-badge {
            background: #e0e7ff;
            color: #4338ca;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .form-control {
            border-radius: 0.65rem;
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
            transition: all 0.2s ease-in-out;
        }
        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .btn-primary-custom {
            background: #4f46e5;
            border: none;
            border-radius: 0.65rem;
            padding: 0.75rem 1.25rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: #ffffff;
            width: 100%;
            transition: all 0.2s ease-in-out;
        }
        .btn-primary-custom:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.35);
        }
        .auth-footer {
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            padding: 1.25rem;
            text-align: center;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container p-3">
        <div class="d-flex justify-content-center">
            <div class="auth-card">
                <div class="p-4 p-sm-5">
                    <div class="text-center mb-4">
                        <div class="d-flex justify-content-center mb-3">
                            <x-brand size="lg" />
                        </div>
                        <h1 class="h4 fw-bold text-dark mb-1">@yield('heading')</h1>
                        <p class="text-muted small">@yield('subheading')</p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success d-flex align-items-center mb-4 py-2 px-3 small rounded-3" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div>{{ session('status') }}</div>
                        </div>
                    @endif

                    @if (session('info'))
                        <div class="alert alert-info d-flex align-items-center mb-4 py-2 px-3 small rounded-3" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <div>{{ session('info') }}</div>
                        </div>
                    @endif

                    @if (session('warning') && ! session('session_warning'))
                        <div class="alert alert-warning d-flex align-items-center mb-4 py-2 px-3 small rounded-3" role="alert">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <div>{{ session('warning') }}</div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger d-flex align-items-center mb-4 py-2 px-3 small rounded-3" role="alert">
                            <i class="bi bi-slash-circle-fill me-2"></i>
                            <div>{{ session('error') }}</div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3" role="alert">
                            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Hubo errores con tu solicitud:</div>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @yield('content')
                </div>

                <div class="auth-footer">
                    @yield('footer')
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
