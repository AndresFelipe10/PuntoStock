@extends('layouts.app')

@section('title', $customer->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('customers.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i> Volver al Directorio
        </a>
        <div>
            <h3 class="fw-bold mb-0">{{ $customer->name }}</h3>
            @if ($customer->document ?? $customer->identification_number)
                <span class="badge bg-light text-dark font-monospace border">ID: {{ $customer->document ?? $customer->identification_number }}</span>
            @endif
        </div>
    </div>

    @can('update', $customer)
        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
            <i class="bi bi-pencil-square me-1"></i> Editar Cliente
        </a>
    @endcan
</div>

<div class="row g-4">
    <!-- Información de Contacto -->
    <div class="col-lg-5">
        <div class="card card-custom p-4 bg-white mb-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Información del Cliente</h5>

            <ul class="list-unstyled mb-0">
                <li class="mb-3">
                    <span class="text-muted small d-block">Identificación Tributaria / Cédula</span>
                    <span class="fw-semibold">{{ $customer->document ?? $customer->identification_number ?? 'No especificada' }}</span>
                </li>
                <li class="mb-3">
                    <span class="text-muted small d-block">Teléfono / WhatsApp</span>
                    <span class="fw-semibold">{{ $customer->phone ?? 'No registrado' }}</span>
                </li>
                <li class="mb-3">
                    <span class="text-muted small d-block">Correo Electrónico</span>
                    <span class="fw-semibold">{{ $customer->email ?? 'No registrado' }}</span>
                </li>
                <li class="mb-3">
                    <span class="text-muted small d-block">Dirección</span>
                    <span class="fw-semibold">{{ $customer->address ?? 'No registrada' }}</span>
                </li>
                <li>
                    <span class="text-muted small d-block">Estado</span>
                    @if ($customer->is_active)
                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-check-circle-fill me-1"></i> Activo
                        </span>
                    @else
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                            <i class="bi bi-dash-circle-fill me-1"></i> Inactivo
                        </span>
                    @endif
                </li>
            </ul>
        </div>
    </div>

    <!-- Historial de Compras -->
    <div class="col-lg-7">
        <div class="card card-custom p-4 bg-white">
            <h5 class="fw-bold mb-3 border-bottom pb-2">Historial de Compras Recientes</h5>

            @if ($customer->sales && $customer->sales->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr class="small text-uppercase text-muted">
                                <th>Factura #</th>
                                <th>Fecha</th>
                                <th>Método</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($customer->sales as $sale)
                                <tr>
                                    <td class="fw-semibold font-monospace">{{ $sale->invoice_number }}</td>
                                    <td class="small text-secondary">{{ $sale->sale_date->format('d/m/Y') }}</td>
                                    <td>
                                        @switch(strtolower($sale->payment_method ?? ''))
                                            @case('cash')
                                            @case('efectivo')
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                                                    <i class="bi bi-cash me-1"></i> Efectivo
                                                </span>
                                                @break
                                            @case('transfer')
                                            @case('transferencia')
                                            @case('nequi')
                                            @case('daviplata')
                                                <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                                    <i class="bi bi-bank me-1"></i> {{ $sale->payment_method_label }}
                                                </span>
                                                @break
                                            @case('card')
                                            @case('tarjeta')
                                            @case('debit')
                                            @case('credit_card')
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                                    <i class="bi bi-credit-card me-1"></i> Tarjeta
                                                </span>
                                                @break
                                            @case('mixed')
                                            @case('split')
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle rounded-pill">
                                                    <i class="bi bi-wallet2 me-1"></i> Mixto
                                                </span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill">
                                                    <i class="bi bi-wallet me-1"></i> {{ $sale->payment_method_label }}
                                                </span>
                                        @endswitch
                                    </td>
                                    <td class="text-end fw-bold text-success">${{ number_format($sale->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-receipt display-5 d-block mb-2 text-secondary opacity-50"></i>
                    Aún no hay compras registradas para este cliente.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
