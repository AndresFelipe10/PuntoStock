@extends('layouts.superadmin')

@section('title', 'Usuarios de ' . $business->name)

@section('content')
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-4 gap-3">
    <div>
        <h1 class="fs-3 fw-bold text-white mb-1">Usuarios de {{ $business->name }}</h1>
        <p class="text-secondary small mb-0">Listado de colaboradores vinculados a este negocio</p>
    </div>
    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 w-100 w-sm-auto">
        <a href="{{ route('superadmin.businesses.users.create', $business) }}" class="btn btn-primary fw-semibold d-inline-flex align-items-center justify-content-center" style="min-height: 42px;">
            <i class="bi bi-person-plus me-1"></i> Agregar Colaborador
        </a>
        <a href="{{ route('superadmin.businesses.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-inline-flex align-items-center justify-content-center" style="min-height: 42px;">
            <i class="bi bi-arrow-left me-1"></i> Volver a Negocios
        </a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4 d-flex align-items-center justify-content-between" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <div>
                <strong class="d-block">Se encontraron los siguientes problemas:</strong>
                <ul class="mb-0 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if (session('temp_password'))
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4 text-white" style="background-color: #198754;">
        <i class="bi bi-key-fill fs-2 me-3"></i>
        <div class="flex-grow-1">
            <h5 class="fw-bold mb-1">¡Colaborador Registrado con Éxito!</h5>
            <p class="mb-0">
                Credenciales para entregar al comercio: 
                <strong>{{ session('created_user_email') }}</strong> &bull; 
                Contraseña temporal: <code class="fs-6 fw-bold bg-dark text-warning px-2 py-1 rounded">{{ session('temp_password') }}</code>
                <span class="badge bg-warning text-dark ms-2">Debe cambiarla al primer inicio de sesión</span>
            </p>
        </div>
    </div>
@endif

<div class="card card-dark border-secondary border-opacity-25 rounded-4 overflow-hidden shadow-sm">
    <div class="table-responsive">
        <table class="table table-dark-custom align-middle mb-0">
            <thead>
                <tr>
                    <th style="min-width: 160px;">Nombre</th>
                    <th style="min-width: 180px;">Correo Electrónico</th>
                    <th style="min-width: 130px;">Rol</th>
                    <th style="min-width: 110px;">Estado</th>
                    <th style="min-width: 150px;">Fecha de Vinculación</th>
                    <th class="text-end" style="min-width: 180px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    @php
                        $role = $roles[$user->pivot->role_id] ?? null;
                        $isActive = (bool) $user->pivot->is_active;
                    @endphp
                    <tr>
                        <td class="fw-medium">
                            <i class="bi bi-person-circle text-primary me-2"></i>{{ $user->name }}
                        </td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($role && $role->slug === 'admin')
                                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25">
                                    <i class="bi bi-shield-check me-1"></i>Administrador
                                </span>
                            @else
                                <span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25">
                                    <i class="bi bi-person me-1"></i>{{ $role->name ?? 'Colaborador' }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($isActive)
                                <span class="badge bg-success bg-opacity-25 text-success">
                                    <i class="bi bi-check-circle me-1"></i>Activo
                                </span>
                            @else
                                <span class="badge bg-danger bg-opacity-25 text-danger">
                                    <i class="bi bi-x-circle me-1"></i>Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="small text-secondary">
                            {{ $user->pivot->created_at ? \Carbon\Carbon::parse($user->pivot->created_at)->format('d/m/Y H:i') : '—' }}
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                {{-- Editar Colaborador --}}
                                <button type="button" 
                                        class="btn btn-outline-primary btn-action-table btn-edit-user" 
                                        title="Editar colaborador"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editUserModal"
                                        data-action-url="{{ route('superadmin.businesses.users.update', [$business, $user]) }}"
                                        data-user-name="{{ $user->name }}"
                                        data-user-email="{{ $user->email }}"
                                        data-user-role="{{ $role && $role->slug === 'admin' ? 'admin' : 'employee' }}">
                                    <i class="bi bi-pencil"></i>
                                </button>

                                {{-- Vaciar Operaciones --}}
                                <button type="button" 
                                        class="btn btn-outline-warning btn-action-table btn-wipe-user" 
                                        title="Vaciar operaciones del colaborador"
                                        data-bs-toggle="modal"
                                        data-bs-target="#wipeUserModal"
                                        data-action-url="{{ route('superadmin.businesses.users.wipe', [$business, $user]) }}"
                                        data-user-name="{{ $user->name }}">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>

                                {{-- Activar / Desactivar --}}
                                @if ($isActive)
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-action-table btn-toggle-superadmin-user" 
                                            title="Desactivar colaborador"
                                            data-bs-toggle="modal"
                                            data-bs-target="#toggleSuperadminUserModal"
                                            data-action-url="{{ route('superadmin.businesses.toggleUserStatus', [$business, $user]) }}"
                                            data-user-name="{{ $user->name }}"
                                            data-target-action="deactivate">
                                        <i class="bi bi-person-x"></i>
                                    </button>
                                @else
                                    <button type="button" 
                                            class="btn btn-outline-success btn-action-table btn-toggle-superadmin-user" 
                                            title="Activar colaborador"
                                            data-bs-toggle="modal"
                                            data-bs-target="#toggleSuperadminUserModal"
                                            data-action-url="{{ route('superadmin.businesses.toggleUserStatus', [$business, $user]) }}"
                                            data-user-name="{{ $user->name }}"
                                            data-target-action="activate">
                                        <i class="bi bi-person-check"></i>
                                    </button>
                                @endif

                                {{-- Eliminar Colaborador --}}
                                <button type="button" 
                                        class="btn btn-outline-danger btn-action-table btn-delete-user" 
                                        title="Eliminar colaborador"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteUserModal"
                                        data-action-url="{{ route('superadmin.businesses.users.destroy', [$business, $user]) }}"
                                        data-user-name="{{ $user->name }}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary">
                            No hay usuarios vinculados a este negocio.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modal Superadmin: Editar Colaborador --}}
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3 bg-primary text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="editUserModalLabel">
                    <i class="bi bi-person-gear me-2 fs-5"></i> Editar Colaborador
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form action="" method="POST" id="editUserForm">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="editUserName" class="form-label text-white small fw-bold">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="editUserName" name="name" required maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label for="editUserEmail" class="form-label text-white small fw-bold">Correo Electrónico <span class="text-danger">*</span></label>
                        <input type="email" class="form-control bg-dark text-white border-secondary" id="editUserEmail" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="editUserRole" class="form-label text-white small fw-bold">Rol en el Negocio <span class="text-danger">*</span></label>
                        <select class="form-select bg-dark text-white border-secondary" id="editUserRole" name="role" required>
                            <option value="admin">Administrador (Acceso total)</option>
                            <option value="employee">Empleado / Colaborador (Operativo)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="editUserPassword" class="form-label text-white small fw-bold">Nueva Contraseña Temporal (Opcional)</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" id="editUserPassword" name="password" placeholder="Dejar en blanco para conservar la actual" minlength="8">
                        <div class="form-text text-secondary small">
                            Si ingresas una nueva clave, se forzará al colaborador a cambiarla en su próximo ingreso y se le enviará un correo con las nuevas credenciales.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Superadmin: Vaciar Operaciones de Colaborador --}}
<div class="modal fade" id="wipeUserModal" tabindex="-1" aria-labelledby="wipeUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3 bg-warning text-dark">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="wipeUserModalLabel">
                    <i class="bi bi-arrow-counterclockwise me-2 fs-5"></i> Vaciar Operaciones de Colaborador
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning bg-warning bg-opacity-10 border border-warning border-opacity-25 d-flex align-items-start mb-0 rounded-3">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-3 flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold text-white mb-1">
                            ¿Seguro que deseas vaciar todas las operaciones de <strong id="wipeUserName" class="text-warning">-</strong> en este negocio?
                        </div>
                        <div class="small text-secondary">
                            Esta acción eliminará de forma irreversible todas las ventas, comandas, movimientos de inventario y gastos generados por este colaborador en este negocio. La cuenta del colaborador y su acceso permanecerán activos.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="wipeUserForm" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-warning rounded-pill px-4 fw-bold text-dark">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Confirmar Vaciado
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal Superadmin: Eliminar Colaborador --}}
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3 bg-danger text-white">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="deleteUserModalLabel">
                    <i class="bi bi-trash-fill me-2 fs-5"></i> Eliminar Colaborador
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-danger bg-danger bg-opacity-10 border border-danger border-opacity-25 d-flex align-items-start mb-0 rounded-3">
                    <i class="bi bi-exclamation-octagon-fill text-danger fs-3 me-3 flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold text-white mb-1">
                            ¿Seguro que deseas eliminar al colaborador <strong id="deleteUserName" class="text-danger">-</strong> de este negocio?
                        </div>
                        <div class="small text-secondary">
                            Se purgarán sus operaciones en este negocio y se desvinculará del comercio. Si el usuario no pertenece a ningún otro negocio, su cuenta y correo quedarán liberados del sistema.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="deleteUserForm" class="d-inline m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="bi bi-trash me-1"></i> Eliminar Definitivamente
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal Superadmin de Cambio de Estado de Colaborador --}}
<div class="modal fade" id="toggleSuperadminUserModal" tabindex="-1" aria-labelledby="toggleSuperadminUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-dark border-secondary border-opacity-50 shadow rounded-4 overflow-hidden text-light">
            <div class="modal-header py-3" id="toggleSaModalHeader">
                <h5 class="modal-title fw-bold d-flex align-items-center text-white" id="toggleSuperadminUserModalLabel">
                    <i class="bi me-2 fs-5" id="toggleSaModalIcon"></i>
                    <span id="toggleSaModalTitle">Cambiar Estado</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert d-flex align-items-start mb-0 rounded-3" id="toggleSaModalAlert" role="alert">
                    <i class="bi fs-3 me-3 flex-shrink-0" id="toggleSaAlertIcon"></i>
                    <div>
                        <div class="fw-semibold text-white mb-1" id="toggleSaModalMessage"></div>
                        <div class="small text-secondary" id="toggleSaModalSubtext"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-dark border-top border-secondary border-opacity-25 px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form action="" method="POST" id="toggleSuperadminUserForm" class="d-inline m-0" data-no-disable>
                    @csrf
                    <button type="submit" class="btn rounded-pill px-4 fw-bold" id="btnConfirmToggleSaUser">
                        Confirmar
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
    // 1. Modal Editar Colaborador
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const actionUrl = btn.getAttribute('data-action-url') || '';
            const userName = btn.getAttribute('data-user-name') || '';
            const userEmail = btn.getAttribute('data-user-email') || '';
            const userRole = btn.getAttribute('data-user-role') || 'employee';

            const form = document.getElementById('editUserForm');
            const nameInput = document.getElementById('editUserName');
            const emailInput = document.getElementById('editUserEmail');
            const roleSelect = document.getElementById('editUserRole');
            const pwdInput = document.getElementById('editUserPassword');

            if (form) form.action = actionUrl;
            if (nameInput) nameInput.value = userName;
            if (emailInput) emailInput.value = userEmail;
            if (roleSelect) roleSelect.value = userRole;
            if (pwdInput) pwdInput.value = '';
        });
    }

    // 2. Modal Vaciar Operaciones Colaborador
    const wipeModal = document.getElementById('wipeUserModal');
    if (wipeModal) {
        wipeModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const actionUrl = btn.getAttribute('data-action-url') || '';
            const userName = btn.getAttribute('data-user-name') || '';

            const form = document.getElementById('wipeUserForm');
            const nameEl = document.getElementById('wipeUserName');
            if (form) form.action = actionUrl;
            if (nameEl) nameEl.textContent = userName;
        });
    }

    // 3. Modal Eliminar Colaborador
    const deleteModal = document.getElementById('deleteUserModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const actionUrl = btn.getAttribute('data-action-url') || '';
            const userName = btn.getAttribute('data-user-name') || '';

            const form = document.getElementById('deleteUserForm');
            const nameEl = document.getElementById('deleteUserName');
            if (form) form.action = actionUrl;
            if (nameEl) nameEl.textContent = userName;
        });
    }

    // 4. Modal Cambiar Estado (Activar/Desactivar)
    const modalEl = document.getElementById('toggleSuperadminUserModal');
    if (modalEl) {
        const modalHeader = document.getElementById('toggleSaModalHeader');
        const modalTitle = document.getElementById('toggleSaModalTitle');
        const modalIcon = document.getElementById('toggleSaModalIcon');
        const modalAlert = document.getElementById('toggleSaModalAlert');
        const alertIcon = document.getElementById('toggleSaAlertIcon');
        const modalMessage = document.getElementById('toggleSaModalMessage');
        const modalSubtext = document.getElementById('toggleSaModalSubtext');
        const statusForm = document.getElementById('toggleSuperadminUserForm');
        const btnConfirm = document.getElementById('btnConfirmToggleSaUser');

        modalEl.addEventListener('show.bs.modal', function (event) {
            const triggerBtn = event.relatedTarget;
            if (!triggerBtn) return;

            const actionUrl = triggerBtn.getAttribute('data-action-url') || '';
            const userName = triggerBtn.getAttribute('data-user-name') || '';
            const targetAction = triggerBtn.getAttribute('data-target-action') || 'deactivate';

            if (statusForm) statusForm.action = actionUrl;

            if (targetAction === 'deactivate') {
                modalHeader.className = 'modal-header py-3 bg-danger text-white';
                modalTitle.textContent = 'Desactivar Usuario';
                modalIcon.className = 'bi bi-person-x-fill me-2 fs-5';

                modalAlert.className = 'alert alert-warning bg-warning bg-opacity-10 border border-warning border-opacity-25 d-flex align-items-start mb-0 rounded-3';
                alertIcon.className = 'bi bi-exclamation-triangle-fill text-warning fs-3 me-3 flex-shrink-0';
                modalMessage.innerHTML = `¿Seguro que deseas desactivar a <strong>${escapeHtml(userName)}</strong> en este negocio?`;
                modalSubtext.textContent = 'El usuario perderá el acceso a las funciones del emprendimiento.';

                btnConfirm.className = 'btn btn-danger rounded-pill px-4 fw-bold';
                btnConfirm.innerHTML = '<i class="bi bi-person-x me-1"></i> Desactivar';
            } else {
                modalHeader.className = 'modal-header py-3 bg-success text-white';
                modalTitle.textContent = 'Activar Usuario';
                modalIcon.className = 'bi bi-person-check-fill me-2 fs-5';

                modalAlert.className = 'alert alert-success bg-success bg-opacity-10 border border-success border-opacity-25 d-flex align-items-start mb-0 rounded-3';
                alertIcon.className = 'bi bi-check-circle-fill text-success fs-3 me-3 flex-shrink-0';
                modalMessage.innerHTML = `¿Seguro que deseas activar a <strong>${escapeHtml(userName)}</strong> en este negocio?`;
                modalSubtext.textContent = 'El usuario podrá volver a ingresar y operar.';

                btnConfirm.className = 'btn btn-success rounded-pill px-4 fw-bold';
                btnConfirm.innerHTML = '<i class="bi bi-person-check me-1"></i> Activar';
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
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
