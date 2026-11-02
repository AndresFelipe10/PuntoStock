@extends('layouts.app')

@section('title', 'Historial de Comandas')

@section('content')
@php
    $hasPastFilters = request()->has('past_page') 
        || request()->has('search') 
        || request()->has('status') 
        || request()->has('order_type')
        || request()->has('date_from') 
        || request()->has('date_to')
        || request('tab') === 'history'
        || request('tab') === 'past';
    $activeTab = $hasPastFilters ? 'past' : (request('tab') === 'today' ? 'today' : 'today');
    $todayFormatted = \Carbon\Carbon::parse($todayDate)->format('d/m/Y');

    $statusBadges = [
        'open' => 'bg-primary',
        'in_kitchen' => 'bg-warning text-dark',
        'dispatched' => 'bg-info text-dark',
        'delivered' => 'bg-primary',
        'billed' => 'bg-success',
        'closed' => 'bg-success',
        'cancelled' => 'bg-danger',
    ];
    $statusLabels = [
        'open' => 'Abierta / En preparación',
        'in_kitchen' => 'En Cocina',
        'dispatched' => 'En Despacho',
        'delivered' => 'Entregada',
        'billed' => 'Cerrada / Pagada',
        'closed' => 'Cerrada / Pagada',
        'cancelled' => 'Anulada',
    ];
@endphp

<style>
    .orders-nav-tabs-wrapper {
        position: relative;
    }
    .orders-segmented-control {
        background-color: #f1f5f9 !important;
        border: 1px solid rgba(148, 163, 184, 0.25) !important;
        padding: 4px !important;
        border-radius: 9999px !important;
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        gap: 4px !important;
    }
    .orders-segmented-control .nav-item {
        flex: 1 1 50% !important;
        min-width: 0 !important;
    }
    .orders-segmented-control .nav-link {
        width: 100%;
        color: #64748b;
        background-color: transparent;
        border: none;
        border-radius: 9999px !important;
        font-size: 0.925rem;
        font-weight: 600;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-align: center;
        user-select: none;
        white-space: nowrap !important;
        -webkit-tap-highlight-color: transparent;
        padding: 8px 12px !important;
    }
    .orders-segmented-control .nav-link:hover:not(.active) {
        color: #334155;
        background-color: rgba(255, 255, 255, 0.6);
    }
    .orders-segmented-control .nav-link.active {
        color: #0d6efd !important;
        background-color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.1) !important;
    }
    .orders-segmented-control .nav-link.active .badge {
        background-color: #0d6efd !important;
        color: #ffffff !important;
        border-color: #0d6efd !important;
    }
    .orders-segmented-control .nav-link:not(.active) .badge {
        background-color: #e2e8f0 !important;
        color: #64748b !important;
        border-color: #cbd5e1 !important;
    }
    @media (max-width: 575.98px) {
        .orders-segmented-control .nav-link {
            font-size: 0.825rem;
            padding: 8px 6px !important;
            gap: 4px !important;
        }
        .orders-segmented-control .nav-link i {
            font-size: 0.9rem !important;
        }
        .orders-segmented-control .badge {
            font-size: 0.725rem !important;
            padding: 0.2rem 0.4rem !important;
        }
    }
    @media (max-width: 380px) {
        .orders-segmented-control .tab-text-extra {
            display: none !important;
        }
        .orders-segmented-control .nav-link {
            font-size: 0.775rem;
            padding: 8px 4px !important;
        }
    }
</style>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">
                <i class="bi bi-receipt text-primary me-2"></i>Comandas
            </h1>
            <p class="text-muted small mb-0">Listado y seguimiento de pedidos de salón, cocina y despachos.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-primary d-inline-flex align-items-center" style="min-height: 44px;">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Mapa de Mesas
            </a>
            <a href="{{ route('restaurant.orders.create') }}" class="btn btn-primary d-inline-flex align-items-center" style="min-height: 44px;">
                <i class="bi bi-plus-circle me-1"></i> Abrir Nueva Comanda
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show rounded-3 mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Control Segmentado Unificado de Navegación Temporal --}}
    <div class="orders-nav-tabs-wrapper mb-3">
        <ul class="nav nav-pills nav-fill p-1 bg-light border border-secondary border-opacity-25 rounded-4 shadow-sm orders-segmented-control" id="ordersTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'today' ? 'active' : '' }} rounded-pill py-2 px-2 px-sm-3 fw-semibold d-flex align-items-center justify-content-center gap-1 gap-sm-2" 
                        id="today-orders-tab" 
                        data-bs-toggle="pill" 
                        data-bs-target="#today-orders-pane" 
                        type="button" 
                        role="tab" 
                        aria-controls="today-orders-pane" 
                        aria-selected="{{ $activeTab === 'today' ? 'true' : 'false' }}"
                        style="min-height: 44px;">
                    <i class="bi bi-calendar-check fs-6"></i>
                    <span><span class="tab-text-extra">Comandas </span>Hoy<span class="d-none d-md-inline"> ({{ $todayFormatted }})</span></span>
                    <span class="badge {{ $activeTab === 'today' ? 'bg-primary text-white' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-2 py-1 ms-1 orders-today-count">
                        {{ $todayOrdersCount ?? $todayOrders->count() }}
                    </span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $activeTab === 'past' ? 'active' : '' }} rounded-pill py-2 px-2 px-sm-3 fw-semibold text-secondary d-flex align-items-center justify-content-center gap-1 gap-sm-2" 
                        id="history-orders-tab" 
                        data-bs-toggle="pill" 
                        data-bs-target="#past-orders-pane" 
                        type="button" 
                        role="tab" 
                        aria-controls="past-orders-pane" 
                        aria-selected="{{ $activeTab === 'past' ? 'true' : 'false' }}"
                        style="min-height: 44px;">
                    <i class="bi bi-clock-history fs-6"></i>
                    <span>Historial<span class="tab-text-extra"> Anterior</span></span>
                    <span class="badge {{ $activeTab === 'past' ? 'bg-primary text-white' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} rounded-pill px-2 py-1 ms-1 orders-history-count">
                        {{ $historyOrdersCount ?? (isset($historyOrders) ? $historyOrders->count() : $pastOrders->total()) }}
                    </span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="ordersTabContent">
        {{-- PESTAÑA 1: COMANDAS DE HOY --}}
        <div class="tab-pane fade {{ $activeTab === 'today' ? 'show active' : '' }}" id="today-orders-pane" role="tabpanel" aria-labelledby="today-orders-tab" tabindex="0">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    @if($todayOrders->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-event text-muted display-4 mb-3 d-block"></i>
                            <h5 class="fw-bold">No hay comandas registradas hoy</h5>
                            <p class="text-muted">Abre una comanda desde una mesa del salón o registra un domicilio para comenzar la jornada.</p>
                            <div class="d-flex justify-content-center gap-2 mt-3">
                                <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-primary">
                                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ir al Salón
                                </a>
                                <a href="{{ route('restaurant.orders.create-delivery') }}" class="btn btn-primary">
                                    <i class="bi bi-bicycle me-1"></i> Nuevo Domicilio
                                </a>
                            </div>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Comanda</th>
                                        <th>Mesa / Tipo</th>
                                        <th>Cliente</th>
                                        <th>Atendido por</th>
                                        <th>Estado</th>
                                        <th>Total</th>
                                        <th>Hora</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($todayOrders as $order)
                                        <tr>
                                            <td class="ps-4 fw-bold text-primary">
                                                {{ $order->order_number }}
                                            </td>
                                            <td>
                                                @if($order->table)
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="bi bi-aspect-ratio me-1"></i>{{ $order->table->name }}
                                                    </span>
                                                @elseif($order->order_type === 'delivery')
                                                    <span class="badge bg-info-subtle text-info-emphasis border">
                                                        <i class="bi bi-bicycle me-1"></i>Domicilio
                                                    </span>
                                                @elseif($order->order_type === 'takeout')
                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                                                        <i class="bi bi-bag me-1"></i>Para Llevar
                                                    </span>
                                                @else
                                                    <span class="badge bg-light text-dark border">
                                                        Mesa / Salón
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $order->customer_name ?? ($order->customer->name ?? 'Consumidor Final') }}
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $order->user->name ?? 'Usuario' }}</small>
                                            </td>
                                            <td>
                                                {!! $order->status_badge !!}
                                            </td>
                                            <td class="fw-bold">
                                                ${{ number_format($order->total, 2) }}
                                            </td>
                                            <td class="small text-muted">
                                                {{ $order->created_at->format('H:i') }}
                                                <span class="text-secondary small">({{ $order->created_at->diffForHumans() }})</span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="d-inline-flex gap-1 align-items-center">
                                                    @if($order->status === 'open' && $order->items->count() === 0)
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                title="Cancelar comanda vacía"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#cancelEmptyOrderIndexModal"
                                                                data-order-number="{{ $order->order_number }}"
                                                                data-table-name="{{ $order->table ? $order->table->name : 'mesa' }}"
                                                                data-action="{{ route('restaurant.orders.cancel-empty', $order) }}">
                                                            <i class="bi bi-trash3 me-1"></i> Cancelar Vacía
                                                        </button>
                                                    @endif
                                                    <a href="{{ route('restaurant.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye me-1"></i> Gestionar
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- PESTAÑA 2: HISTORIAL DE DÍAS ANTERIORES --}}
        <div class="tab-pane fade {{ $activeTab === 'past' ? 'show active' : '' }}" id="past-orders-pane" role="tabpanel" aria-labelledby="history-orders-tab" tabindex="0">
            {{-- Filtros de Búsqueda --}}
            <div class="card border-0 shadow-sm rounded-4 mb-3 bg-light">
                <div class="card-body p-3">
                    <form method="GET" action="{{ route('restaurant.orders.index') }}" class="row g-2 align-items-end">
                        <input type="hidden" name="tab" value="history">
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold text-secondary mb-1">Buscar comanda, mesa, tipo o cliente</label>
                            <input type="text" 
                                   name="search" 
                                   class="form-control form-control-sm" 
                                   placeholder="Ej: ORD-0012, Mesa 3, Domicilio, Para llevar, María..." 
                                   value="{{ request('search') }}">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold text-secondary mb-1">Tipo</label>
                            <select name="order_type" class="form-select form-select-sm">
                                <option value="">Todos los tipos</option>
                                <option value="dine_in" {{ in_array(request('order_type'), ['dine_in', 'table']) ? 'selected' : '' }}>Salón / Mesa</option>
                                <option value="delivery" {{ request('order_type') === 'delivery' ? 'selected' : '' }}>Domicilio</option>
                                <option value="takeaway" {{ in_array(request('order_type'), ['takeaway', 'takeout']) ? 'selected' : '' }}>Para Llevar</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold text-secondary mb-1">Estado</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Todos los estados</option>
                                <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Abierta</option>
                                <option value="in_kitchen" {{ request('status') === 'in_kitchen' ? 'selected' : '' }}>En Cocina</option>
                                <option value="precount" {{ request('status') === 'precount' ? 'selected' : '' }}>En Cobro</option>
                                <option value="billed" {{ in_array(request('status'), ['billed', 'closed', 'paid']) ? 'selected' : '' }}>Cerrada / Pagada</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Anulada</option>
                            </select>
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold text-secondary mb-1">Fecha Desde</label>
                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                        </div>

                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-semibold text-secondary mb-1">Fecha Hasta</label>
                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                        </div>

                        <div class="col-12 col-md-1 d-flex gap-1">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1" title="Filtrar">
                                <i class="bi bi-filter"></i>
                            </button>
                            @if($hasPastFilters)
                                <a href="{{ route('restaurant.orders.index', ['tab' => 'history']) }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    @if($pastOrders->isEmpty())
                        <div class="text-center py-5">
                            <i class="bi bi-clock-history text-muted display-4 mb-3 d-block"></i>
                            <h5 class="fw-bold">No se encontraron comandas en días anteriores</h5>
                            <p class="text-muted">No hay registros previos con los criterios de filtro especificados.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Comanda</th>
                                        <th>Mesa / Tipo</th>
                                        <th>Cliente</th>
                                        <th>Atendido por</th>
                                        <th>Estado</th>
                                        <th>Total</th>
                                        <th>Fecha y Hora</th>
                                        <th class="text-end pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pastOrders as $order)
                                        <tr>
                                            <td class="ps-4 fw-bold text-primary">
                                                {{ $order->order_number }}
                                            </td>
                                            <td>
                                                @if($order->table)
                                                    <span class="badge bg-light text-dark border">
                                                        <i class="bi bi-aspect-ratio me-1"></i>{{ $order->table->name }}
                                                    </span>
                                                @elseif($order->order_type === 'delivery')
                                                    <span class="badge bg-info-subtle text-info-emphasis border">
                                                        <i class="bi bi-bicycle me-1"></i>Domicilio
                                                    </span>
                                                @elseif($order->order_type === 'takeout')
                                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border">
                                                        <i class="bi bi-bag me-1"></i>Para Llevar
                                                    </span>
                                                @else
                                                    <span class="badge bg-light text-dark border">
                                                        Mesa / Salón
                                                    </span>
                                                @endif
                                            </td>
                                            <td>
                                                {{ $order->customer_name ?? ($order->customer->name ?? 'Consumidor Final') }}
                                            </td>
                                            <td>
                                                <small class="text-muted">{{ $order->user->name ?? 'Usuario' }}</small>
                                            </td>
                                            <td>
                                                {!! $order->status_badge !!}
                                            </td>
                                            <td class="fw-bold">
                                                ${{ number_format($order->total, 2) }}
                                            </td>
                                            <td class="small text-muted">
                                                {{ $order->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="d-inline-flex gap-1 align-items-center">
                                                    @if($order->status === 'open' && $order->items->count() === 0)
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                title="Cancelar comanda vacía"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#cancelEmptyOrderIndexModal"
                                                                data-order-number="{{ $order->order_number }}"
                                                                data-table-name="{{ $order->table ? $order->table->name : 'mesa' }}"
                                                                data-action="{{ route('restaurant.orders.cancel-empty', $order) }}">
                                                            <i class="bi bi-trash3 me-1"></i> Cancelar Vacía
                                                        </button>
                                                    @endif
                                                    <a href="{{ route('restaurant.orders.show', $order) }}" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye me-1"></i> Ver Detalle
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if($pastOrders->hasPages())
                            <div class="p-3 border-top">
                                {{ $pastOrders->links() }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL REUTILIZABLE PARA CANCELAR COMANDA VACÍA EN HISTORIAL --}}
<div class="modal fade" id="cancelEmptyOrderIndexModal" tabindex="-1" aria-labelledby="cancelEmptyOrderIndexModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="cancelEmptyOrderIndexModalLabel">
                    <i class="bi bi-trash3 me-2"></i>Cancelar Comanda Vacía
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning d-flex align-items-center mb-0 rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold text-dark mb-1">
                            ¿Confirmas que deseas cancelar la comanda <strong id="cancelEmptyModalOrderNumber"></strong> y liberar inmediatamente la <strong id="cancelEmptyModalTableName">mesa</strong>?
                        </div>
                        <div class="small text-muted">
                            Esta comanda no contiene platos activos y la mesa quedará disponible para nuevos comensales.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">
                    Conservar Comanda
                </button>
                <form action="" method="POST" id="cancelEmptyOrderIndexForm" class="d-inline m-0" data-no-disable>
                    @csrf
                    @method('POST')
                    <button type="submit" class="btn btn-danger px-4 fw-bold" id="btnConfirmCancelEmptyIndex">
                        <i class="bi bi-trash3 me-1"></i> Sí, Cancelar y Liberar Mesa
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const cancelModal = document.getElementById('cancelEmptyOrderIndexModal');
    const cancelForm = document.getElementById('cancelEmptyOrderIndexForm');
    const orderNumberSpan = document.getElementById('cancelEmptyModalOrderNumber');
    const tableNameSpan = document.getElementById('cancelEmptyModalTableName');
    const btnConfirm = document.getElementById('btnConfirmCancelEmptyIndex');

    if (cancelModal) {
        cancelModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;

            const orderNumber = button.getAttribute('data-order-number') || '';
            const tableName = button.getAttribute('data-table-name') || 'mesa';
            const actionUrl = button.getAttribute('data-action') || '';

            if (orderNumberSpan) orderNumberSpan.textContent = orderNumber;
            if (tableNameSpan) tableNameSpan.textContent = tableName;
            if (cancelForm && actionUrl) cancelForm.setAttribute('action', actionUrl);

            if (btnConfirm) {
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-trash3 me-1"></i> Sí, Cancelar y Liberar Mesa';
            }
        });

        cancelModal.addEventListener('hidden.bs.modal', function () {
            if (btnConfirm) {
                btnConfirm.disabled = false;
                btnConfirm.innerHTML = '<i class="bi bi-trash3 me-1"></i> Sí, Cancelar y Liberar Mesa';
            }
        });
    }

    if (cancelForm && btnConfirm) {
        cancelForm.addEventListener('submit', function () {
            btnConfirm.disabled = true;
            btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';
        });
    }
});
</script>
@endsection
