@extends('layouts.superadmin')

@section('title', 'Editar Negocio')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card card-dark p-4 p-md-5">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4 border-bottom border-secondary pb-3">
                <div>
                    <h3 class="fw-bold mb-1">Editar Negocio</h3>
                    <p class="text-secondary small mb-0">Modifica los datos comerciales de {{ $business->name }}</p>
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

            {{-- Estado de Suscripción y Renovación --}}
            <div class="card card-dark p-3 mb-4 border border-secondary border-opacity-50 bg-secondary bg-opacity-10">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <div class="text-secondary small fw-medium">Vencimiento Mensualidad</div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="fs-5 fw-bold text-white">
                                {{ $business->subscription_ends_at ? $business->subscription_ends_at->timezone('America/Bogota')->format('d M, Y') : 'Sin fecha asignada' }}
                            </span>
                            @if ($business->subscription_ends_at)
                                @php
                                    $daysLeft = $business->daysUntilExpiration();
                                @endphp
                                @if ($daysLeft < 0)
                                    <span class="badge bg-danger bg-opacity-75 text-white border border-danger">
                                        <i class="bi bi-slash-circle-fill me-1"></i>Vencida (hace {{ abs($daysLeft) }} {{ abs($daysLeft) === 1 ? 'día' : 'días' }})
                                    </span>
                                @elseif ($business->isCriticalExpiring())
                                    <span class="badge bg-danger text-white">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i>Notif. Crítica ({{ $daysLeft === 0 ? 'Vence hoy' : '1d restante' }})
                                    </span>
                                @elseif ($business->isExpiringSoon())
                                    <span class="badge bg-warning text-dark border border-warning">
                                        <i class="bi bi-bell-fill me-1"></i>Notif. Preventiva ({{ $daysLeft }}d restantes)
                                    </span>
                                @else
                                    <span class="badge bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25">
                                        <i class="bi bi-check-circle-fill me-1"></i>Al día (Sin notif.)
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <button type="button" class="btn btn-success btn-sm px-3 fw-semibold"
                            data-bs-toggle="modal" data-bs-target="#renewSubscriptionModal">
                        <i class="bi bi-arrow-repeat me-1"></i> Renovar 30 días
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('superadmin.businesses.update', $business) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label small fw-semibold text-secondary">Nombre Comercial <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('name') is-invalid @enderror"
                               id="name" name="name" value="{{ old('name', $business->name) }}"
                               minlength="2" maxlength="100" required autofocus>
                    </div>
                    <div class="col-md-6">
                        <label for="business_type" class="form-label small fw-semibold text-secondary">Tipo de Negocio <span class="text-danger">*</span></label>
                        <select class="form-select bg-dark border-secondary text-light @error('business_type') is-invalid @enderror"
                                id="business_type" name="business_type" required>
                            <option value="retail" {{ old('business_type', $business->business_type) === 'retail' ? 'selected' : '' }}>
                                Comercio General / Retail (Productos, Stock Directo)
                            </option>
                            <option value="restaurant" {{ old('business_type', $business->business_type) === 'restaurant' ? 'selected' : '' }}>
                                Restaurante / Comidas y Bebidas (Mesas, Recetas, Insumos)
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nit" class="form-label small fw-semibold text-secondary">NIT / Identificación</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('nit') is-invalid @enderror"
                               id="nit" name="nit" value="{{ old('nit', $business->nit) }}"
                               minlength="5" maxlength="15" inputmode="numeric">
                    </div>
                    <div class="col-md-6">
                        <label for="phone" class="form-label small fw-semibold text-secondary">Teléfono / WhatsApp</label>
                        <input type="tel" class="form-control bg-dark border-secondary text-light @error('phone') is-invalid @enderror"
                               id="phone" name="phone" value="{{ old('phone', $business->phone) }}"
                               minlength="7" maxlength="15" inputmode="tel">
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="email" class="form-label small fw-semibold text-secondary">Correo Electrónico</label>
                        <input type="email" class="form-control bg-dark border-secondary text-light @error('email') is-invalid @enderror"
                               id="email" name="email" value="{{ old('email', $business->email) }}" maxlength="150">
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label small fw-semibold text-secondary">Dirección</label>
                        <input type="text" class="form-control bg-dark border-secondary text-light @error('address') is-invalid @enderror"
                               id="address" name="address" value="{{ old('address', $business->address) }}"
                               minlength="5" maxlength="150">
                    </div>
                </div>

                <div class="mb-4 p-3 rounded-3 border border-secondary border-opacity-50 bg-dark">
                    <label for="subscription_ends_at" class="form-label small fw-semibold text-light">
                        <i class="bi bi-calendar-event text-primary me-1"></i> Fecha de Vencimiento de Suscripción (Corte Mensual)
                    </label>
                    <input type="date" class="form-control bg-dark border-secondary text-light @error('subscription_ends_at') is-invalid @enderror"
                           id="subscription_ends_at" name="subscription_ends_at"
                           value="{{ old('subscription_ends_at', $business->subscription_ends_at?->timezone('America/Bogota')->format('Y-m-d')) }}">
                    @error('subscription_ends_at')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text text-secondary small mt-1">
                        Puedes ajustar libremente la fecha límite de mensualidad para corregir días o configurar un ciclo específico.
                    </div>
                </div>

                <div class="mb-4 p-3 rounded-3 border border-secondary border-opacity-50 bg-dark">
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
                        <input class="form-check-input ms-3" type="checkbox" role="switch" id="nightly_report_enabled" name="nightly_report_enabled" value="1" {{ old('nightly_report_enabled', $business->nightly_report_enabled) ? 'checked' : '' }} style="width: 2.8em; height: 1.5em; cursor: pointer;">
                    </div>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 border-top border-secondary pt-3">
                    <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary px-4 w-100 w-sm-auto text-center" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold w-100 w-sm-auto d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Superadmin de Renovación de Suscripción --}}
<div class="modal fade" id="renewSubscriptionModal" tabindex="-1" aria-labelledby="renewSubscriptionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3 bg-success text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center text-white" id="renewSubscriptionModalLabel">
                    <i class="bi bi-arrow-repeat me-2 fs-5"></i> Renovar Suscripción (30 Días)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 d-flex align-items-start mb-0 rounded-3">
                    <i class="bi bi-calendar-check-fill text-success fs-3 me-3 flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold text-white mb-1">¿Renovar 30 días calendario la suscripción de {{ $business->name }}?</div>
                        <div class="small text-secondary">Se extenderá la fecha de corte 30 días calendario manteniendo intactos los datos del negocio.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="{{ route('superadmin.businesses.renewSubscription', $business) }}" method="POST" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">
                        <i class="bi bi-arrow-repeat me-1"></i> Confirmar Renovación
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
