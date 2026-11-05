@extends('layouts.superadmin')

@section('title', 'Gestión de Negocios')

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
    <div>
        <h1 class="fs-3 fw-bold text-white mb-1">Negocios Registrados</h1>
        <p class="text-secondary small mb-0">Administra los negocios de la plataforma PuntoStock</p>
    </div>
    <a href="{{ route('superadmin.businesses.create') }}" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-3 py-2 fw-semibold w-100 w-sm-auto text-nowrap rounded-3 shadow-sm" style="min-height: 44px;">
        <i class="bi bi-plus-circle fs-5"></i>
        <span>Crear Nuevo Negocio</span>
    </a>
</div>

<div class="card card-dark border-secondary border-opacity-25 rounded-3 p-3 mb-4 shadow-sm">
    <form method="GET" action="{{ route('superadmin.businesses.index') }}" class="row g-2 align-items-end">
        <div class="col-12 col-md-5 col-lg-5">
            <div class="input-group">
                <span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control bg-dark border-secondary text-light" 
                       placeholder="Buscar por nombre, NIT, teléfono o email..." value="{{ $search }}" style="min-height: 42px;">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3 col-lg-3">
            <select name="status" class="form-select bg-dark border-secondary text-light" style="min-height: 42px;">
                <option value="">Todos los estados</option>
                <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Activos</option>
                <option value="inactive" {{ $selectedStatus === 'inactive' ? 'selected' : '' }}>Suspendidos</option>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-4 col-lg-4 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary px-3 flex-fill flex-sm-grow-0 d-inline-flex align-items-center justify-content-center gap-1" style="min-height: 42px;">
                <i class="bi bi-funnel"></i> Filtrar
            </button>
            @if ($search || $selectedStatus)
                <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary px-3 flex-fill flex-sm-grow-0 d-inline-flex align-items-center justify-content-center gap-1" style="min-height: 42px;">
                    <i class="bi bi-x-circle"></i> Limpiar
                </a>
            @endif
        </div>
    </form>
</div>

<div class="card card-dark border-secondary border-opacity-25 rounded-4 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="table table-dark-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 180px;">Negocio</th>
                    <th style="min-width: 130px;">NIT / Identificación</th>
                    <th style="min-width: 140px;">Contacto</th>
                    <th style="min-width: 150px;">Administrador</th>
                    <th style="min-width: 180px;">Vencimiento Mensualidad</th>
                    <th style="min-width: 110px;">Estado</th>
                    <th class="text-end" style="min-width: 200px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($businesses as $biz)
                    @php
                        $admin = $biz->users->first();
                    @endphp
                    <tr>
                        <td class="fw-semibold">
                            <div>
                                <i class="bi {{ $biz->isRestaurant() ? 'bi-egg-fried text-warning' : 'bi-shop text-primary' }} me-2"></i>{{ $biz->name }}
                            </div>
                            <div class="mt-1">
                                @if ($biz->isRestaurant())
                                    <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25 small px-2 py-0.5">
                                        <i class="bi bi-fire me-1"></i>Restaurante
                                    </span>
                                @else
                                    <span class="badge bg-secondary bg-opacity-25 text-light border border-secondary border-opacity-25 small px-2 py-0.5">
                                        <i class="bi bi-tag me-1"></i>Retail
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $biz->nit ?: '—' }}</td>
                        <td>
                            @if ($biz->phone)
                                <div><i class="bi bi-telephone text-muted me-1"></i>{{ $biz->phone }}</div>
                            @endif
                            @if ($biz->email)
                                <div class="small text-secondary">{{ $biz->email }}</div>
                            @endif
                            @if (! $biz->phone && ! $biz->email)
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($admin)
                                <div class="fw-medium">{{ $admin->name }}</div>
                                <div class="small text-secondary">{{ $admin->email }}</div>
                            @else
                                <span class="text-muted small">Sin admin</span>
                            @endif
                        </td>
                        <td>
                            @if ($biz->subscription_ends_at)
                                <div class="fw-bold mb-1" style="color: #1e293b !important; font-size: 0.95rem;">
                                    {{ $biz->subscription_ends_at->timezone('America/Bogota')->format('d M, Y') }}
                                </div>
                                @php
                                    $daysLeft = $biz->daysUntilExpiration();
                                @endphp
                                @if ($daysLeft < 0)
                                    <span class="badge bg-danger bg-opacity-75 text-white border border-danger">
                                        <i class="bi bi-slash-circle-fill me-1"></i>Vencida (hace {{ abs($daysLeft) }} {{ abs($daysLeft) === 1 ? 'día' : 'días' }})
                                    </span>
                                @elseif ($biz->isCriticalExpiring())
                                    <span class="badge bg-danger text-white">
                                        <i class="bi bi-exclamation-octagon-fill me-1"></i>Notif. Crítica ({{ $daysLeft === 0 ? 'Vence hoy' : '1d restante' }})
                                    </span>
                                @elseif ($biz->isExpiringSoon())
                                    <span class="badge bg-warning text-dark border border-warning">
                                        <i class="bi bi-bell-fill me-1"></i>Notif. Preventiva ({{ $daysLeft }}d restantes)
                                    </span>
                                @else
                                    <span class="badge bg-info bg-opacity-25 text-info-emphasis border border-info border-opacity-25">
                                        <i class="bi bi-check-circle-fill me-1"></i>Al día (Sin notif.)
                                    </span>
                                @endif
                            @else
                                @php
                                    $daysLeft = null;
                                @endphp
                                <span class="badge bg-secondary bg-opacity-25 text-secondary">Sin fecha</span>
                            @endif
                        </td>
                        <td>
                            @if ($biz->status === 'active')
                                <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">
                                    <i class="bi bi-check-circle me-1"></i>Activo
                                </span>
                            @else
                                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25">
                                    <i class="bi bi-pause-circle me-1"></i>Suspendido
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                <a href="{{ route('superadmin.businesses.edit', $biz) }}" class="btn btn-outline-primary btn-action-table" style="opacity: 1 !important; visibility: visible !important;" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="{{ route('superadmin.businesses.users', $biz) }}" class="btn btn-outline-info btn-action-table" title="Ver usuarios">
                                    <i class="bi bi-people"></i>
                                </a>
                                @php
                                    $expFormatted = $biz->subscription_ends_at ? $biz->subscription_ends_at->timezone('America/Bogota')->format('d/m/Y') : 'sin fecha';
                                    $nextCutFormatted = $biz->subscription_ends_at ? $biz->subscription_ends_at->copy()->timezone('America/Bogota')->addDays(30)->format('d/m/Y') : 'en 30 días';
                                    if ($daysLeft !== null && $daysLeft > 3) {
                                        $confirmRenew = "Este negocio aún tiene {$daysLeft} días vigentes (vence el {$expFormatted}). ¿Seguro que deseas extender 30 días adicionales a su fecha de corte?";
                                    } else {
                                        $confirmRenew = "¿Confirmas la renovación de 30 días para {$biz->name}? Nueva fecha de corte: {$nextCutFormatted}.";
                                    }
                                @endphp
                                <button type="button"
                                        class="btn btn-outline-success btn-action-table btn-renew-biz"
                                        title="Renovar 30 días"
                                        data-bs-toggle="modal"
                                        data-bs-target="#renewSubscriptionModal"
                                        data-action-url="{{ route('superadmin.businesses.renewSubscription', $biz) }}"
                                        data-business-name="{{ $biz->name }}"
                                        data-renew-message="{{ $confirmRenew }}">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                <button type="button"
                                        class="btn btn-outline-warning btn-action-table btn-reset-biz-pwd"
                                        title="Restablecer contraseña del admin"
                                        data-bs-toggle="modal"
                                        data-bs-target="#resetPasswordModal"
                                        data-action-url="{{ route('superadmin.businesses.resetPassword', $biz) }}"
                                        data-business-name="{{ $biz->name }}">
                                    <i class="bi bi-key"></i>
                                </button>
                                @if ($biz->status === 'active')
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-action-table btn-toggle-biz-status" 
                                            title="Suspender negocio"
                                            data-bs-toggle="modal"
                                            data-bs-target="#toggleBusinessStatusModal"
                                            data-action-url="{{ route('superadmin.businesses.toggleStatus', $biz) }}"
                                            data-business-name="{{ $biz->name }}"
                                            data-business-status="active">
                                        <i class="bi bi-pause-fill"></i>
                                    </button>
                                @else
                                    <button type="button" 
                                            class="btn btn-outline-success btn-action-table btn-toggle-biz-status" 
                                            title="Reactivar negocio"
                                            data-bs-toggle="modal"
                                            data-bs-target="#toggleBusinessStatusModal"
                                            data-action-url="{{ route('superadmin.businesses.toggleStatus', $biz) }}"
                                            data-business-name="{{ $biz->name }}"
                                            data-business-status="inactive">
                                        <i class="bi bi-play-fill"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-secondary">
                            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
                            No se encontraron negocios registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($businesses->hasPages())
    <div class="mt-4">
        {{ $businesses->links() }}
    </div>
@endif

{{-- Modal Superadmin de Cambio de Estado de Negocio --}}
<div class="modal fade" id="toggleBusinessStatusModal" tabindex="-1" aria-labelledby="toggleBusinessStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3" id="toggleBizModalHeader">
                <h5 class="modal-title fw-bold d-flex align-items-center text-white" id="toggleBusinessStatusModalLabel">
                    <i class="bi me-2 fs-5" id="toggleBizModalIcon"></i>
                    <span id="toggleBizModalTitle">Cambiar Estado</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert d-flex align-items-start mb-0 rounded-3" id="toggleBizModalAlert" role="alert">
                    <i class="bi fs-3 me-3 flex-shrink-0" id="toggleBizAlertIcon"></i>
                    <div>
                        <div class="fw-semibold text-white mb-1" id="toggleBizModalMessage"></div>
                        <div class="small text-secondary" id="toggleBizModalSubtext"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="toggleBusinessStatusForm" class="d-inline m-0" data-no-disable>
                    @csrf
                    <button type="submit" class="btn rounded-pill px-4 fw-bold" id="btnConfirmToggleBiz">
                        Confirmar
                    </button>
                </form>
            </div>
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
                        <div class="fw-semibold text-white mb-1" id="renewModalMessage">¿Confirmas la renovación de 30 días para este negocio?</div>
                        <div class="small text-secondary">Se extenderá la fecha de corte 30 días calendario y se habilitarán las funciones de su plan.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="renewSubscriptionForm" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold">
                        <i class="bi bi-arrow-repeat me-1"></i> Confirmar Renovación
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal Superadmin de Restablecer Contraseña Admin --}}
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3 bg-warning text-dark">
                <h5 class="modal-title fw-bold d-flex align-items-center text-dark" id="resetPasswordModalLabel">
                    <i class="bi bi-key-fill me-2 fs-5"></i> Restablecer Contraseña del Administrador
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning bg-warning bg-opacity-10 border border-warning border-opacity-25 d-flex align-items-start mb-0 rounded-3">
                    <i class="bi bi-shield-lock-fill text-warning fs-3 me-3 flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold text-white mb-1">¿Restablecer la contraseña del administrador de <strong id="resetPwdBizName" class="text-warning">-</strong>?</div>
                        <div class="small text-secondary">Se generará una clave temporal que se mostrará en pantalla tras confirmar.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="resetPasswordForm" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                        <i class="bi bi-key-fill me-1"></i> Restablecer Clave
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const renewModal = document.getElementById('renewSubscriptionModal');
    if (renewModal) {
        renewModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const actionUrl = btn.getAttribute('data-action-url') || '';
            const msg = btn.getAttribute('data-renew-message') || '¿Confirmas la renovación de 30 días?';
            const form = document.getElementById('renewSubscriptionForm');
            const msgEl = document.getElementById('renewModalMessage');
            if (form) form.action = actionUrl;
            if (msgEl) msgEl.textContent = msg;
        });
    }

    const resetPwdModal = document.getElementById('resetPasswordModal');
    if (resetPwdModal) {
        resetPwdModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const actionUrl = btn.getAttribute('data-action-url') || '';
            const bizName = btn.getAttribute('data-business-name') || '';
            const form = document.getElementById('resetPasswordForm');
            const nameEl = document.getElementById('resetPwdBizName');
            if (form) form.action = actionUrl;
            if (nameEl) nameEl.textContent = bizName;
        });
    }

    const modalEl = document.getElementById('toggleBusinessStatusModal');
    if (!modalEl) return;

    const modalHeader = document.getElementById('toggleBizModalHeader');
    const modalTitle = document.getElementById('toggleBizModalTitle');
    const modalIcon = document.getElementById('toggleBizModalIcon');
    const modalAlert = document.getElementById('toggleBizModalAlert');
    const alertIcon = document.getElementById('toggleBizAlertIcon');
    const modalMessage = document.getElementById('toggleBizModalMessage');
    const modalSubtext = document.getElementById('toggleBizModalSubtext');
    const statusForm = document.getElementById('toggleBusinessStatusForm');
    const btnConfirm = document.getElementById('btnConfirmToggleBiz');

    modalEl.addEventListener('show.bs.modal', function (event) {
        const triggerBtn = event.relatedTarget;
        if (!triggerBtn) return;

        const actionUrl = triggerBtn.getAttribute('data-action-url') || '';
        const bizName = triggerBtn.getAttribute('data-business-name') || '';
        const currentStatus = triggerBtn.getAttribute('data-business-status') || 'active';

        if (statusForm) statusForm.action = actionUrl;

        if (currentStatus === 'active') {
            modalHeader.className = 'modal-header py-3 bg-danger text-white';
            modalTitle.textContent = 'Suspender Negocio';
            modalIcon.className = 'bi bi-pause-circle-fill me-2 fs-5';

            modalAlert.className = 'alert alert-warning bg-warning bg-opacity-10 border border-warning border-opacity-25 d-flex align-items-start mb-0 rounded-3';
            alertIcon.className = 'bi bi-exclamation-triangle-fill text-warning fs-3 me-3 flex-shrink-0';
            modalMessage.innerHTML = `¿Seguro que deseas suspender las operaciones de <strong>${escapeHtml(bizName)}</strong>?`;
            modalSubtext.textContent = 'Los usuarios vinculados a este negocio no podrán ingresar hasta que sea reactivado.';

            btnConfirm.className = 'btn btn-danger rounded-pill px-4 fw-bold';
            btnConfirm.innerHTML = '<i class="bi bi-pause-fill me-1"></i> Suspender';
        } else {
            modalHeader.className = 'modal-header py-3 bg-success text-white';
            modalTitle.textContent = 'Reactivar Negocio';
            modalIcon.className = 'bi bi-play-circle-fill me-2 fs-5';

            modalAlert.className = 'alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 d-flex align-items-start mb-0 rounded-3';
            alertIcon.className = 'bi bi-check-circle-fill text-success fs-3 me-3 flex-shrink-0';
            modalMessage.innerHTML = `¿Seguro que deseas reactivar el negocio <strong>${escapeHtml(bizName)}</strong>?`;
            modalSubtext.textContent = 'El negocio volverá a tener acceso operativo inmediato al sistema.';

            btnConfirm.className = 'btn btn-success rounded-pill px-4 fw-bold';
            btnConfirm.innerHTML = '<i class="bi bi-play-fill me-1"></i> Reactivar';
        }

        btnConfirm.disabled = false;
        btnConfirm.removeAttribute('data-original-html');
    });

    if (statusForm) {
        statusForm.addEventListener('submit', function () {
            if (!btnConfirm) return;
            btnConfirm.setAttribute('data-original-html', btnConfirm.innerHTML);
            btnConfirm.disabled = true;
            btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';
        });
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (!btnConfirm) return;
        btnConfirm.disabled = false;
        const orig = btnConfirm.getAttribute('data-original-html');
        if (orig) {
            btnConfirm.innerHTML = orig;
            btnConfirm.removeAttribute('data-original-html');
        }
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
