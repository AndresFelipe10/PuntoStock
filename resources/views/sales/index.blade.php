@extends('layouts.app')

@section('title', 'Ventas')

@section('content')
<div class="d-flex justify-content-between align-items-start align-items-md-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Ventas</h3>
        <p class="text-muted mb-0">Historial de ventas y comprobantes</p>
    </div>
    {{-- Acciones en Escritorio (>= 768px): Mantener 100% intacto --}}
    <div class="d-none d-md-flex gap-2 flex-wrap">
        <a href="{{ route('reports.cash-register') }}" class="btn btn-outline-primary rounded-pill px-3 fw-semibold shadow-sm">
            <i class="bi bi-cash-coin me-1"></i> Cuadre de Caja
        </a>
        @can('create', App\Models\Sale::class)
            <a href="{{ route('sales.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                <i class="bi bi-cart-plus me-1"></i> Nueva Venta
            </a>
        @endcan
    </div>
    {{-- Acciones en Móvil (< 768px): Cuadrícula ergonómica táctil (min 44px) --}}
    <div class="d-md-none w-100">
        <div class="row g-2">
            <div class="col-6">
                <a href="{{ route('reports.cash-register') }}" class="btn btn-outline-primary rounded-pill w-100 fw-semibold shadow-sm d-flex align-items-center justify-content-center" style="min-height: 44px;">
                    <i class="bi bi-cash-coin me-1"></i> Cuadre de Caja
                </a>
            </div>
            <div class="col-6">
                @can('create', App\Models\Sale::class)
                    <a href="{{ route('sales.create') }}" class="btn btn-primary rounded-pill w-100 fw-semibold shadow-sm d-flex align-items-center justify-content-center" style="min-height: 44px;">
                        <i class="bi bi-cart-plus me-1"></i> + Nueva Venta
                    </a>
                @endcan
            </div>
        </div>
    </div>
</div>

{{-- Filtros Escritorio (>= 768px): Mantener 100% intacto --}}
<div class="card card-custom p-3 mb-4 d-none d-md-block">
    <form method="GET" action="{{ route('sales.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="search" class="form-label small fw-semibold text-muted">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search" name="search" class="form-control border-start-0"
                           placeholder="N° factura o cliente" value="{{ $search ?? '' }}">
                </div>
            </div>
            <div class="col-md-2">
                <label for="from" class="form-label small fw-semibold text-muted">Desde</label>
                <input type="date" id="from" name="from" class="form-control" value="{{ $from ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="to" class="form-label small fw-semibold text-muted">Hasta</label>
                <input type="date" id="to" name="to" class="form-control" value="{{ $to ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="payment_method" class="form-label small fw-semibold text-muted">Método</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    <option value="">Todos</option>
                    <option value="cash" {{ ($selectedPaymentMethod ?? '') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                    <option value="transfer" {{ ($selectedPaymentMethod ?? '') === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                    <option value="card" {{ ($selectedPaymentMethod ?? '') === 'card' ? 'selected' : '' }}>Tarjeta</option>
                    <option value="other" {{ ($selectedPaymentMethod ?? '') === 'other' ? 'selected' : '' }}>Otro</option>
                    <option value="mixed" {{ ($selectedPaymentMethod ?? '') === 'mixed' ? 'selected' : '' }}>Mixto</option>
                </select>
            </div>
            <div class="col-md-1">
                <label for="status" class="form-label small fw-semibold text-muted">Estado</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="completed" {{ ($selectedStatus ?? '') === 'completed' ? 'selected' : '' }}>Completada</option>
                    <option value="cancelled" {{ ($selectedStatus ?? '') === 'cancelled' ? 'selected' : '' }}>Anulada</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary rounded-pill px-3 fw-semibold flex-fill">
                    <i class="bi bi-funnel me-1"></i> Filtrar
                </button>
                <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </div>
    </form>
</div>

{{-- Filtros Móvil (< 768px): Barra compacta + Colapsable progresivo --}}
@php
    $activeMobileFiltersCount = collect([$from, $to, $selectedPaymentMethod, $selectedStatus])->filter()->count();
@endphp
<form method="GET" action="{{ route('sales.index') }}" class="d-md-none mb-3">
    <div class="input-group mb-2 shadow-sm rounded-pill overflow-hidden border">
        <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
        <input type="text" name="search" class="form-control border-0 ps-1" placeholder="Buscar factura o cliente..." value="{{ $search ?? '' }}">
        @if(!empty($search) || $activeMobileFiltersCount > 0)
            <a href="{{ route('sales.index') }}" class="btn btn-white text-muted border-0 d-flex align-items-center px-2" title="Limpiar búsqueda">
                <i class="bi bi-x-lg"></i>
            </a>
        @endif
        <button type="submit" class="btn btn-primary px-3 fw-semibold">
            <i class="bi bi-arrow-right"></i>
        </button>
    </div>
    
    <button class="btn btn-outline-secondary btn-sm w-100 rounded-pill py-2 d-flex align-items-center justify-content-center gap-1 shadow-sm" 
            type="button" 
            data-bs-toggle="collapse" 
            data-bs-target="#mobileFiltersCollapse" 
            aria-expanded="{{ $activeMobileFiltersCount > 0 ? 'true' : 'false' }}">
        <i class="bi bi-funnel me-1"></i>
        <span>Filtros avanzados {{ $activeMobileFiltersCount > 0 ? "($activeMobileFiltersCount activos)" : '' }}</span>
        <i class="bi bi-chevron-down ms-1 small"></i>
    </button>

    <div class="collapse mt-2 {{ $activeMobileFiltersCount > 0 ? 'show' : '' }}" id="mobileFiltersCollapse">
        <div class="card card-custom p-3 bg-white border">
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Desde</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $from ?? '' }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Hasta</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $to ?? '' }}">
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Método</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="cash" {{ ($selectedPaymentMethod ?? '') === 'cash' ? 'selected' : '' }}>Efectivo</option>
                        <option value="transfer" {{ ($selectedPaymentMethod ?? '') === 'transfer' ? 'selected' : '' }}>Transferencia</option>
                        <option value="card" {{ ($selectedPaymentMethod ?? '') === 'card' ? 'selected' : '' }}>Tarjeta</option>
                        <option value="other" {{ ($selectedPaymentMethod ?? '') === 'other' ? 'selected' : '' }}>Otro</option>
                        <option value="mixed" {{ ($selectedPaymentMethod ?? '') === 'mixed' ? 'selected' : '' }}>Mixto</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="completed" {{ ($selectedStatus ?? '') === 'completed' ? 'selected' : '' }}>Completada</option>
                        <option value="cancelled" {{ ($selectedStatus ?? '') === 'cancelled' ? 'selected' : '' }}>Anulada</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 mt-2 pt-2 border-top">
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill flex-fill py-2 fw-semibold">
                        <i class="bi bi-funnel me-1"></i> Aplicar Filtros
                    </button>
                    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 py-2">
                        Limpiar
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Tabla en Escritorio (>= 768px): Mantener 100% intacta --}}
<div class="card card-custom d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th class="ps-4">Factura</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th class="text-center">Método</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td class="ps-4">
                            <span class="font-monospace fw-semibold text-primary">{{ $sale->invoice_number }}</span>
                        </td>
                        <td>{{ $sale->sale_date->format('d/m/Y H:i') }}</td>
                        <td>{{ $sale->display_customer_name }}</td>
                        <td>{{ $sale->user->name }}</td>
                        <td class="text-center">
                            @switch($sale->payment_method)
                                @case('cash')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                        <i class="bi bi-cash me-1"></i> Efectivo
                                    </span>
                                    @break
                                @case('transfer')
                                    <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                        <i class="bi bi-bank me-1"></i> Transferencia
                                    </span>
                                    @break
                                @case('card')
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                        <i class="bi bi-credit-card me-1"></i> Tarjeta
                                    </span>
                                    @break
                                @case('mixed')
                                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill">
                                        <i class="bi bi-wallet2 me-1"></i> Mixto
                                    </span>
                                    @break
                                @default
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                        <i class="bi bi-three-dots me-1"></i> Otro
                                    </span>
                            @endswitch
                        </td>
                        <td class="text-end fw-bold">${{ number_format($sale->total, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if ($sale->status === 'completed')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-check-circle-fill me-1"></i> Completada
                                </span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                                    <i class="bi bi-x-circle-fill me-1"></i> Anulada
                                </span>
                            @endif
                        </td>
                        <td class="text-center pe-4">
                            <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                <i class="bi bi-receipt me-1"></i> Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-cart-x fs-1 d-block mb-2 opacity-50"></i>
                            No se encontraron ventas registradas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Lista de Tarjetas Táctiles en Móvil (< 768px): Patrón Table-to-Card --}}
<div class="d-md-none d-flex flex-column gap-3">
    @forelse ($sales as $sale)
        <div class="card card-custom p-3 bg-white shadow-sm border">
            {{-- Cabecera: Consecutivo + Estado --}}
            <div class="d-flex justify-content-between align-items-center pb-2 mb-2 border-bottom">
                <span class="font-monospace fw-bold text-primary">{{ $sale->invoice_number }}</span>
                @if ($sale->status === 'completed')
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                        <i class="bi bi-check-circle-fill me-1"></i> Completada
                    </span>
                @else
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1">
                        <i class="bi bi-x-circle-fill me-1"></i> Anulada
                    </span>
                @endif
            </div>

            {{-- Cuerpo: Cliente, Fecha y Vendedor --}}
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-start mb-1">
                    <span class="text-muted small">Cliente:</span>
                    <span class="fw-semibold text-dark text-end">{{ $sale->display_customer_name }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="text-muted small">Fecha / Hora:</span>
                    <span class="text-secondary small">{{ $sale->sale_date->format('d/m/Y H:i') }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Vendedor:</span>
                    <span class="text-secondary small">{{ $sale->user->name }}</span>
                </div>
            </div>

            {{-- Pie: Método de pago, Total y Botón Táctil --}}
            <div class="pt-2 border-top">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        @switch($sale->payment_method)
                            @case('cash')
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                    <i class="bi bi-cash me-1"></i> Efectivo
                                </span>
                                @break
                            @case('transfer')
                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                    <i class="bi bi-bank me-1"></i> Transferencia
                                </span>
                                @break
                            @case('card')
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                    <i class="bi bi-credit-card me-1"></i> Tarjeta
                                </span>
                                @break
                            @case('mixed')
                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill">
                                    <i class="bi bi-wallet2 me-1"></i> Mixto
                                </span>
                                @break
                            @default
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                    <i class="bi bi-three-dots me-1"></i> Otro
                                </span>
                        @endswitch
                    </div>
                    <div class="text-end">
                        <span class="text-muted small d-block" style="font-size: 0.725rem;">Total Venta</span>
                        <span class="fw-bold text-dark fs-5">${{ number_format($sale->total, 0, ',', '.') }}</span>
                    </div>
                </div>

                <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-primary rounded-pill w-100 fw-semibold d-flex align-items-center justify-content-center gap-1 shadow-sm" style="min-height: 42px;">
                    <i class="bi bi-receipt me-1"></i> Ver Comprobante
                </a>
            </div>
        </div>
    @empty
        <div class="card card-custom p-4 text-center text-muted bg-white">
            <i class="bi bi-cart-x fs-1 d-block mb-2 opacity-50"></i>
            No se encontraron ventas registradas.
        </div>
    @endforelse
</div>

@if ($sales->hasPages())
    <div class="d-flex justify-content-center mt-4">
        {{ $sales->links() }}
    </div>
@endif
@endsection
