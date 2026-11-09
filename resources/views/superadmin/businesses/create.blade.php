@extends('layouts.superadmin')

@section('title', 'Crear Negocio')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-dark p-4 p-md-5">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4 border-bottom border-secondary pb-3">
                <div>
                    <h3 class="fw-bold mb-1">Registrar Nuevo Emprendimiento</h3>
                    <p class="text-secondary small mb-0">Crea en un solo paso el negocio y su usuario administrador inicial.</p>
                </div>
                <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver
                </a>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger mb-4 py-2 px-3 small rounded-3 bg-danger bg-opacity-25 text-danger-emphasis border-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Corrige los siguientes errores:</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('superadmin.businesses.store') }}" novalidate>
                @csrf

                <h5 class="fw-bold text-primary mb-3"><i class="bi bi-building me-2"></i>1. Datos del Negocio</h5>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nombre Comercial <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('name') is-invalid @enderror"
                               id="name" name="name" value="{{ old('name') }}" placeholder="Ej: Tienda La Colina"
                               minlength="2" maxlength="100" required autofocus>
                    </div>
                    <div class="col-md-6">
                        <label for="business_type" class="form-label small fw-semibold text-secondary">Tipo de Negocio <span class="text-danger">*</span></label>
                        <select class="form-select bg-dark border-secondary text-light @error('business_type') is-invalid @enderror"
                                id="business_type" name="business_type" required>
                            <option value="retail" {{ old('business_type', 'retail') === 'retail' ? 'selected' : '' }}>
                                Comercio General / Retail (Productos, Stock Directo)
                            </option>
                            <option value="restaurant" {{ old('business_type') === 'restaurant' ? 'selected' : '' }}>
                                Restaurante / Comidas y Bebidas (Mesas, Recetas, Insumos)
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nit" class="form-label small fw-semibold text-secondary">NIT / Identificación</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('nit') is-invalid @enderror"
                               id="nit" name="nit" value="{{ old('nit') }}" placeholder="Ej: 900123456-1"
                               minlength="5" maxlength="15" inputmode="numeric">
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Teléfono / WhatsApp</label>
                        <input type="tel" class="form-control bg-dark border-secondary text-light @error('phone') is-invalid @enderror"
                               id="phone" name="phone" value="{{ old('phone') }}" placeholder="Ej: 3001234567"
                               minlength="7" maxlength="15" inputmode="tel">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold text-secondary">Correo del Negocio</label>
                        <input type="email" class="form-control bg-dark border-secondary text-light @error('email') is-invalid @enderror"
                               id="email" name="email" value="{{ old('email') }}" placeholder="contacto@negocio.com" maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label small fw-semibold text-secondary">Dirección</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('address') is-invalid @enderror"
                               id="address" name="address" value="{{ old('address') }}" placeholder="Ej: Calle 5 # 10-20, Cali"
                               minlength="5" maxlength="150">
                    </div>
                </div>

                <h5 class="fw-bold text-primary mb-3 border-top border-secondary pt-4"><i class="bi bi-person-badge me-2"></i>2. Administrador Inicial</h5>

                <div class="mb-3">
                    <label for="admin_name" class="form-label small fw-semibold text-secondary">Nombre del Administrador <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark border-secondary text-light @error('admin_name') is-invalid @enderror"
                           id="admin_name" name="admin_name" value="{{ old('admin_name') }}" placeholder="Ej: Andrés Felipe Gómez"
                           minlength="2" maxlength="80" required>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="admin_email" class="form-label small fw-semibold text-secondary">Correo del Administrador <span class="text-danger">*</span></label>
                        <input type="email" class="form-control bg-dark border-secondary text-light @error('admin_email') is-invalid @enderror"
                               id="admin_email" name="admin_email" value="{{ old('admin_email') }}" placeholder="admin@negocio.com" required>
                    </div>
                    <div class="col-md-6">
                        <label for="admin_password" class="form-label small fw-semibold text-secondary">Contraseña Inicial</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('admin_password') is-invalid @enderror"
                               id="admin_password" name="admin_password" value="{{ old('admin_password') }}"
                               placeholder="Opcional: dejar vacío para autogenerar">
                        <div class="form-text text-secondary small">Si la dejas en blanco, el sistema generará una segura y la mostrará una sola vez.</div>
                    </div>
                </div>

                <h5 class="fw-bold text-primary mb-3 border-top border-secondary pt-4"><i class="bi bi-robot me-2"></i>3. Notificaciones y Automatizaciones IA</h5>

                <div class="p-3 rounded-3 mb-4" style="background-color: rgba(30, 41, 59, 0.5); border: 1px solid #334155;">
                    <div class="form-check form-switch d-flex align-items-center justify-content-between p-0 mb-0">
                        <div>
                            <label class="form-check-label fw-bold text-light mb-1 d-block" for="nightly_report_enabled" style="cursor: pointer;">
                                <i class="bi bi-moon-stars-fill text-warning me-1"></i> Resumen Nocturno Automatizado (Asistente IA)
                            </label>
                            <div class="text-secondary small">
                                Envía automáticamente a las 11:30 PM el reporte gerencial de ventas, recaudos y stock crítico al correo del administrador.
                            </div>
                        </div>
                        <input type="hidden" name="nightly_report_enabled" value="0">
                        <input class="form-check-input ms-3" type="checkbox" role="switch" id="nightly_report_enabled" name="nightly_report_enabled" value="1" {{ old('nightly_report_enabled', '1') == '1' ? 'checked' : '' }} style="width: 2.8em; height: 1.5em; cursor: pointer;">
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top border-secondary pt-3">
                    <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary px-4 w-100 w-sm-auto text-center" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold w-100 w-sm-auto d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                        <i class="bi bi-check-circle me-1"></i> Crear Negocio y Administrador
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
