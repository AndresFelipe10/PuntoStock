@extends('layouts.app')

@section('title', 'Cuadre de Caja')

@section('content')
<style>
    @media print {
        @page {
            size: letter portrait;
            margin: 10mm 12mm;
        }

        .no-print, .navbar, .sidebar-desktop, .offcanvas, .btn, form, footer, .d-print-none {
            display: none !important;
        }

        .main-content {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
        }

        body {
            background-color: #fff !important;
            color: #000 !important;
            font-size: 8.5pt !important;
            line-height: 1.25 !important;
        }

        .print-header {
            display: block !important;
            padding-top: 4mm !important;
            margin-top: 0 !important;
            margin-bottom: 12px !important;
            border-bottom: 2px solid #333 !important;
            padding-bottom: 8px !important;
        }

        /* Desactivar sticky y fixed para evitar superposiciones */
        .sticky-top,
        .position-sticky,
        .position-fixed {
            position: static !important;
            transform: none !important;
            float: none !important;
        }

        .card {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 0.65rem !important;
            box-shadow: none !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 4px !important;
            background: #fff !important;
        }

        .card-custom.p-4 {
            padding: 8px 10px !important;
        }

        .card-custom h5 {
            font-size: 9.5pt !important;
            margin-bottom: 6px !important;
        }

        /* 1. Grilla horizontal compacta para los 4 KPIs principales */
        .print-kpi-grid {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 8px !important;
            margin-bottom: 10px !important;
        }

        .print-kpi-grid > [class*="col-"] {
            width: 100% !important;
            max-width: 100% !important;
            flex: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .print-kpi-grid .card {
            padding: 8px 10px !important;
            margin-bottom: 0 !important;
            border-left-width: 3px !important;
        }

        .print-kpi-grid h3 {
            font-size: 13pt !important;
            margin-top: 2px !important;
            margin-bottom: 2px !important;
        }

        .print-kpi-grid span {
            font-size: 7.5pt !important;
            line-height: 1.1 !important;
        }

        .print-kpi-grid small {
            font-size: 7pt !important;
        }

        /* 2. Grilla para Canales de Venta (Restaurante) */
        .print-restaurant-row > [class*="col-"] {
            width: 100% !important;
            max-width: 100% !important;
            flex: none !important;
            padding: 0 !important;
            margin-bottom: 8px !important;
        }

        .print-channel-grid {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 8px !important;
        }

        .print-channel-grid > [class*="col-"] {
            width: 100% !important;
            max-width: 100% !important;
            flex: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .print-channel-grid .p-3 {
            padding: 6px 8px !important;
        }

        .print-channel-grid h4 {
            font-size: 11pt !important;
        }

        .print-channel-grid span {
            font-size: 8pt !important;
        }

        /* Grilla de Fletes y Propinas en 2 columnas */
        .print-fletes-propinas-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 8px !important;
            margin-top: 8px !important;
        }

        .print-fletes-propinas-grid > div {
            margin-top: 0 !important;
            padding: 6px 8px !important;
        }

        .print-fletes-propinas-grid .h4 {
            font-size: 11pt !important;
        }

        /* Conciliación de Arqueo compacta */
        .print-conciliacion-list {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 10px !important;
            margin-bottom: 4px !important;
        }

        .print-conciliacion-list .list-group-item {
            padding: 4px 0 !important;
            border: none !important;
        }

        /* 3. Grilla de Arqueo Operativo (Caja Menor, Caja General, Balance Consolidado) */
        .print-arqueo-grid {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 8px !important;
            margin-bottom: 10px !important;
        }

        .print-arqueo-grid > [class*="col-"] {
            width: 100% !important;
            max-width: 100% !important;
            flex: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .print-arqueo-grid .card {
            padding: 8px 10px !important;
            margin-bottom: 0 !important;
        }

        .print-arqueo-grid p {
            display: none !important;
        }

        .print-arqueo-grid .list-group-item {
            padding: 2px 0 !important;
            font-size: 7.5pt !important;
        }

        .print-arqueo-grid .bg-light {
            padding: 4px 6px !important;
            margin-top: 4px !important;
        }

        .print-arqueo-grid .fs-5 {
            font-size: 10pt !important;
        }

        /* 4. Tablas Contables Nítidas sin Letras Verticales */
        .table-responsive {
            overflow: visible !important;
            overflow-x: visible !important;
            display: block !important;
            width: 100% !important;
        }

        .table-responsive::-webkit-scrollbar {
            display: none !important;
        }

        .table {
            width: 100% !important;
            max-width: 100% !important;
            border-collapse: collapse !important;
            font-size: 8.5pt !important;
            line-height: 1.25 !important;
            table-layout: auto !important;
            margin-bottom: 0 !important;
        }

        .table th,
        .table td {
            word-break: normal !important;
            overflow-wrap: normal !important;
            padding: 5px 6px !important;
            vertical-align: middle !important;
        }

        .table th {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            font-size: 7.5pt !important;
            font-weight: 700 !important;
        }

        /* Evitar que el tfoot se repita en cada página */
        .table tfoot {
            display: table-row-group !important;
        }

        .table tr {
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        /* Columnas con nowrap obligatorio para que números y fechas jamás se partan */
        .print-nowrap,
        .table .print-nowrap {
            white-space: nowrap !important;
        }

        .print-notes {
            font-size: 7.5pt !important;
            line-height: 1.15 !important;
            max-width: 140px !important;
        }

        /* Anchos de columnas para Tabla 1: Medios de Pago */
        .table-payment-methods th:nth-child(1),
        .table-payment-methods td:nth-child(1) {
            width: 22% !important;
        }
        .table-payment-methods th:nth-child(2),
        .table-payment-methods td:nth-child(2) {
            width: 12% !important;
            text-align: center !important;
            white-space: nowrap !important;
        }
        .table-payment-methods th:nth-child(3),
        .table-payment-methods td:nth-child(3) {
            width: 10% !important;
            text-align: right !important;
            white-space: nowrap !important;
        }
        .table-payment-methods th:nth-child(4),
        .table-payment-methods td:nth-child(4) {
            width: 18% !important;
            text-align: right !important;
            white-space: nowrap !important;
        }
        .table-payment-methods th:nth-child(5),
        .table-payment-methods td:nth-child(5) {
            width: 38% !important;
            white-space: normal !important;
        }

        /* Anchos y nowrap para Tabla 2: Gastos Operativos */
        .table-expenses th:nth-child(1),
        .table-expenses td:nth-child(1),
        .table-expenses th:nth-child(2),
        .table-expenses td:nth-child(2),
        .table-expenses th:nth-child(3),
        .table-expenses td:nth-child(3),
        .table-expenses th:nth-child(5),
        .table-expenses td:nth-child(5),
        .table-expenses th:nth-child(7),
        .table-expenses td:nth-child(7) {
            white-space: nowrap !important;
        }

        /* Anchos y nowrap para Tabla 3: Detalle de Ventas */
        .table-sales th:nth-child(1),
        .table-sales td:nth-child(1),
        .table-sales th:nth-child(2),
        .table-sales td:nth-child(2),
        .table-sales th:nth-child(3),
        .table-sales td:nth-child(3),
        .table-sales th:nth-child(7),
        .table-sales td:nth-child(7) {
            white-space: nowrap !important;
        }

        .badge {
            border: 1px solid #cbd5e1 !important;
            font-weight: 600 !important;
        }
    }
    .print-header {
        display: none;
    }
</style>

{{-- Encabezado para impresión --}}
<div class="print-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold mb-0">{{ $currentBusiness->name }}</h2>
            <p class="mb-0 text-muted">NIT: {{ $currentBusiness->nit ?? 'No registrado' }} | Cali, Colombia</p>
        </div>
        <div class="text-end">
            <h4 class="fw-bold mb-0">CUADRE DE CAJA {{ $period_type === 'monthly' ? 'MENSUAL' : 'DIARIO' }}</h4>
            <p class="mb-0 text-muted">
                Período: {{ $period_type === 'monthly' ? $selected_month : \Carbon\Carbon::parse($selected_date)->format('d/m/Y') }}
            </p>
            <small class="text-muted">Impreso: {{ now()->format('d/m/Y H:i') }}</small>
        </div>
    </div>
</div>

{{-- Barra superior de acciones --}}
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3 no-print">
    <div>
        <h3 class="fw-bold mb-1">
            <i class="bi bi-cash-coin text-primary me-2"></i>Cuadre de Caja
        </h3>
        <p class="text-muted small mb-0">
            Control discriminado de ingresos por método de pago para <strong>{{ $currentBusiness->name }}</strong>.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
            <i class="bi bi-printer me-1"></i> Imprimir Cuadre
        </button>
        <a href="{{ route('reports.cash-register', array_merge(request()->all(), ['export' => 'csv'])) }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Exportar a CSV
        </a>
        <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
            <i class="bi bi-receipt me-1"></i> Historial Ventas
        </a>
        <a href="{{ route('sales.create') }}" class="btn btn-primary fw-semibold rounded-pill px-3 shadow-sm">
            <i class="bi bi-cart-plus me-1"></i> + Nueva Venta
        </a>
    </div>
</div>

{{-- Pestañas de Reportes (Solo se muestran si el usuario es Admin para coherencia con el Hub) --}}
@if(auth()->check() && auth()->user()->isCurrentAdmin())
    <ul class="nav nav-pills mb-4 gap-2 no-print">
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.index') }}">
                <i class="bi bi-grid me-1"></i> Centro
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 active shadow-sm" href="{{ route('reports.cash-register') }}">
                <i class="bi bi-cash-coin me-1"></i> Cuadre de Caja
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.sales') }}">
                <i class="bi bi-graph-up-arrow me-1"></i> Ventas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.inventory') }}">
                <i class="bi bi-boxes me-1"></i> Valoración Inventario
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link rounded-pill px-3 bg-white text-secondary shadow-sm" href="{{ route('reports.top-products') }}">
                <i class="bi bi-trophy me-1"></i> Top Productos
            </a>
        </li>
    </ul>
@endif

{{-- Filtros: Selector Diario / Mensual y Cajero --}}
<div class="card card-custom p-3 bg-white border-0 shadow-sm mb-4 no-print">
    <form method="GET" action="{{ route('reports.cash-register') }}" id="filterForm" class="row g-3 align-items-end">
        {{-- Tipo de Período --}}
        <div class="col-md-3 col-sm-6">
            <label class="form-label small fw-semibold text-muted mb-1">Modalidad de Cuadre</label>
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="period_type" id="periodDaily" value="daily" {{ $period_type === 'daily' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-primary btn-sm py-2" for="periodDaily">
                    <i class="bi bi-calendar-day me-1"></i> Diario
                </label>

                <input type="radio" class="btn-check" name="period_type" id="periodMonthly" value="monthly" {{ $period_type === 'monthly' ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="btn btn-outline-primary btn-sm py-2" for="periodMonthly">
                    <i class="bi bi-calendar-month me-1"></i> Mensual
                </label>
            </div>
        </div>

        {{-- Selector de Fecha o Mes --}}
        @if($period_type === 'monthly')
            <div class="col-md-3 col-sm-6">
                <label for="month" class="form-label small fw-semibold text-muted mb-1">Mes a Cuadrar</label>
                <input type="month" class="form-control form-control-sm py-2" id="month" name="month" value="{{ $selected_month }}">
            </div>
        @else
            <div class="col-md-3 col-sm-6">
                <label for="date" class="form-label small fw-semibold text-muted mb-1">Día a Cuadrar</label>
                <input type="date" class="form-control form-control-sm py-2" id="date" name="date" value="{{ $selected_date }}">
            </div>
        @endif

        {{-- Filtro por Cajero / Vendedor --}}
        <div class="col-md-3 col-sm-6">
            <label for="user_id" class="form-label small fw-semibold text-muted mb-1">Cajero / Vendedor</label>
            <select class="form-select form-select-sm py-2" id="user_id" name="user_id">
                <option value="">Todos los colaboradores</option>
                @foreach($cashiers as $c)
                    <option value="{{ $c->id }}" {{ $selected_user_id == $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Botones de Filtrado --}}
        <div class="col-md-3 col-sm-6 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm py-2 flex-grow-1 fw-semibold rounded-3">
                <i class="bi bi-funnel me-1"></i> Consultar
            </button>
            <a href="{{ route('reports.cash-register') }}" class="btn btn-light btn-sm py-2 text-muted border rounded-3" title="Restablecer a hoy">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
        </div>
    </form>
</div>

{{-- Subtítulo indicador del período actual --}}
<div class="alert alert-light border shadow-sm d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3 mb-4 rounded-3 no-print">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill text-primary"></i>
        <span>
            Mostrando cuadre <strong>{{ $period_type === 'monthly' ? 'mensual de ' . $selected_month : 'diario del ' . \Carbon\Carbon::parse($selected_date)->translatedFormat('l, d \d\e F \d\e Y') }}</strong>
            @if($selected_user_id)
                — Cajero: <strong>{{ $cashiers->firstWhere('id', $selected_user_id)?->name }}</strong>
            @endif
        </span>
    </div>
    <span class="badge bg-secondary-subtle text-secondary font-monospace">
        {{ $sales_count }} {{ $sales_count === 1 ? 'transacción' : 'transacciones' }}
    </span>
</div>

{{-- Tarjetas KPI de Resumen General --}}
@php
    $cashAmount = $by_method['cash']['amount'] ?? 0;
    $electronicAmount = ($by_method['transfer']['amount'] ?? 0) + ($by_method['card']['amount'] ?? 0) + ($by_method['other']['amount'] ?? 0);
@endphp

<div class="row g-3 mb-4 print-kpi-grid">
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-primary">
            <span class="text-muted small fw-semibold text-uppercase">Total Recaudado (Ventas)</span>
            <h3 class="fw-bold mb-0 mt-1 text-primary">${{ number_format($total_revenue, 0, ',', '.') }}</h3>
            <small class="text-muted">{{ $sales_count }} transacciones cobradas</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-danger">
            <span class="text-muted small fw-semibold text-uppercase">Egresos y Gastos</span>
            <h3 class="fw-bold mb-0 mt-1 text-danger">-${{ number_format($total_expenses, 0, ',', '.') }}</h3>
            <small class="text-muted">
                {{ $expenses_count ?? count($expenses) }} pagos operativos registrados
            </small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-success">
            <span class="text-muted small fw-semibold text-uppercase">Efectivo Neto en Gaveta</span>
            <h3 class="fw-bold mb-0 mt-1 text-success">${{ number_format($net_cash_in_drawer, 0, ',', '.') }}</h3>
            <small class="text-muted">Caja Menor física disponible</small>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card card-custom p-3 bg-white h-100 border-0 shadow-sm border-start border-4 border-info">
            <span class="text-muted small fw-semibold text-uppercase">Balance Neto del Turno</span>
            <h3 class="fw-bold mb-0 mt-1 {{ $net_turn_balance >= 0 ? 'text-primary' : 'text-danger' }}">
                ${{ number_format($net_turn_balance, 0, ',', '.') }}
            </h3>
            <small class="text-muted">Ventas netas menos egresos</small>
        </div>
    </div>
</div>

@if($currentBusiness->isRestaurant())
    {{-- MÓDULO RESTAURANTE: CANALES DE VENTA Y RECAUDO DE FLETES --}}
    <div class="row g-3 mb-4 print-restaurant-row">
        {{-- Tarjeta: Desglose por Canales de Venta --}}
        <div class="col-lg-8 print-channels-col">
            <div class="card card-custom p-4 bg-white border-0 shadow-sm h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>Discriminación por Canal de Venta
                    </h5>
                    <span class="badge bg-light text-dark border">Vertical Restaurante</span>
                </div>
                <div class="row g-3 print-channel-grid">
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 bg-light border border-primary-subtle">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-aspect-ratio text-primary fs-5"></i>
                                <span class="fw-semibold text-dark">Ventas Salón (Mesas)</span>
                            </div>
                            <h4 class="fw-bold text-primary mb-0 print-nowrap">${{ number_format($channels['table']['total'], 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $channels['table']['count'] }} comandas liquidadas</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 bg-light border border-info-subtle">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-bicycle text-info fs-5"></i>
                                <span class="fw-semibold text-dark">Ventas Domicilios</span>
                            </div>
                            <h4 class="fw-bold text-info mb-0 print-nowrap">${{ number_format($channels['delivery']['total'], 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $channels['delivery']['count'] }} pedidos entregados</small>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="p-3 rounded-3 bg-light border border-secondary-subtle">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-bag text-secondary fs-5"></i>
                                <span class="fw-semibold text-dark">Ventas Para Llevar</span>
                            </div>
                            <h4 class="fw-bold text-dark mb-0 print-nowrap">${{ number_format($channels['takeout']['total'], 0, ',', '.') }}</h4>
                            <small class="text-muted">{{ $channels['takeout']['count'] }} órdenes en mostrador</small>
                        </div>
                    </div>
                </div>

                {{-- Recaudo de Domicilios / Fletes y Propinas en Grilla Horizontal para Impresión --}}
                <div class="print-fletes-propinas-grid">
                    {{-- Recaudo de Domicilios / Fletes (Cuadre con Repartidores) --}}
                    <div class="mt-3 p-3 bg-info-subtle rounded-3 border border-info text-info-emphasis d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <i class="bi bi-cash-stack fs-5 me-2 align-middle"></i>
                            <strong class="align-middle">Recaudo Total por Fletes / Domicilios:</strong>
                            <span class="d-block small text-muted mt-1 d-print-none">Monto acumulado por fletes para liquidación y cuadre con repartidores.</span>
                        </div>
                        <div class="text-end">
                            <span class="h4 fw-bold text-dark mb-0 print-nowrap">${{ number_format($total_delivery_fee, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    {{-- Recaudo de Propinas Voluntarias (Bolsa de Propinas para Personal de Servicio) --}}
                    <div class="mt-3 p-3 bg-success-subtle rounded-3 border border-success text-success-emphasis d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <i class="bi bi-heart-fill fs-5 me-2 align-middle text-success"></i>
                            <strong class="align-middle">Total Propinas Recaudadas (Servicio Voluntario):</strong>
                            <span class="d-block small text-muted mt-1 d-print-none">
                                Bolsa de propinas acumulada para distribución al personal de servicio.
                                (Efectivo: <strong>${{ number_format($cash_tips ?? 0, 0, ',', '.') }}</strong> | 
                                Digital: <strong>${{ number_format($digital_tips ?? 0, 0, ',', '.') }}</strong>)
                            </span>
                        </div>
                        <div class="text-end">
                            <span class="h4 fw-bold text-success mb-0 print-nowrap">${{ number_format($total_tips ?? 0, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tarjeta: Conciliación de Efectivo Físico vs Dinero Digital --}}
        <div class="col-lg-4 print-conciliacion-col">
            <div class="card card-custom p-4 bg-white border-0 shadow-sm h-100">
                <h5 class="fw-bold mb-3 text-dark">
                    <i class="bi bi-arrow-left-right text-success me-2"></i>Conciliación de Arqueo
                </h5>
                <div class="list-group list-group-flush mb-3 print-conciliacion-list">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <div>
                            <span class="fw-semibold text-dark d-block"><i class="bi bi-cash me-1 text-success"></i>Efectivo en Gaveta</span>
                            <small class="text-muted d-print-none">Total billetes restando vueltos</small>
                        </div>
                        <span class="fw-bold font-monospace text-success fs-6 print-nowrap">${{ number_format($cash_in_drawer, 0, ',', '.') }}</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                        <div>
                            <span class="fw-semibold text-dark d-block"><i class="bi bi-phone me-1 text-info"></i>Dinero Digital (Apps / Bancos)</span>
                            <small class="text-muted d-print-none">Nequi, Daviplata, Tarjetas</small>
                        </div>
                        <span class="fw-bold font-monospace text-info fs-6 print-nowrap">${{ number_format($digital_money, 0, ',', '.') }}</span>
                    </div>
                </div>
                <div class="border-top pt-2 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">Total Conciliado:</span>
                    <span class="fw-bold font-monospace fs-5 text-primary print-nowrap">${{ number_format($total_revenue, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- Cuadre Discriminado por Métodos de Pago --}}
<div class="card card-custom p-4 bg-white border-0 shadow-sm mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h5 class="fw-bold mb-0">
            <i class="bi bi-wallet2 text-primary me-2"></i>Discriminación por Medios de Pago
        </h5>
        <span class="small text-muted">Conciliación contra registros contables</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 table-payment-methods">
            <thead class="table-light">
                <tr>
                    <th>Método de Pago</th>
                    <th class="text-center print-nowrap" style="width: 130px;">Transacciones</th>
                    <th class="text-end print-nowrap" style="width: 140px;">% Cuota</th>
                    <th class="text-end print-nowrap" style="width: 180px;">Total Percibido</th>
                    <th>Detalle de Operación / Conciliación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($by_method as $key => $method)
                    @php
                        $percentage = $total_revenue > 0 ? round(($method['amount'] / $total_revenue) * 100, 1) : 0;
                    @endphp
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-{{ $method['color'] }}-subtle text-{{ $method['color'] }} p-2 rounded-circle d-print-none">
                                    <i class="bi {{ $method['icon'] }} fs-6"></i>
                                </span>
                                <div>
                                    <strong class="d-block">{{ $method['name'] }}</strong>
                                    <small class="text-muted d-print-none">
                                        @if($key === 'cash')
                                            Moneda física en cajón
                                        @elseif($key === 'transfer')
                                            Cuentas de ahorro / Nequi / Daviplata
                                        @elseif($key === 'card')
                                            Vouchers datáfono débito/crédito
                                        @else
                                            Otros convenios o acuerdos
                                        @endif
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td class="text-center print-nowrap">
                            <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                {{ $method['transactions_count'] }}
                            </span>
                        </td>
                        <td class="text-end print-nowrap">
                            <span class="fw-semibold">{{ $percentage }}%</span>
                            <div class="progress mt-1 d-print-none" style="height: 4px;">
                                <div class="progress-bar bg-{{ $method['color'] }}" style="width: {{ $percentage }}%"></div>
                            </div>
                        </td>
                        <td class="text-end print-nowrap">
                            <span class="fw-bold font-monospace fs-6 text-{{ $method['color'] }}">
                                ${{ number_format($method['amount'], 0, ',', '.') }}
                            </span>
                        </td>
                        <td>
                            @if($key === 'cash')
                                <small class="text-muted">
                                    Billetes recibidos: <strong>${{ number_format($method['nominal_received'] ?? $method['amount'], 0, ',', '.') }}</strong>
                                    — Cambio devuelto: <span class="text-danger">-${{ number_format($method['change_given'] ?? 0, 0, ',', '.') }}</span>
                                    = <strong class="text-success">Neto en caja: ${{ number_format($method['amount'], 0, ',', '.') }}</strong>
                                </small>
                            @elseif($key === 'transfer')
                                <small class="text-muted">
                                    Verificar comprobantes en la app bancaria contra la columna Referencia
                                </small>
                            @elseif($key === 'card')
                                <small class="text-muted">
                                    Cierre de lote de datáfono debe sumar exactamente este valor
                                </small>
                            @else
                                <small class="text-muted">
                                    Conciliación según soportes internos registrados
                                </small>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr class="fw-bold">
                    <td>TOTAL CUADRE GENERAL</td>
                    <td class="text-center font-monospace print-nowrap">{{ $sales_count }}</td>
                    <td class="text-end print-nowrap">100%</td>
                    <td class="text-end font-monospace text-primary fs-5 print-nowrap">
                        ${{ number_format($total_revenue, 0, ',', '.') }}
                    </td>
                    <td>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-circle-fill me-1"></i> Balance cuadrado con ventas completadas
                        </span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- ARQUEO OPERATIVO: CAJA MENOR (EFECTIVO FÍSICO) Y CAJA GENERAL (CUENTAS DIGITALES) --}}
<div class="row g-3 mb-4 print-arqueo-grid">
    {{-- Caja Menor (Efectivo Físico) --}}
    <div class="col-md-6 col-lg-4">
        <div class="card card-custom p-4 bg-white border-0 shadow-sm h-100 border-top border-4 border-success">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-cash-stack text-success me-2"></i>Arqueo de Caja Menor
                </h5>
                <span class="badge bg-success-subtle text-success">Efectivo Físico</span>
            </div>
            <p class="small text-muted mb-3">Gaveta del local para cambio/vueltos, ventas en efectivo y salidas menores.</p>
            <div class="list-group list-group-flush mb-3">
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                    <span class="text-dark small"><i class="bi bi-plus-circle text-success me-1"></i>Ventas Cobradas en Efectivo</span>
                    <span class="fw-bold font-monospace text-success fs-6">${{ number_format($cash_in_drawer, 0, ',', '.') }}</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                    <span class="text-dark small"><i class="bi bi-dash-circle text-danger me-1"></i>Gastos y Salidas en Efectivo</span>
                    <span class="fw-bold font-monospace text-danger fs-6">-${{ number_format($cash_expenses, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="border-top pt-3 d-flex justify-content-between align-items-center mt-auto bg-light p-3 rounded-3">
                <div>
                    <strong class="d-block text-dark small text-uppercase">Total Efectivo a Entregar:</strong>
                    <small class="text-muted">Efectivo Neto Esperado en Gaveta</small>
                </div>
                <span class="fw-bold font-monospace fs-5 text-success">${{ number_format($net_cash_in_drawer, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Caja General (Cuentas Digitales y Bancarias) --}}
    <div class="col-md-6 col-lg-4">
        <div class="card card-custom p-4 bg-white border-0 shadow-sm h-100 border-top border-4 border-info">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-bank text-info me-2"></i>Caja General
                </h5>
                <span class="badge bg-info-subtle text-info">Cuentas Digitales</span>
            </div>
            <p class="small text-muted mb-3">Recaudos por Nequi, Daviplata, datáfono y pagos por transferencia bancaria.</p>
            <div class="list-group list-group-flush mb-3">
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                    <span class="text-muted small">Transferencias / Nequi / Daviplata</span>
                    <span class="font-monospace text-dark">${{ number_format($by_method['transfer']['amount'] ?? 0, 0, ',', '.') }}</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                    <span class="text-muted small">Tarjetas / Datáfono</span>
                    <span class="font-monospace text-dark">${{ number_format($by_method['card']['amount'] ?? 0, 0, ',', '.') }}</span>
                </div>
                @if(($by_method['other']['amount'] ?? 0) > 0)
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1">
                        <span class="text-muted small">Otros medios</span>
                        <span class="font-monospace text-dark">${{ number_format($by_method['other']['amount'], 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-top">
                    <span class="text-dark small"><i class="bi bi-dash-circle text-danger me-1"></i>Gastos Pagados por Transferencia</span>
                    <span class="fw-bold font-monospace text-danger fs-6">-${{ number_format($digital_expenses, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="border-top pt-3 d-flex justify-content-between align-items-center mt-auto bg-light p-3 rounded-3">
                <div>
                    <strong class="d-block text-dark small text-uppercase">Saldo en Cuentas Digitales:</strong>
                    <small class="text-muted">Total disponible en bancos/apps</small>
                </div>
                <span class="fw-bold font-monospace fs-5 text-info">${{ number_format($net_digital_money, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Balance Total Consolidado --}}
    <div class="col-md-12 col-lg-4">
        <div class="card card-custom p-4 bg-white border-0 shadow-sm h-100 border-top border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calculator text-primary me-2"></i>Balance Consolidado
                </h5>
                <span class="badge bg-primary-subtle text-primary">Arqueo Total</span>
            </div>
            <p class="small text-muted mb-3">Consolidación de Caja Menor y Caja General (Total Ventas - Total Gastos).</p>
            <div class="list-group list-group-flush mb-3">
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                    <span class="text-dark small"><i class="bi bi-plus-circle text-primary me-1"></i>Total Ventas Recaudadas</span>
                    <span class="fw-bold font-monospace text-primary fs-6">${{ number_format($total_revenue, 0, ',', '.') }}</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-2">
                    <span class="text-dark small"><i class="bi bi-dash-circle text-danger me-1"></i>Total Gastos Operativos</span>
                    <span class="fw-bold font-monospace text-danger fs-6">-${{ number_format($total_expenses, 0, ',', '.') }}</span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center px-0 py-1 small text-muted">
                    <span>Efectivo Físico + Bancos</span>
                    <span>${{ number_format($net_cash_in_drawer, 0, ',', '.') }} + ${{ number_format($net_digital_money, 0, ',', '.') }}</span>
                </div>
            </div>
            <div class="border-top pt-3 d-flex justify-content-between align-items-center mt-auto bg-light p-3 rounded-3">
                <div>
                    <strong class="d-block text-dark small text-uppercase">Balance Neto del Período:</strong>
                    <small class="text-muted">Utilidad neta de caja</small>
                </div>
                <span class="fw-bold font-monospace fs-5 {{ $net_turn_balance >= 0 ? 'text-primary' : 'text-danger' }}">
                    ${{ number_format($net_turn_balance, 0, ',', '.') }}
                </span>
            </div>
        </div>
    </div>
</div>

{{-- Detalle Individual de Gastos Operativos del Período --}}
@if($expenses->isNotEmpty())
    <div class="card card-custom p-4 bg-white border-0 shadow-sm mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-receipt-cutoff text-danger me-2"></i>Detalle de Gastos Operativos
                </h5>
                <small class="text-muted">Egresos registrados durante este período que impactan el arqueo de caja</small>
            </div>
            <span class="badge bg-danger-subtle text-danger font-monospace">
                {{ $expenses->count() }} {{ $expenses->count() === 1 ? 'gasto registrado' : 'gastos registrados' }}
            </span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-expenses">
                <thead class="table-light">
                    <tr>
                        <th class="print-nowrap">Comprobante</th>
                        <th class="print-nowrap">Fecha de Pago</th>
                        <th class="print-nowrap">Categoría</th>
                        <th>Proveedor / Beneficiario</th>
                        <th class="print-nowrap">Caja / Método</th>
                        <th>Descripción</th>
                        <th class="text-end print-nowrap">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses as $expense)
                        @php
                            $pmValue = $expense->payment_method instanceof \App\Enums\PaymentMethod ? $expense->payment_method->value : (string)$expense->payment_method;
                            $isCashExpense = ($pmValue === 'cash');
                        @endphp
                        <tr>
                            <td class="font-monospace fw-semibold print-nowrap">
                                {{ $expense->invoice_number ?? 'S/N' }}
                            </td>
                            <td class="small print-nowrap">
                                {{ $expense->paid_at ? $expense->paid_at->format('d/m/Y') : ($expense->issue_date ? $expense->issue_date->format('d/m/Y') : '—') }}
                            </td>
                            <td class="print-nowrap">
                                <span class="badge bg-light text-dark border">
                                    {{ $expense->category instanceof \App\Enums\ExpenseCategory ? $expense->category->label() : $expense->category }}
                                </span>
                            </td>
                            <td class="small">
                                {{ $expense->supplier ? $expense->supplier->name : 'Gasto General' }}
                            </td>
                            <td class="print-nowrap">
                                @if($isCashExpense)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-cash me-1"></i>Efectivo (Caja Menor)
                                    </span>
                                @else
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">
                                        <i class="bi bi-bank me-1"></i>Digital (Caja General)
                                    </span>
                                @endif
                            </td>
                            <td class="small text-muted" style="max-width: 250px;">
                                {{ $expense->description ? Str::limit($expense->description, 50) : '—' }}
                            </td>
                            <td class="text-end font-monospace fw-bold text-danger print-nowrap">
                                -${{ number_format($expense->amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr class="fw-bold">
                        <td colspan="6">TOTAL EGRESOS DEL PERÍODO</td>
                        <td class="text-end font-monospace text-danger fs-6 print-nowrap">
                            -${{ number_format($total_expenses, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

{{-- Detalle Individual de Ventas del Período --}}
<div class="card card-custom p-4 bg-white border-0 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h5 class="fw-bold mb-0">Detalle de Ventas Registradas</h5>
            <small class="text-muted">Listado cronológico de comprobantes que componen este cuadre</small>
        </div>
        <span class="badge bg-light text-secondary border font-monospace">
            {{ $sales->count() }} registros
        </span>
    </div>

    @if($sales->isEmpty())
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
            <h6 class="fw-bold">No se encontraron ventas para este período</h6>
            <p class="small mb-0">Verifica la fecha o mes seleccionado en los filtros superiores.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-sales">
                <thead class="table-light">
                    <tr>
                        <th class="print-nowrap">Factura</th>
                        <th class="print-nowrap">Fecha / Hora</th>
                        <th class="print-nowrap">Cajero</th>
                        <th>Cliente</th>
                        <th>Método(s) de Pago</th>
                        <th class="print-notes">Notas</th>
                        <th class="text-end print-nowrap">Total</th>
                        <th class="text-center no-print" style="width: 100px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales as $sale)
                        <tr>
                            <td class="print-nowrap">
                                <a href="{{ route('sales.show', $sale) }}" class="fw-bold text-decoration-none font-monospace">
                                    {{ $sale->invoice_number }}
                                </a>
                                @if($currentBusiness->isRestaurant() && $sale->order_type && $sale->order_type !== 'retail')
                                    <div class="mt-1">
                                        @if($sale->order_type === 'table')
                                            <span class="badge bg-primary-subtle text-primary border" style="font-size: 0.72rem;">Salón</span>
                                        @elseif($sale->order_type === 'delivery')
                                            <span class="badge bg-info-subtle text-info border" style="font-size: 0.72rem;">Domicilio</span>
                                            @if($sale->delivery_fee > 0)
                                                <span class="badge bg-light text-dark border" style="font-size: 0.72rem;">+${{ number_format($sale->delivery_fee, 0, ',', '.') }} flete</span>
                                            @endif
                                        @elseif($sale->order_type === 'takeout')
                                            <span class="badge bg-secondary-subtle text-secondary border" style="font-size: 0.72rem;">Para Llevar</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="small print-nowrap">
                                {{ $sale->sale_date->format('d/m/Y H:i') }}
                            </td>
                            <td class="small print-nowrap">
                                <i class="bi bi-person me-1 text-muted"></i>{{ $sale->user ? $sale->user->name : 'N/A' }}
                            </td>
                            <td class="small">
                                {{ $sale->display_customer_name ?? $sale->customer_name ?? ($sale->customer ? $sale->customer->name : config('sales.default_customer_name', 'Consumidor Final')) }}
                            </td>
                            <td>
                                @if($sale->payments->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($sale->payments as $payment)
                                            <span class="badge bg-light text-dark border font-monospace print-nowrap" style="font-size: 0.78rem;">
                                                {{ $payment->method_label }}: ${{ number_format($payment->amount, 0, ',', '.') }}
                                                @if($payment->reference)
                                                    <span class="text-muted">({{ $payment->reference }})</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge bg-light text-dark border font-monospace print-nowrap" style="font-size: 0.78rem;">
                                        {{ $sale->payment_method_label ?? $sale->payment_method }}: ${{ number_format($sale->total, 0, ',', '.') }}
                                    </span>
                                @endif
                            </td>
                            <td class="small text-muted print-notes">
                                {{ $sale->notes ? Str::limit($sale->notes, 40) : '—' }}
                            </td>
                            <td class="text-end font-monospace fw-bold print-nowrap">
                                ${{ number_format($sale->total, 0, ',', '.') }}
                            </td>
                            <td class="text-center no-print">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('sales.print.receipt', $sale) }}" target="_blank" class="btn btn-outline-secondary" title="Imprimir Ticket">
                                        <i class="bi bi-receipt"></i>
                                    </a>
                                    <a href="{{ route('sales.show', $sale) }}" class="btn btn-outline-primary" title="Ver detalle">
                                        <i class="bi bi-eye"></i>
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
@endsection
